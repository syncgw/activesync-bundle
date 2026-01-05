<?php
declare(strict_types=1);

/*
 * 	<GetAttachment> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Attachment;
use syncgw\lib\HTTP;
use syncgw\lib\XML;

class masGetAttachment {

    /**
     * 	Singleton instance of object
     * 	@var masGetAttachment
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masGetAttachment {

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
					 'GetAttachment'));
		$xml->addVar('Stat', 'v27.0');
	}

	/**
	 * 	Parse XML node
	 *
	 *	The GetAttachment command retrieves an email attachment from the server.
	 *
	 * 	@param	- Input document
	 * 	@param	- Output document
	 * 	@return	- true = Ok; false = Error
	 */
	public function Parse(XML &$in, XML &$out): bool {

		// only for version < 14.0, other version use <ItemOperations><Fetch>

		// get attachment id to load
		$mas = masHandler::getInstance();
		$id  = $mas->callParm('AttachmentName');
		$att = Attachment::getInstance();

		// open and read attachment record
		// we assume $id is attachment record id
		if (!$data = $att->read($id)) {
			$mas->setHTTP(500);
		    return false;
		}

 	    $http = HTTP::getInstance();
		$http->addHeader('Content-Type', $att->getVar('MIME'));
		$http->addBody($data);

		// set status 200 - ok
		$mas->setStat(masHandler::STOP);

		return true;
	}

}
