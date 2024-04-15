<?php
declare(strict_types=1);

/*
 * 	<GetItemEstimate> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\document\field\fldConversationId;
use syncgw\document\field\fldStartTime;
use syncgw\document\field\fldStatus;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;

class masGetItemEstimate {

	// status codes
	const CID			 = '2';
	const PRIME 		 = '3';
	const KEY			 = '4';
	// status description
	const STAT  		 = [
		self::CID		 => 'A collection was invalid or one of the specified collection IDs was invalid',
		self::PRIME		 => 'The synchronization state has not been primed',
		self::KEY		 => 'The specified synchronization key was invalid',
	];

    /**
     * 	Singleton instance of object
     * 	@var masGetItemEstimate
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masGetItemEstimate {

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
					 'GetItemEstimate'));
		$xml->addVar('Stat', 'v27.0');
	}

	/**
	 * 	Parse XML node
	 *
	 *	The GetItemEstimate command gets an estimate of the number of items in a collection or folder on
	 *	the server that have to be synchronized.
	 *
	 * 	@param	- Input document
	 * 	@param	- Output document
	 * 	@return	- true = Ok; false = Error
	 */
	public function Parse(XML &$in, XML &$out): bool {

		Msg::InfoMsg($in, '<GetItemEstimate> input');

		// gets an estimate of the number of items in a collection or folder on the server that have to be synchronized
		$out->addVar('GetItemEstimate', null, false, $out->setCP(XML::AS_ESTIMATE));
		$out->addVar('Status', masStatus::OK);

		// <Collections> - not used

		// contains elements that describe estimated changes
		$out->addVar('Response');
		$op = $out->savePos();
		$out->addVar('Status', masStatus::OK);

		$usr = User::getInstance();
		$mas = masHandler::getInstance();
		$db  = DB::getInstance();

		// contains elements that apply to a particular collection
		$in->xpath('//Collection/.', false);
		while ($in->getItem() !== null) {

			$ip = $in->savePos();

			// contains elements that apply to a particular collection
			$out->addVar('Collection');

			// specifies the server ID of the collection from which the item estimate is being obtained this is the group...
			$gid = $in->getVar('CollectionId', false);

			// @opt <CollectionId> value "RI" (recipient information cache)
			if ($gid == 'RI')
				Msg::ErrMsg('<CollectionId> "RI" not supported');

			// <Options> control certain aspects of how the synchronization is performed
			$in->restorePos($ip);
			$mas->loadOptions('GetItemEstimate', $in);

			// compile handler ID from $gid
			if (!($hid = array_search(substr($gid, 0, 1), Util::HID(Util::HID_PREF, DataStore::ALL, true)))) {
				Msg::ErrMsg('Cannot compile class name from "'.$gid.'"');
				$opts = $mas->getOption($gid);
			} else
				$opts = $mas->getOption(strval($hid));

			// <SyncKey> will not be updated!
			$in->restorePos($ip);
			if ($usr->syncKey($gid) != $in->getVar('SyncKey', false)) {
				$out->restorePos($op);
				$out->updVar('Status', self::KEY, false);
				break;
			}

			$out->addVar('CollectionId', $gid);

			// specifies whether to include items that are included within the conversation modality
			// 0/1 whether to include conversations
			$in->restorePos($ip);
			if ($cmod = $in->getVar('ConversationMode', false)) {

				// only allowed for MailBoxes
				if (!($hid & DataStore::MAIL) && !is_numeric($cmod)) {

					if ($mas->callParm('BinVer') < 14) {
						$mas->setHTTP(400);
						return false;
					}
					$out->restorePos($op);
					$out->updVar('Status', masStatus::XML, false);
					break;
				}
			}

			// max. # of items to return
			$max = $opts['MaxItems'];

			// estimated number of records
			$cnt = 0;

			// no conversation id identified
			$cid = '';

			if ($hid & DataStore::TASK)
				$df = $opts['FilterType'] == -1 ? 'Incomplete task' : 'All task';
			else $df = gmdate('D Y-m-d G:i:s', time() - $opts['FilterType']);
				Msg::InfoMsg('Read modified records in group "'.$gid.
										'" with filter "'.$df.'" in "'.Util::HID(Util::HID_CNAME, $hid).'"');

			// load all records in group
			foreach ($db->Query($hid, DataStore::RIDS, $gid) as $id => $typ) {

				// don't exeed limit
				if ($max && ++$cnt == $max)
					break;

				// we do not count groups
				if ($typ & DataStore::TYP_GROUP)
					continue;

				// get record
				if (!($doc = $db->Query($hid, DataStore::RID, $id)))
					continue;

				// we do not care about records which were ok
				if ($doc->getVar('SyncStat') == DataStore::STAT_OK) {
					$cnt--;
					continue;
				}

				// check for filter
				if ($opts['FilterType']) {
					if ($hid & DataStore::TASK) {
						if ($doc->getVar(fldStatus::TAG) == 'COMPLETED') {
							$cnt--;
							continue;
						}
					} elseif ($hid & (DataStore::CALENDAR|DataStore::MAIL)) {
						if ($doc->getVar(fldStartTime::TAG) <= time() - $opts['FilterType']) {
							$cnt--;
							continue;
						}
					}
				}

				// check <ConversationId>
				// Setting the <ConversationMode> element to 0 (false) in a GetItemEstimate request results in an Estimate element
				// value that only includes items that meet the <FilterType> element value.
				// Setting the value to 1 (true) expands the result set to also include items with identical <ConversationId> element
				// in the <FilterType> result set.
				if ($cmod) {
					if (!$cid)
						$cid = $doc->getVar(fldConversationId::TAG);
					elseif ($doc->getVar(fldConversationId::TAG) != $cid) {
						$cnt--;
						continue;
					}
				}
			}

			// specifies the estimated number of items in the collection or folder that have to be synchronized
			$out->addVar('Estimate', strval($cnt));
			$out->restorePos($op);
		}

		$out->getVar('GetItemEstimate');
		Msg::InfoMsg($out, '<GetItemEstimate> output');

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
