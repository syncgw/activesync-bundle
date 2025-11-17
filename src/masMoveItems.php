<?php
declare(strict_types=1);

/*
 * 	<MoveItems> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Util;
use syncgw\lib\XML;

class masMoveItems {

	// status codes
	const SRC			 = '1';
	const DEST			 = '2';
	const OK			 = '3';
	const SAME			 = '4';
	const DSTID 		 = '5';
	const LOCK			 = '7';

	// status description
	const STAT  		 = [
		self::SRC		 => 'Invalid source collection ID or invalid source Item ID',
		self::DEST		 => 'Invalid destination collection ID',
		self::SAME		 => 'Source and destination collection IDs are the same',
		self::DSTID		 => 'One of the following failures occurred: the item cannot be moved to more than one item at a time, '.
						    'or the source or destination item was locked',
		self::LOCK		 => 'Source or destination item was locked',
	];

    /**
     * 	Singleton instance of object
     * 	@var masMoveItems
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masMoveItems {

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
					 'MoveItems'));
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

		Msg::InfoMsg($in, '<MoveItems> input');

		// <MoveItems> moves an item or items from one folder on the server to another
		$out->addVar('MoveItems', null, false, $out->setCP(XML::AS_MOVE));

		$db = DB::getInstance();

		// contains elements that describe details of the items to be moved
		$in->xpath('//Move/.');
		while ($in->getItem() !== null) {

			$ip = $in->savePos();

			// specifies the server ID of the item to be moved
			$gid = $in->getVar('SrcMsgId', false);

			// <SrcFldId> specifies the server ID of the source folder (that is, the folder that contains the items to be moved)
			// -> we don't need this information

			// specifies the server ID of the destination folder (that is, the folder to which the items are moved)
			$in->restorePos($ip);
			$dest = $in->getVar('DstFldId', false);

			$op = $out->savePos();
			$out->addVar('Response');
			$out->addVar('SrcMsgId', $gid);
			$out->updVar('Status', self::OK, false);

			// compile handler based on <GUID>
			if (!($hid = array_search(substr($gid, 0, 1), Util::HID(Util::HID_PREF, DataStore::ALL)))) {

				$out->restorePos($op);
				$out->addVar('Status', self::SRC, false);
				$out->restorePos($op);
				continue;
			}

			// load orginal record
			// we don't need to take care about group - GUID is unique
			if (!($doc = $db->Query($hid, DataStore::RGID, $gid))) {

				$out->restorePos($op);
				$out->updVar('Status', self::SRC, false);
				$out->restorePos($op);
				continue;
			}

			// "create" new record
			$doc->updVar('GUID', $id = $db->mkGUID($hid, true));

			// we assume destination group exists (not verified)
			$doc->updVar('Group', $dest);

			// reset status (check <Ping> status)
			$db->setSyncStat($hid, $doc, DataStore::STAT_OK, true);

			// specifies the new server ID of the item after the item is moved to the destination folder
			$out->addVar('DstMsgId', $id);

			// delete moved record
			$db->Query($hid, DataStore::DEL, $gid);

			$out->restorePos($op);
		}

		$out->getVar('MoveItems');
		Msg::InfoMsg($out, '<MoveItems> output');

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
