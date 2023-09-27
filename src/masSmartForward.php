<?php
declare(strict_types=1);

/*
 * 	<SmartForward> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Msg;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\HTTP;
use syncgw\lib\User;
use syncgw\lib\XML;
use syncgw\document\field\fldAttribute;
use syncgw\document\field\fldMailTo;
use syncgw\document\field\fldBody;
use syncgw\document\field\fldConversationId;

class masSmartForward {

   /**
     * 	Singleton instance of object
     * 	@var masSmartForward
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masSmartForward {

	   	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Opt', '<a href="https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-ascmd" target="_blank">[MS-ASCMD]</a> '.
				      sprintf('Exchange ActiveSync &lt;%s&gt; handler'), 'SmartForward');
	}

	/**
	 * 	Parse XML node
	 *
	 * 	@param	- Input document
	 * 	@param	- Output document
	 * 	@return	- true = Ok; false = Error
	 */
	public function Parse(XML &$in, XML &$out): bool {

		Msg::InfoMsg($in, '<SmartForward> input');

		// is used by clients to forward messages without retrieving the full, original message from the server
		// The <SmartForward> command is similar to the <SendMail> command (section 2.2.1.17), except that
		// the outgoing message identifies the item being forwarded to and includes the text of the new
		// message. The full text of the original message is retrieved and sent by the server.

		$out->addVar('SmartForward', null, false, $out->setCP(XML::AS_COMPOSE));

		$db  = DB::getInstance();
		$mas = masHandler::getInstance();
		$fld = fldBody::getInstance();

		// return status
		$rc = 0;

		// it specifies the client's unique message ID (MID)
		$mid = $in->getVar('ClientId');

		// <Source> contains information about the source message

		// specifies the folder ID for the source message
		// $grp = $in->getVar('FolderId');

		// specifies the item ID for the source message, which is returned in the <Sync> command response message
		$gid = $in->getVar('ItemId');

		// specifies the long ID for the source message, which is returned in the <Search> command response message
		if ($val = $in->getVar('LongId')) {

	    	$gid = $mas->SearchId(masHandler::LOAD, DataStore::MAIL, $val);
	    	$gid = array_pop($gid);
	    	$gid = $gid['GUID'];
	    	// $grp = $gid['Group'];
		}

		// @todo <InstanceId> - Recurrence
		// specifies the instance of a recurrence for the source item. For example, 2010-03-20T22:40:00.000Z

		// optional child element of command requests. it identifies the account from which an email is sent
		if ($id = $in->getVar('AccountId')) {

			if (!($usr = $db->Query(DataStore::USER, DataStore::RGID, $id)))
				$rc = masStatus::ACCID;
		} else
			$usr = User::getInstance();

		if (!$rc) {

			// does account support sending mails?
			if ($usr->getVar('SendDisabled'))
				$rc = masStatus::ACCSEND;

			// get password for user
			else
				$upw = HTTP::getinstance()->getHTTPVar('Password');

			$uid = $usr->getVar('EMailPrime');
		}

		// optional child element
		// specifies whether a copy of the message will be stored in the Sent Items folder
		$save = $in->getVar('SaveInSentItems') !== null;
		if ($mas->callParm('Options') == 'SaveInSent')
			$save = true;

		// it specifies whether the client is sending the entire message.
		// If the element is present, the message was edited
		$cmd = $in->getVar('ReplaceMime') !== null ? DataStore::UPD : DataStore::ADD;

		// <airsyncbase:Body>
		$in->getVar('ApplicationData');
		$bdy = new XML();
	    $fld->import($mas->MIME[DataStore::MAIL][0], 1.0, '', $in, 'Body', $bdy);

		// required child element. it contains the MIME-encoded message
		$doc = $db->cnv2Int($gid, $in->getVar('Mime'));

		if ($v = $bdy->getVar(fldBody::TAG)) {

			$doc->getVar('Data');
			$doc->updVar(fldBody::TAG, $v);
			$doc->setAttr($bdy->getAttr());
		}

		// @todo <TemplateID> - RM - Rights Management
		// contains a string that identifies a particular rights policy template to be applied to the outgoing message

		// add message id
		$doc->getVar('Data');
		$doc->addVar(fldConversationId::TAG, $mid);

		// update ID
		$doc->updVar('GUID', $gid);

		// use different account id?
		if (!$rc && $id) {

			if (!$db->Authorize($uid, $upw))
				$rc = masStatus::ACCID;
		}

		// <Forwardees> specifies the recipients of a forwarded meeting invitation

		// <Forwardee> that specifies a recipient of a forwarded meeting invitation
		// <Email> specifies the email address of the forwardee
		// <Name> specifies the name of the forwardee
		if (!$rc) {

			$in->xpath('Forwardee');
			$doc->getVar('Data');
			while ($in->getItem !== false) {

				$p = $in->savePos();
				$n = $in->getVar('Name', false);
				$in->restorePos($p);
				$e = $in->getVar('Email', false);
				$e = $n ? '"'.$n.'" <'.$e.'>' : $e;
				$doc->addVar(fldMailTo::TAG, $e);
				$in->restorePos($p);
			}

			// save in internal and external data store
			if ($save) {

				$ds = '\\use syncgw\\document\\docMail';
				$ds = $ds::getInstance();
				$id = $ds->getBoxID(fldAttribute::MBOX_SENT);
				$doc->updVar('Group', $id[0]);
				$doc->updVar('extGroup', $id[1]);
				if (!$db->Query(DataStore::EXT|DataStore::MAIL, $cmd, $doc))
					$rc = masStatus::SERVER;
			}

			// send mail
			if (!$db->SendMail(false, $doc)) {

				Msg::InfoMsg('<SmartForward> failed');
				$rc = masStatus::SERVER;
			} else
				$rc = masStatus::SUBMIT;
		}

		$out->addVar('Status', $rc);

		$out->getVar('SmartForward');
		Msg::InfoMsg($out, '<SmartForward> output');

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

		if (isset(masStatus::STAT[$rc]))
			return masStatus::STAT[$rc];

		return 'Unknown return code "'.$rc.'"';
	}

}
