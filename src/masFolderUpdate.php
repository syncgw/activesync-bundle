<?php
declare(strict_types=1);

/*
 * 	<FolderUpdate> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\document\field\fldDescription;
use syncgw\document\field\fldGroupName;
use syncgw\lib\Msg;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Device;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;

class masFolderUpdate {

	// status codes
	const EXIST	  		 = '2';
	const RIP	  		 = '3';
	const FOUND	  		 = '4';
	const PARENT  		 = '5';
	const SERVER  		 = '6';
	const SYNCKEY 		 = '9';
	const FORMAT  		 = '10';
	const UNKNOWN 		 = '11';

	// status description
	const STAT    = [
	    self::EXIST		 =>	'A folder with that name already exists or the specified folder is a special folder',
		self::RIP		 =>	'The specified folder is the Recipient information folder, which cannot be updated by the client',
		self::FOUND		 =>	'The specified folder does not exist',
		self::PARENT	 =>	'The specified parent folder was not found',
		self::SERVER	 => 'An error occurred on the server',
		self::SYNCKEY	 =>	'Synchronization key mismatch or invalid synchronization key',
		self::FORMAT	 =>	'Incorrectly formatted request',
		self::UNKNOWN	 =>	'An unknown error occurred',
	];

    /**
     * 	Singleton instance of object
     * 	@var masFolderUpdate
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masFolderUpdate {

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
					 'FolderUpdate'));
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

		Msg::InfoMsg($in, '<FolderUpdate> input');

		// moves a folder from one location to another on the server
		$out->addVar('FolderUpdate', null, false, $out->setCP(XML::AS_FOLDER));
		$out->addVar('Status', $rc = masStatus::OK);

		// get last sync key
		$usr = User::getInstance();
		$key = $usr->syncKey('All');

		// check for key
		if (($k = $in->getVar('SyncKey')) === null) {

			Msg::WarnMsg('<SyncKey> missing');
			$rc = self::FORMAT;
		} elseif ($k != $key) {

			Msg::WarnMsg('<SyncKey> "'.$k.'" does not match "'.$key.'"');
    		$rc = self::SYNCKEY;
    	}

		$out->addVar('Status', $rc);

		if ($rc != masStatus::OK) {

			$out->getVar('FolderUpdate');
			Msg::InfoMsg($out, '<FolderUpdate> output');
		    return true;
		}

		// identifies the folder on the server to be renamed or moved
		if (($hid = array_search(substr($fid = $in->getVar('ServerId'), 0, 1), Util::HID(Util::HID_PREF))) === null) {

			Msg::WarnMsg('Data store for folder "'.$fid.'" not found');
			$rc = self::REQUEST;
		}

		if ($rc == masStatus::OK) {

		    $db = DB::getInstance();

		    if ($par = $in->getVar('ParentId')) {
    			if (substr($par, 0, 1) != substr($fid, 0, 1)) {

    				Msg::WarnMsg('Data store for parent folder "'.$par.'" not found');
    				$rc = self::REQUEST;
    			} elseif (!$db->Query($hid, DataStore::RGID, $par)) {

    				Msg::WarnMsg('Parent record "'.$par.'" not found');
    				$rc = self::PARENT;
    			}
		    }

		    $nam = $in->getVar('DisplayName');

    		// represent the synchronization state of a collection
    		// If the <FolderCreate> command, <FolderDelete> command, or <FolderUpdate> command is not successful,
			// the server MUST NOT return a <SyncKey> element
			if ($rc == masStatus::OK) {

				$out->addVar('SyncKey', $usr->syncKey('All', 1));

		        // move/update folder

			    // need to reload record
    			$doc = $db->Query($hid, DataStore::RGID, $fid);
    			if ($par)
    				$doc->updVar('Group', '('.$par.')');

    			$doc->updVar(fldGroupName::TAG, $nam);
    			$dev = Device::getInstance();
    			$doc->updVar(fldDescription::TAG, 'Group provided by "'.$dev->getVar('GUID').'"' );
    			if (!$db->Query($hid, DataStore::UPD, $doc))
    				$rc = self::SERVER;
    		}
		}

		// update status
		if ($rc != masStatus::OK)
			$out->updVar('Status', $rc);

		$out->getVar('FolderUpdate');
		Msg::InfoMsg($out, '<FolderUpdate> output');

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
