<?php
declare(strict_types=1);

/*
 * 	<Find> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Attachment;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\Msg;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\document\field\fldAlias;
use syncgw\document\field\fldAttribute;
use syncgw\document\field\fldBusinessPhone;
use syncgw\document\field\fldCompany;
use syncgw\document\field\fldFirstName;
use syncgw\document\field\fldFullName;
use syncgw\document\field\fldHomePhone;
use syncgw\document\field\fldLastName;
use syncgw\document\field\fldMailHome;
use syncgw\document\field\fldMobilePhone;
use syncgw\document\field\fldOffice;
use syncgw\document\field\fldPhoto;
use syncgw\document\field\fldTitle;

class masFind {

	// status codes
	const REQUEST	= '2';
	const FSYNC 	= '3';
	const RANGE 	= '4';
	// status description
	const STAT      = [
			self::REQUEST		=>  'The client\'s search failed to validate.',
			self::FSYNC			=>  'The folder hierarchy is out of date.',
			self::RANGE			=>  'The requested range does not begin with 0.',
	];

    /**
     * 	Singleton instance of object
     * 	@var masFind
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masFind {

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
					 'target="_blank">[MS-ASCMD]</a> '.sprintf('Exchange ActiveSync &lt;%s&gt; handler', 'Find'));
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

	    Msg::InfoMsg($in, '<Find> input');

	    $mas = masHandler::getInstance();
		$grp = null;
		$rc  = masStatus::OK;
	    $db  = DB::getInstance();

	    // <Find> construct property restriction based searches for entries in a mailbox
		$out->addVar('Find', null, false, $out->setCP(XML::AS_FIND));

		// <Options>
		$mas->loadOptions('Find', $in);
		$opts = $mas->getOption();

		// <SearchId> as a unique identifier for that search
		// <ExecuteSearch> contains the Find command request parameters

	 	// <GALSearchCriterion> contains the criterion for a GAL search
		if ($in->getVar('GALSearchCriterion') !== null) {

			$ip = $in->savePos();

			// <Query> specifies the predicates used to match entries
			$in->restorePos($ip);
			$qry = $in->getVar('Query');

			$hid = DataStore::CONTACT;

			// try to find GAL
			foreach ($db->getRIDS(DataStore::CONTACT) as $gid => $typ) {

				if ($typ == DataStore::TYP_GROUP) {

					$xml = $db->Query(DataStore::CONTACT, DataStore::RGID, $gid);

					if ($xml->getVar(fldAttribute::TAG) & fldAttribute::GAL) {

						$grp = $xml->getVar('GUID');

						// be sure to synchronize GAL
						$ds  = Util::HID(Util::HID_CNAME, DataStore::CONTACT);
				        $ds  = $ds::getInstance();
						$ds->syncDS($grp, true);
						break;
					}
				}
			}
		} else {

			// <MailBoxSearchCriterion>  contains the criterion for a mailbox search

			$ip = $in->savePos();

			// <Query> specifies the predicates used to match entries
			// <DeepTraversal> indicates that the client wants the server to search all subfolders
			// for the folder that is specified in the query (is an empty tag element)
			$in->restorePos($ip);
			$qry = $in->getVar('Query', false);

			$hid = DataStore::CONTACT;

			// <FreeText> specifies a Keyword Query Language (KQL) string value that defines the search criteria
			// <Class> specifies the class of items retrieved by the search					X
			// <CollectionId> specifies the folder in which to search
			if ($val = $in->getVar('CollectionId'))
				$grp = $val;

			// <Options> contains the search options
			// <Range> specifies the maximum number of matching entries to return
			list($low, $high) = explode('-', $opts['Range']);

			$rc = self::REQUEST;
		}

		$gids = [];
		foreach ($db->getRIDS($hid, $grp, boolval($opts['DeepTraversal'])) as $gid => $typ) {

			if ($typ != DataStore::TYP_DATA)
				continue;

			$xml = $db->Query($hid, DataStore::RGID, $gid);
			$xml->getVar('Data');
			if (stripos($xml->saveXML(false), $qry))
				$gids[] = [ $gid => $xml ];
		}
		if (!count($gids))
			$rc = self::REQUEST;

		// set status
		$out->addVar('Status', $rc);

		if ($rc != masStatus::OK) {
			$out->getVar('Find');
			Msg::InfoMsg($out, '<Find> output');
			return false;
		}

		// <Response> contains the search results that are returned from the server
		$out->addVar('Response');
		// everything is ok
		$out->addVar('Status', $rc);

		$op  = $out->savePos();
		$att = Attachment::getInstance();

		list($start, $end) = explode('-', $opts['Range']);
		$cnt = 0;

		foreach ($gids as $gid => $xml) {

			// check range
			if ($cnt++ < $start)
				continue;

			if ($cnt > $end)
				break;

			$out->restorePos($op);

			// <Result> serves a container for an individual matching mailbox items
			$out->addVar('Result');

			// <airsync:Class> specifies the class of items retrieved by the search
			$out->addVar('Class', Util::HID(Util::HID_ENAME, $hid), false, $out->setCP(XML::AS_AIR));

			// <airsync:ServerId>
			$out->addVar('ServerId', $gid);

			// <airsync:CollectionId> specifies the folder in which the item was found
			$out->addVar('CollectionId', $grp);

			// <Properties> contains the properties that are returned for an item in the response.
			$out->addVar('Properties', null, false, $out->setCP(XML::AS_FIND));

			if ($hid & DataStore::CONTACT) {

				// <gal:DisplayName> contains the display name of a recipient in the GAL
				if (($val = $xml->getVar(fldFullName::TAG, false)))
					$out->addVar('DisplayName', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:Phone> contains the phone number of a recipient in the GAL
				if (($val = $xml->getVar(fldBusinessPhone::TAG, false)))
					$out->addVar('Phone', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:Office> contains the office location or number of a recipient in the GAL
				if (($val = $xml->getVar(fldOffice::TAG, false)))
					$out->addVar('Office', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:Title> contains the title of a recipient in the GAL
				if (($val = $xml->getVar(fldTitle::TAG, false)))
					$out->addVar('Title', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:Company> contains the company of a recipient in the GAL that matched the search criteria
				if (($val = $xml->getVar(fldCompany::TAG, false)))
					$out->addVar('Company', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:Alias> contains the alias of a recipient in the GAL
				if (($val = $xml->getVar(fldAlias::TAG, false)))
					$out->addVar('Alias', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:FirstName> contains the first name of a recipient in the GAL
				if (($val = $xml->getVar(fldFirstName::TAG, false)))
					$out->addVar('FirstName', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:LastName> contains the last name of a recipient in the GAL
				if (($val = $xml->getVar(fldLastName::TAG, false)))
					$out->addVar('LastName', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:HomePhone> contains the home phone number of a recipient in the GAL
				if (($val = $xml->getVar(fldHomePhone::TAG, false)))
					$out->addVar('HomePhone', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:MobilePhone> contains the mobile phone number of a recipient in the GAL
				if (($val = $xml->getVar(fldMobilePhone::TAG, false)))
					$out->addVar('MobilePhone', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:EmailAddress> contains the email address of a recipient in the GAL
				if (($val = $xml->getVar(fldMailHome::TAG, false)))
					$out->addVar('EmailAddress', $val, false, $out->setCP(XML::AS_GAL));

				// <gal:Picture> contains the properties that are returned for an item in the response.
				if (($val = $xml->getVar(fldPhoto::TAG, false))) {
					$out->addVar('Picture', null, false, $out->setCP(XML::AS_GAL));
					$val = $att->read($val);
					// <Status>
					if (--$opts['MaxPictures'] <= 0)
						$out->addVar('Status', masStatus::PICLIMIT);
					elseif (strlen($val) > $opts['MaxSize'])
						$out->addVar('Status', masStatus::PICSIZE);
					else {
						$out->addVar('Status', masStatus::OK);
						// <gal:Data> contains the binary data of the contact photo
						$out->addVar('Data', base64_encode($val));
					}
				}
			} else {

				// @todo <Range> specifies the range of bytes that the client can receive in response to the fetch operation
				// 		 Msg::WarnMsg('+++ <Find><Option><Range> not supported');

				// <email:Subject>
				// <email:DateReceived>
				// <email:DisplayTo>
				// <DisplayCc> specifies the list of secondary recipients of a message as displayed to the user
				// <DisplayBcc> specifies the blind carbon copy (Bcc) recipients of an email as displayed to the user
				// <email:Importance>
				// <email:Read>
				// <email2:IsDraft>
				// <Preview> contains an up to 255-character preview of the Email Text Body to be displayed in the list of search results
				// <HasAttachments> specifies whether or not a message contains attachments.
				// <email:From>
			}
		}

		$low; $high; $qry; // disable Eclipse warning

		$out->getVar('Find');
		Msg::InfoMsg($out, '<Find> output');

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
