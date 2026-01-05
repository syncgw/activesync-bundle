<?php
declare(strict_types=1);

/*
 *  <ItemOperations> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Config;
use syncgw\lib\Msg;
use syncgw\lib\Attachment;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\HTTP;
use syncgw\lib\Session;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\document\field\fldConversationId;

class masItemOperations {

	// status codes
	const OK		 = '1';
	const PROT		 = '2';
	const SERVER	 = '3';
	const URI		 = '4';
	const ACCESS	 = '5';
	const NOTFOUND	 = '6';
	const CONNECT	 = '7';
	const BYTERANGE	 = '8';
	const STORE		 = '9';
	const FILE		 = '10';
	const SIZE		 = '11';
	const IO		 = '12';
	const CONVERSION = '14';
	const ATTACHMENT = '15';
	const RESOURCE	 = '16';
	const PARTIAL	 = '17';
	const CRED		 = '18';
	// status description
	const STAT       = [
		self::OK     		=>  'Success',
		self::PROT			=>	'Protocol error - protocol violation/XML validation error',
		self::SERVER		=> 	'Server error',
		self::URI			=> 	'Document library access - The specified URI is bad',
		self::ACCESS		=>	'Document library - Access denied',
		self::NOTFOUND		=>	'Document library - The object was not found or access denied',
		self::CONNECT		=>	'Document library - Failed to connect to the server',
		self::BYTERANGE		=>	'The byte-range is invalid or too large',
		self::STORE			=>	'The store is unknown or unsupported',
		self::FILE			=> 	'The file is empty',
		self::SIZE			=>	'The requested data size is too large',
		self::IO			=>	'Failed to download file because of input/output (I/O) failure',
		self::CONVERSION	=>	'Mailbox fetch provider - The item failed conversion',
		self::ATTACHMENT	=>	'Attachment fetch provider - Attachment or attachment ID is invalid',
		self::RESOURCE		=>	'Access to the resource is denied',
		self::PARTIAL		=>	'Partial success; the command completed partially',
		self::CRED			=>	'Credentials required',
	];

    /**
     * 	Singleton instance of object
     * 	@var masItemOperations
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masItemOperations {

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
					 'ItemOperations'));
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

		$db   = DB::getInstance();
		$att  = Attachment::getInstance();
		$mas  = masHandler::getInstance();
		$ver  = $mas->callParm('BinVer');
		$hdlr = array_flip(Util::HID(Util::HID_PREF));

		Msg::InfoMsg($in, '<ItemOperations> input');

		// load options
		$mas->loadOptions('ItemOperations', $in);

		// @todo <Options><Schema> The schema of the item to be fetched (e-mail only)
		// 		 Msg::WarnMsg('+++ <ItemOperations><Option><Schema> not supported');
		// @todo <Options><Schema><Location>
		// @todo <Options><Schema><TopLevelSchemaProps>
		// @todo <Options><UserName> The user name leveraged to fetch the desired item in <ItemOperations>
	   	// @todo <Options><Password> The password for the <User> in <ItemOperations>
   		// @todo <Options><MIMESupport> Enables MIME support for email items that are sent from the server to the client
	  	// 		 Msg::WarnMsg('+++ <ItemOperations><Option><MIMESupport> not supported');
	  	// @todo <Options><RightsManagementSupport> How the server returns rights-managed email messages to the client
		// 		 Msg::WarnMsg('+++ <ItemOperations><Option><RightsManagementSupport> not supported');

        // provide batched online handling of <Fetch> and <Move> operations against the server
		$out->addVar('ItemOperations', null, false, $out->setCP(XML::AS_ITEM));
		if (($in->xpath('//Move/.') && $ver >= 14.0) || ($in->xpath('//Fetch/.') && $ver > 2.5))
			$out->addVar('Status', $rc = self::OK);
		$out->addVar('Response');
		$rp = $out->savePos();

		// ------------------------------------------------------------------------------------------------------------------------------------------

		// <EmptyFolderContents> identifies the body of the request or response as containing the operation that deletes
		// the contents of a folder
		if ($in->xpath('//EmptyFolderContents/.')) {

    		while ($in->getItem() !== null) {

    			$ip = $in->savePos();
    			$op = $out->savePos();

    			$out->addVar('EmptyFolderContents');

    			// required
    			$gid = $in->getVar('CollectionId', false);
    			$out->addVar('CollectionId', $gid, false, $out->setCP(XML::AS_AIR));
				$opts = $mas->getOption($gid);

    			// get handler ID
    			if (!isset($hdlr[substr($gid, 0, 1)])) {

    				Msg::WarnMsg('Handler ID for "'.$gid.'" not found');
    				$out->addVar('Status', self::STORE, false, $out->setCP(XML::AS_ITEM));
    				continue;
    			} else
					$hid = $hdlr[substr($gid, 0, 1)];

				$rc = self::OK;

				$in->restorePos($ip);
    			foreach ($db->getRIDS($hid, $gid, boolval($opts['DeleteSubFolders'])) as $id => $unused) {

        			if (!$db->Query($hid, DataStore::DEL, $id)) {

        				$rc = self::IO;
    	       			break;
        			}
    			}
				$unused; // disable Eclipse warning

    			$out->addVar('Status', $rc, false, $out->setCP(XML::AS_ITEM));
				$in->restorePos($ip);
    			$out->restorePos($op);
    		}
		}

		// ------------------------------------------------------------------------------------------------------------------------------------------

		$sess = Session::getInstance();

		//  retrieves an item from the server
		if ($in->xpath('//Fetch/.')) {

			$opts = $mas->getOption(strval(strcasecmp($in->getVar('Store'), 'mailbox') !== null ? DataStore::MAIL : DataStore::DOCLIB));

			// get allowed range to send
			list($start, $end) = explode('-', $opts['Range']);

			$http = HTTP::getInstance();

			// login with different credentials
			// The <UserName> element is an optional child element of the <Options> element in <ItemOperations> command requests
			// that specifies the username of the account leveraged to fetch the desired item.
			if ($n = $opts['UserName']) {

				$usr  = User::getInstance();
				$http->updHTTPVar(HTTP::RCV_HEAD, 'User', $n);
				$http->updHTTPVar(HTTP::RCV_HEAD, 'Password', $opts['Password']);
				if (!$usr->Login($n, $opts['Password'], $mas->callParm('DeviceId'))) {

					$http->addHeader('WWW-Authenticate', 'Basic realm=masItemOperations');
					$mas->setHTTP(401);
					return false;
				}
			}

    		while ($in->getItem() !== null) {

    			$ip = $in->savePos();

    			$out->restorePos($rp);
    			$out->addVar('Fetch', null, false, $out->setCP(XML::AS_ITEM));
    			$out->addVar('Status', $rc = self::OK);

    			// @opt <Store> Either "DocumentLibrary" or "Mailbox" for items and attachments

    			// @opt <documentlibrary:LinkId> Uniform Resource Identifier (URI) that is assigned by the server
    			// to certain resources, such as Windows SharePoint Services or UNC documents

    	        // first we check for attachments

   	        	// specifies a unique identifier that is assigned by the server to each attachment to a given item
   	        	// $ip = $in->savePos();
   	        	if ($fid = $in->getVar('FileReference', false)) {

		            $val = $att->read($fid);

		            if (!($len = $att->getVar('Size'))) {

                		$rc = self::NOTFOUND;
                        break;
					}

       				$out->addVar('FileReference', $fid, false, $out->setCP(XML::AS_BASE));
		     		$op = $out->savePos();

		     		$out->addVar('Properties', null, false, $out->setCP(XML::AS_ITEM));
	    	        $out->addVar('Total', $len);
		   	        // indicates the time at which a document item was last modified
					$out->addVar('Version', gmdate(Config::masTIME, intval($att->getVar('Created'))));

					// length of data to send - we got a <Range>
	    	        if (($pos = $end) != '0') {

			   	    	$pos = $start;
						$len = $end - $start + 1;
	    	        } else
	    	        	$end = $len;

	    	        // does it fit?
	    	       	if ($len > $end)
	  			    	$len = $end;

					// 2.2.3.130 Part
					// The Part element is an optional child element of the Properties element or the airsyncbase:Body
					// element in ItemOperations command responses that specifies an integer index into the metadata of
					// the multipart response.
  			    	if ($http->getHTTPVar('MS-ASAcceptMultiPart') == 'T') {

						$sess = Session::getInstance();
	    	       		if (!$sess->xpath('//Data/ItemPart[text()="'.$fid.'"]')) {

	    	       			$sess->getVar('Data');
	    	       			$sess->addVar('ItemPart', $fid, false, [ 'NO' => $no = '1' ]);
	    	       		} else {
							$sess->getItem();
	    	       			$no = $sess->getAttr('NO') + 1;
	    	       		}
	    	       		$sess->setAttr([ 'NO' => strval($no) ]);

		     	       	// optional child element of the Properties element or the <airsyncbase:Body> element in <ItemOperations>
	  				    // command responses that specifies an integer index into the metadata of the multipart response
	  				    $out->addVar('Part', strval($no));
  			    	}

	    	       	if ($start != 0 &&  $end != 9999999)
		    	       	$out->addVar('Range', strval($start).'-'.strval($end));

			   		$out->addVar('Data', base64_encode(substr($val, intval($pos), intval($len))));
   	        	} else {

		    		// handler id unknown
	    			$hid = 0;

   	        		// <LongId> specifies a unique identifier that was assigned by the server to each result returned
   	        		// by a previous <Search> response
					// [ 'Id' => '' ][ 'Group' => '' ][ 'GUID' => '' ]
					$ip = $in->savePos();
					if ($val = $in->getVar('LongId', false)) {

   	        			if ($val = $mas->SearchId(masHandler::LOAD, $hid, $val)) {
   	        				$id = $val['Id']; $grp = $val['Group']; $gid = $val['GUID'];
   		        		}
					} else {

						$id = 0;

				       	// get document ID
			    	   	$in->restorePos($ip);
				       	$gid = $in->getVar('ServerId', false);

	   	    	    	// get group
				       	$in->restorePos($ip);
				       	$grp = $in->getVar('CollectionId', false);

		   				if (!$gid) {

	    					Msg::WarnMsg('+++ No <ServerId> available');
	    					$rc = self::STORE;
	    					break;
	   			   		}

	    			}

		    		// get handler ID
	    			if (!$gid && !isset($hdlr[substr($gid, 0, 1)])) {

	    				Msg::WarnMsg('Handler ID for "'.$gid.'" not found');
	    				$rc = self::STORE;
	    				break;
	    			}
  	        		$hid = $hdlr[substr($gid, 0, 1)];

		   	    	// @opt <RemoveRightsManagementProtection> indicates that the client is removing the information

		       		if ($id)
		 				$out->addVar('LongId', $id, false, $out->setCP(XML::AS_AIR));

	 				if ($grp)
			       		$out->addVar('CollectionId', $grp, false, $out->setCP(XML::AS_AIR));

		 			if ($gid)
			       		$out->addVar('ServerId', $gid, false, $out->setCP(XML::AS_AIR));

	      			$out->addVar('Class', Util::HID(Util::HID_TAB, $hid), false, $out->setCP(XML::AS_AIR));

	     			$op = $out->savePos();
    	            $out->addVar('Properties', null, false, $out->setCP(XML::AS_ITEM));

	      			// get data store handler
  					$ds = Util::HID(Util::HID_CNAME, $hid);
	   				$ds = $ds::getInstance();

	       			// export all fields
	       			$xml = new XML();
	       			$doc = $db->Query($hid, DataStore::RGID, $gid);
					$ds->export($xml, $doc, $mas->MIME[$hid]);

					// @opt <airSyncBase:ContentType> - specifies the type of data returned

					// swap properties
	                $xml->getChild('ApplicationData');
	                while ($xml->getItem() !== null)
	                	$out->append($xml, false, false);

	           		// @opt <documentlibrary:AllProps> Rights Management
				    // @opt <RightsManagementLicense> Rights Management
				    // contains the rights policy template settings for the template applied to the e-mail message being synchronized
           			if ($ver >= 14.1) {

						$set = masSettings::getInstance();
			    		if ($set->getVar('DisableRightsManagementTemplates') === null) {

			    			$p = $out->savePos();
			   				if ($set->xpath('//RightsManagementLicense/.')) {

		    	   				$out->addVar('RightsManagementLicense', null, false, $out->setCP(XML::AS_RIGTHM));
		       					while($set->getItem() !== null)
		       						$out->append($set, false);
		       				}
			    			$out->restorePos($p);
			    		}
					}
	    			$in->restorePos($ip);
	    			$out->restorePos($op);
				}
    		}

 			if ($rc != self::OK)
				$out->updVar('Status', $rc);

			// we do not need to re-login, because session will end here
		}

		// ------------------------------------------------------------------------------------------------------------------------------------------

		// identifies the body of the request or response as containing the operation that moves a given conversation
		if ($in->xpath('//Move/.')) {

			$in->restorePos($ip);
			$out->savePos();

			// specifies the conversation to be moved
			if ($cid = $in->getVar('ConversationId', false))
    		   	$out->addVar('ConversationId', $cid, false, $out->setCP(XML::AS_AIR));
   		   	$opts = $mas->getOption(strval($cid));

    		// @opt strlen('ConversationId') > 22 - commented out
    		// if (strlen($cid) > 22)
    		//		$cid = substr($cid, 0, 22);

   	        // specifies the server ID of the destination folder (that is, the folder to which the items are moved)
		    $in->restorePos($ip);
			$gid = $in->getVar('DstFldId', false);

        	// external record mapping table
        	$map = [];

        	// scan through all mail records
        	$gids = $db->getRIDS(DataStore::MAIL);
    		foreach ($gids as $id => $typ) {

    			// check only groups
				if ($typ & DataStore::TYP_DATA)
					continue;

    			// load record
				if (!($doc = $db->Query(DataStore::MAIL, DataStore::RGID, $id)))
					continue;

	        	// build external group mapping table
  				$map[$id] = $doc->getVar('extID');
   			}

    		foreach ($gids as $id => $typ) {

   				// check only data records
				if ($typ & DataStore::TYP_GROUP)
					continue;

    			// get conversation id
 				if (!($val = $doc->getVar(fldConversationId::TAG)))
					continue;

  	  			// @opt strlen('ConversationId') > 22 - commented out
    			// if (strlen($cid) > 22)
    			//		$cid = substr($cid, 0, 22);

    			// check conversation id / did we already move?
    			$doc->getVar('Data');
    			if (strcmp($cid, $val) || $doc->getVar('Group') == $gid)
    				continue;

	    		// move always?
		   		// Whether to <Move> the specified conversation, including all future emails in the conversation,
				// to the folder specified by the <DstFldId> element (Destination <GUID>)
				$in->restorePos($op);
				if ($opts['MoveAlways']) {

					// update?
					$usr = User::getInstance();
					$usr->xpath('//MoveAlways/CID');
					while (($v = $usr->getItem()) && $v != $cid)
						;
					if ($v)
						$usr->setAttr([ 'Int' => $gid, 'Ext' => $map[$gid] ]);
					else {

						$usr->getVar('MoveAlways');
						$usr->addVar('CID', $cid, false, [ 'Int' => $gid, 'Ext' => $map[$gid] ]);
    				}
    			}

	        	// delete existing record
	        	$db->Query(DataStore::MAIL, DataStore::DEL, $id);

	        	// add new record
	        	$doc->updVar('Group', $gid);
	        	$doc->updVar('extGroup', $map[$gid]);
	        	$db->Query(DataStore::MAIL, DataStore::ADD, $doc);
        	}

			$in->restorePos($ip);
    		$out->restorePos($op);
		}

		$out->getVar('ItemOperations');
		Msg::InfoMsg($out, '<ItemOperations> output');

		// 2.2.1.10.1.1 MultiPartResponse were handled by masHTTP::checkOut();

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
