<?php
declare(strict_types=1);

/*
 * 	<Ping> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Server;
use syncgw\lib\Util;
use syncgw\lib\XML;

class masPing {

	// status codes
	const EXPIRED	= '1';
	const CHANGE	= '2';
	const PARM		= '3';
	const FORMAT	= '4';
	const MAXHB		= '5';
	const MAXFOLDER	= '6';
	const HIERARCHY = '7';
	const SERVER	= '8';

	// status description
	const STAT      = [
			self::EXPIRED		=> 	'The heartbeat interval expired before any changes occurred in the folders being monitored.',
			self::CHANGE		=>	'Changes occurred in at least one of the monitored folders. The response specifies the changed folders.',
			self::PARM			=>	'The Ping command request omitted required parameters.',
			self::FORMAT		=>	'Syntax error in Ping command request.',
			self::MAXHB			=>	'The specified heartbeat interval is outside the allowed range. For intervals that were too short, the '.
									'response contains the shortest allowed interval. For intervals that were too long, the response '.
									'contains the longest allowed interval.',
			self::MAXFOLDER		=>	'The Ping command request specified more than the allowed number of folders to monitor. The '.
									'response indicates the allowed number in the MaxFolders element',
			self::HIERARCHY		=>	'Folder hierarchy sync required.',
			self::SERVER		=>	'An error occurred on the server.',
	];

    /**
     * 	Singleton instance of object
     * 	@var masPing
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masPing {

		if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
 	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Opt', '<a href="https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-ascmd" '.
					 'target="_blank">[MS-ASCMD]</a> '.sprintf('Exchange ActiveSync &lt;%s&gt; handler', 'Ping'));
		$xml->addVar('Stat', 'v27.0');
	}

	/**
	 * 	Parse XML node
	 *
	 * 	@param	- Input document
	 * 	@param	- Output document
	 * 	@return	- true = Ok; false = Error
	 */
	public function Parse(XML &$in, XML &$out): bool {

		$cnf = Config::getInstance();
		$db  = DB::getInstance();
		$hdl = array_flip(Util::HID(Util::HID_PREF));
		$mas = masHandler::getInstance();

		Msg::InfoMsg($in, '<Ping> input');

		// unregister shutdown function to protect any <SyncKey> value
		$srv = Server::getInstance();
		$srv->unregShutdown('syncgw\lib\User');

		$out->addVar('Ping', null, false, $out->setCP(XML::AS_PING));
		$out->addVar('Status', self::EXPIRED);

		// get max. sleep time we support
   		$end   = $cnf->getVar(Config::HEARTBEAT);
		// sleep time
		$sleep = $cnf->getVar(Config::PING_SLEEP);

   		// it specifies the length of time, in seconds, that the server SHOULD wait before sending a response if no
   		// new items are added to the specified set of folders
		if (($hb = $in->getVar('HeartbeatInterval')) !== null) {

		    if ($hb < 10 || $hb > $end) {

				$out->updVar('Status', self::MAXHB);
				$out->addVar('HeartbeatInterval', strval($end));
				$out->getVar('Ping');
				Msg::InfoMsg($out, '<Ping> output');
				return true;
		    }
		} else
		    $hb = $end;

	    // end of time...
	    $end += time();

		// identifies the folder and folder type to be monitored by the client -> must be cached
		$grps = [];
		if ($in->xpath('//Folder')) {

			// get folder information
			while ($in->getItem() !== null) {

				// specifies the server ID of the folder to be monitored
				$gid = $in->getVar('Id', false);

				if (!isset($hdl[substr($gid, 0, 1)]) || !($hid = $hdl[substr($gid, 0, 1)])) {

					$out->updVar('Status', self::EXPIRED);
					Msg::InfoMsg('Handler for "'.$gid.'" not available / not enabled. Skipping check.');
					continue;
				}

				// <Class> not used
				$grps[$gid] = $hid;

				// enable existing ping entries
				$mas->PingStat(masHandler::SET, $hid, $gid);
			}
		} else {

			// check all available handlers
			foreach (Util::HID(Util::HID_PREF, DataStore::DATASTORES) as $hid => $unused) {

				foreach ($mas->PingStat(masHandler::LOAD, $hid) as $gid)
					$grps[$gid] = $hid;
			}
		}
		$unused; // disable Eclipse warning

	    Msg::InfoMsg($grps, 'Folder(s) to monitor');

		// change buffer
		$chg = [];

		// process all folders
		while (time() < $end) {

			// process all groups
			foreach ($grps as $gid => $hid) {

				// synhronize data store
				$ds = Util::HID(Util::HID_CNAME, $hid);
				$ds = $ds::getInstance();
				if ($ds->syncDS($gid, true) === false) {

				    // we never should go here!
    				Msg::WarnMsg('SyncDS() failed for ['.$gid.'] - this may be ok');
					$mas->setStat(masHandler::EXIT);
    				return true;
        		}

				// check for changed records
				Msg::InfoMsg('Checking group ['.$gid.'] in '.Util::HID(Util::HID_CNAME, $hid));
				if ($db->Query($hid, DataStore::RNOK, $gid))
					$chg[] = $gid;
			}

			// anything changed?
			if (count($chg))
				break;

			// are we debugging?
			if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE) {
				Msg::InfoMsg('We do not wait until end of timeout in "'.$hb.'" seconds');
				$mas->setStat(masHandler::EXIT);
				return true;
			}

			// check time to sleep
			if (time() + $sleep > $end)
        	    $sleep = $end - time();

        	// we split sleep into single seconds to catch parallel calls
			for ($i=0; $sleep > 0 && $i < $sleep; $i++)
				Util::Sleep(1);

            // double check at which point in time we were
            // we could go here e.g. if HTTP server has been suspended for a while
            // but we're sure connection from client has been dropped, so we don't need to send anything
       	    if (time() > $end) {

				$mas->setStat(masHandler::EXIT);
				return true;
            }

            // request record refresh in external data base
            if (isset($hid))
	            $db->Refresh($hid);
		}

		// any folder changed?
		if (count($chg)) {
			$out->updVar('Status', self::CHANGE);
			$p = $out->savePos();

			$out->addVar('Folders');
			Msg::InfoMsg($chg, 'Changed folders (at minimum one record has status != "OK")');

			// identifies the folder and folder type to be monitored by the client
			foreach ($chg as $gid)
				$out->addVar('Folder', $gid);
			$out->restorePos($p);
		}

		// <MaxFolders> specifies the maximum number of folders that can be monitored -> we monitor as many as client want
		// The element is returned in a response with a status code of 6

		$out->getVar('Ping');
		Msg::InfoMsg($out, '<Ping> output');

		return true;
	}

	/**
	 * 	Get status comment
	 *
	 *  @param  - Path to status code
	 * 	@param	- Return code
	 * 	@return	- Textual equation
	 */
	static public function status(string $path, string $rc): string {

		if (isset(self::STAT[$rc]))
			return self::STAT[$rc];
		if (isset(masStatus::STAT[$rc]))
			return masStatus::STAT[$rc];

		return 'Unknown return code "'.$rc.'"';
	}

}
