<?php
declare(strict_types=1);

/*
 * 	<GetHierarchy> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\XML;
use syncgw\document\field\fldAttribute;
use syncgw\document\field\fldGroupName;

class masGetHierarchy {

	// mail box conversion table
	const MBOXES = [
		fldAttribute::MBOX_USER	=> '1',		// User-created folder (generic)
		fldAttribute::MBOX_IN	 	=> '2',		// Default Inbox folder
		fldAttribute::MBOX_DRAFT	=> '3',		// Default Drafts folder
		fldAttribute::MBOX_TRASH	=> '4', 	// Default Deleted Items folder
		fldAttribute::MBOX_SENT	=> '5', 	// Default Sent Items folder
		fldAttribute::MBOX_OUT	=> '6',		// Default Outbox folder
	];

    /**
     * 	Singleton instance of object
     * 	@var masGetHierarchy
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masGetHierarchy {

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
					 'GetHierarchy'));
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

		Msg::InfoMsg($in, '<GetHierarchy> input');

		// only for version <= 12.1, above is handled by <FolderSync>

		// Gets the list of email folders from the server. Each folder's place within the folder hierarchy is indicated by its parent ID.
		// The list of folders returned includes only email folders. For example, the Contacts folder and the Calendar folder are not included in the list.

		// a required element in <GetHierarchy> command responses that specifies the folders in a mailbox
		$out->addVar('Folders', null, false, $out->setCP(XML::AS_FOLDER));

		$db = DB::getInstance();

		foreach ($db->Query(DataStore::MAIL, DataStore::GRPS) as $gid => $unused) {

			$op = $out->savePos();

			// contains details about a folder
			$doc = $db->Query(DataStore::MAIL, DataStore::RGID, $gid);

			// specifies the display name of the folder
			$out->addVar('DisplayName', $doc->getVar(fldGroupName::TAG));

			// specifies the server-unique identifier of the folder
			$out->addVar('ServerId', $gid);

			// specifies the type of the folder
			$typ = $doc->getVar(fldAttribute::TAG);
			$out->addVar('Type', self::MBOXES[isset(self::MBOXES[$typ]) ? $typ : fldAttribute::MBOX_USER]);

			// specifies the server ID of the folder's parent folder. A parent ID of 0 (zero) is the mailbox root folder
			$g = $doc->getVar('Group');
			$out->addVar('ParentId', $g ? $g : '0');

			$out->restorePos($op);
		}
		$unused; // disable Eclipse warning

		$out->getVar('Folders');
		Msg::InfoMsg($out, '<GetHierarchy> output');

		return true;
	}

}
