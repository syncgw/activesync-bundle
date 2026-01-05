<?php
declare(strict_types=1);

/*
 * 	<FolderDelete> handler class
 *
 * 	@package	sync*gw
 * 	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;

class masFolderDelete {

	// status codes
	const SYSTEM  		 = '3';
	const EXIST	  		 = '4';
	const SERVER  		 = '6';
	const SYNCKEY 		 = '9';
	const FORMAT  		 = '10';
	const UNKNOWN 		 = '11';
	// status description
	const STAT    		 = [
		self::SYSTEM	 => 'The specified folder is a special system folder and cannot be deleted by the client',
		self::EXIST		 => 'The specified folder does not exist',
		self::SERVER	 => 'An error occurred on the server',
		self::SYNCKEY	 =>	'Synchronization key mismatch or invalid synchronization key',
		self::FORMAT	 =>	'Incorrectly formatted request',
		self::UNKNOWN	 =>	'An unknown error occurred',
	];

    /**
     * 	Singleton instance of object
     * 	@var masFolderDelete
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masFolderDelete {

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
					 'target="_blank">[MS-ASCMD]</a> '.sprintf('Exchange ActiveSync &lt;%s&gt; handler',
					 'FolderDelete'));
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

		Msg::InfoMsg($in, '<FolderDelete> input');

		// deletes a folder from the server
		$out->addVar('FolderDelete', null, false, $out->setCP(XML::AS_FOLDER));

		// get last sync key
		$usr = User::getInstance();
		$key = $usr->syncKey('All');

		$rc = masStatus::OK;

		// represent the synchronization state of a collection
		if (($k = $in->getVar('SyncKey')) === null) {

			Msg::WarnMsg('<SyncKey> missing');
			$rc = self::FORMAT;
		} elseif ($k != $key) {

			Msg::WarnMsg('<SyncKey> "'.$k.'" does not match "'.$key.'"');
    		$rc = self::SYNCKEY;
    	}

		$out->addVar('Status', $rc);

		if ($rc != masStatus::OK) {
    		$out->getVar('FolderDelete');

    		Msg::InfoMsg($out, '<FolderDelete> output');
		    return true;
		}

		// specifies the folder on the server to be deleted, and it is a unique identifier assigned by the server
		// to each folder that can be synchronized
		if (($hid = array_search(substr($fid = $in->getVar('ServerId'), 0, 1), Util::HID(Util::HID_PREF))) === null) {

			Msg::WarnMsg('Data store for folder "'.$fid.'" not found');
			$rc = self::REQUEST;
		}

		// represent the synchronization state of a collection
		// If the <FolderCreate> command, <FolderDelete> command, or <FolderUpdate> command is not successful,
		// the server MUST NOT return a <SyncKey> element
		if ($rc == masStatus::OK) {

		    $out->addVar('SyncKey', $usr->syncKey('All', 1));

    		// delete folder
			$db = DB::getInstance();
			if (!$db->Query($hid, DataStore::DEL, $fid)) {

				Msg::WarnMsg('Record "'.$fid.'" does not exist');
				$rc = self::EXIST;
			}

			// delete folder itself
			if (!$db->Query($hid, DataStore::DEL, $fid)) {

				Msg::WarnMsg('Record "'.$fid.'" does not exist');
				$rc = self::EXIST;
			}
		}

		// update status
		if ($rc != masStatus::OK)
			$out->updVar('Status', $rc);

		$out->getVar('FolderDelete');
		Msg::InfoMsg($out, '<FolderDelete> output');

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
