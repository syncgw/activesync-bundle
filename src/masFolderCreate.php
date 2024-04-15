<?php
declare(strict_types=1);

/*
 * 	<FolderCreate> handler class
 *
 *	@package	sync*gw
 * 	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Device;
use syncgw\lib\User;
use syncgw\lib\XML;
use syncgw\document\field\fldDescription;
use syncgw\document\field\fldGroupName;

class masFolderCreate {

	// status codes
	const EXIST	  		 = '2';
	const SYSTEM  		 = '3';
	const PARENT  		 = '5';
	const SERVER  		 = '6';
	const SYNCKEY 		 = '9';
	const FORMAT  		 = '10';
	const UNKNOWN 		 = '11';
	const CODE	  		 = '12';
	// status description
	const STAT    		 = [
		self::EXIST		 =>	'A folder that has this name already exists',
		self::SYSTEM	 =>	'The specified parent folder is a special system folder',
		self::PARENT	 =>	'The specified parent folder was not found',
		self::SERVER	 => 'An error occurred on the server',
		self::SYNCKEY	 =>	'Synchronization key mismatch or invalid synchronization key',
		self::FORMAT	 =>	'Malformed request',
		self::UNKNOWN	 =>	'An unknown error occurred',
		self::CODE		 =>	'Code unknown',
	];

    /**
     * 	Singleton instance of object
     * 	@var masFolderCreate
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masFolderCreate {

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
					  'FolderCreate'));
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

		Msg::InfoMsg($in, '<FolderCreate> input');

		// creates a new folder as a child folder of the specified parent folder
		$out->addVar('FolderCreate', null, false, $out->setCP(XML::AS_FOLDER));

		// get last sync key
		$usr = User::getInstance();
		$key = $usr->syncKey('All');

		$rc = masStatus::OK;

		// <SyncKey> represent the synchronization state of a collection
		if (($k = $in->getVar('SyncKey')) === null) {
			Msg::WarnMsg('<SyncKey> missing');
			$rc = self::FORMAT;
		} elseif ($k != $key) {
    		Msg::WarnMsg('<SyncKey> "'.$k.'" does not match "'.$key.'"');
    		$rc = self::SYNCKEY;
    	}

		$out->addVar('Status', $rc);

		if ($rc != masStatus::OK) {
    		$out->getVar('FolderCreate');
    		Msg::InfoMsg($out, '<FolderCreate> output');
		    return true;
		}

    	// <ParentId> specifies the server ID of the parent folder
		// A parent ID of 0 (zero) signifies the mailbox root folder
		if (($pid = $in->getVar('ParentId')) == '0')
			$pid = '';

		// <DisplayName> specifies the name of the folder that is shown to the user
		$nam = $in->getVar('DisplayName');

		$cnf = Config::getInstance();
		$ena = $cnf->getVar(Config::ENABLED);

		// <Type> specifies the type of the folder to be created
		switch ($in->getVar('Type')) {
		case masFolderType::F_UTASK:
			if (!($ena & ($hid = DataStore::TASK))) {

				$rc = self::PARENT;
				Msg::WarnMsg('Task data store not enabled');
			}
			break;

		case masFolderType::F_UCALENDAR:
			if (!($ena & ($hid = DataStore::CALENDAR))) {
			 	$rc = self::PARENT;
				Msg::WarnMsg('Calendar data store not enabled');
			}
			break;

		case masFolderType::F_UCONTACT:
			if (!($ena & ($hid = DataStore::CONTACT))) {

			 	$rc = self::PARENT;
				Msg::WarnMsg('Contact data store not enabled');
			}
			break;

		case masFolderType::F_UNOTE:
			if (!($ena & ($hid = DataStore::NOTE))) {

			 	$rc = self::PARENT;
				Msg::WarnMsg('Note data store not enabled');
			}
			break;

		case masFolderType::F_UMAIL:
			if (!($ena & ($hid = DataStore::MAIL))) {

			 	$rc = self::PARENT;
				Msg::WarnMsg('Mail data store not enabled');
			}
			break;

		// masFolderType::F_GENERIC
		default:
			Msg::WarnMsg('The requested folder type "'.$in->getVar('Type').'" is not supported');
			$rc = self::SYSTEM;
			break;
		}

		// check parent folder
		if ($rc == masStatus::OK && $pid) {
			$db = DB::getInstance();
			if (!$db->Query($hid, DataStore::RGID, $pid)) {

				Msg::WarnMsg('Parent "'.$pid.'" not found');
				$rc = self::PARENT;
			}
		}

		// If the <FolderCreate> command, <FolderDelete> command, or <FolderUpdate> command is not successful,
		// the server MUST NOT return a <SyncKey> element
		if ($rc == masStatus::OK)
			$out->addVar('SyncKey', $usr->syncKey('All', 1));

		// uniquely identifies a new folder on a server
		if ($rc == masStatus::OK) {
			$db  = DB::getInstance();
			$dev = Device::getInstance();
			$xml = $db->mkDoc($hid, [ fldGroupName::TAG 	=> $nam,
									  fldDescription::TAG => 'Folder provided by "'.$dev->getVar('GUID').'"',
									  'Group' 	  			=> $pid ], true);
			$out->addVar('ServerId', $xml->getVar('GUID'));
		} else
			$out->updVar('Status', $rc);

		$out->getVar('FolderCreate');
		Msg::InfoMsg($out, '<FolderCreate> output');

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
