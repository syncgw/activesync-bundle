<?php
declare(strict_types=1);

/*
 * 	<SendMail> handler class
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
use syncgw\lib\HTTP;
use syncgw\lib\User;
use syncgw\lib\XML;
use syncgw\document\field\fldConversationId;

class masSendMail {

	/**
     * 	Singleton instance of object
     * 	@var masSendMail
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masSendMail {

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
					 'target="_blank">[MS-ASCMD]</a> '.sprintf('Exchange ActiveSync &lt;%s&gt; handler', 'SendMail'));
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

		Msg::InfoMsg($in, '<SendMail> input');

		$out->addVar('SendMail', null, false, $out->setCP(XML::AS_COMPOSE));

		$db  = DB::getInstance();
		$mas = masHandler::getInstance();

		// return status
		$rc = 0;

		// a required child element of the <SendMail> element in SendMail command requests
		// it specifies the client's unique message ID (MID)
		$mid = $in->getVar('ClientId');

		// optional child element of command requests. it identifies the account from which an email is sent
		if ($uid = $in->getVar('AccountId')) {
			// we use <AccountId> as <GUID>
			if (!($usr = $db->Query(DataStore::USER, DataStore::RGID, $uid)))
				$rc = masStatus::ACCID;
		} else
			$usr = User::getInstance();

		// does account support sending mails?
		if ($usr->getVar('SendDisabled'))
			$rc = masStatus::ACCSEND;

		// optional child element. specifies whether a copy of the message will be stored in the Sent Items folder
		$save = $in->getVar('SaveInSentItems') !== null;
		if ($mas->callParm('Options') == 'SaveInSent')
			$save = true;

		// @todo <TemplateID> - RM - Rights Management
		// contains a string that identifies a particular rights policy template to be applied to the outgoing message

		// use different account id?
		if (!$rc && $uid) {
			$http = HTTP::getInstance();
			if (!$db->Authorize($uid, $http->getHTTPVar('Password')))
				$rc = masStatus::ACCID;
		}

		if (!$rc) {

			// required child element. it contains the MIME-encoded message
			if (($doc = $db->SendMail($save, str_replace("\n", "\r\n", $in->getVar('Mime')))) == null)
				$rc = masStatus::SUBMIT;
			else {
				$doc->getVar('Data');
				$doc->addVar(fldConversationId::TAG, $mid);
				if (!$db->Query(DataStore::EXT|DataStore::MAIL, DataStore::ADD, $doc))
					$rc = masStatus::SERVER;
				else {
					// set status 200 - ok
					$mas->setStat(masHandler::STOP);
					return true;
				}
			}
		}
		$out->addVar('Status', $rc);

		$out->getVar('SendMail');
		Msg::InfoMsg($out, '<SendMail> output');

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
