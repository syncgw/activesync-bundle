<?php
declare(strict_types=1);

/*
 * 	ActiveSync handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\activesync\mime\mimAsCalendar;
use syncgw\activesync\mime\mimAsContact;
use syncgw\activesync\mime\mimAsMail;
use syncgw\activesync\mime\mimAsNote;
use syncgw\activesync\mime\mimAsTask;
use syncgw\document\field\fldBody;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\Config;
use syncgw\lib\Device;
use syncgw\lib\HTTP;
use syncgw\lib\Server;
use syncgw\lib\Session;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\lib\Datastore;

class masHandler extends XML {

	// ActiveSync server version
	const MSVER    = '16.1';

    const LOAD     = 0x01;				// load ping status
    const SET 	   = 0x02;				// set and save status
    const DEL      = 0x04;				// delete status

	const STOP 	   = 1;					// stop with HTPP 200
	const EXIT     = 2; 				// exit processing

	// filter times = FilterType
	const FILTER   = [
	    // Val 	Meaning 							Email 	Calendar 	Tasks	Converted seconds
		// 0 	No filter- synchronize all items 	Yes 	Yes 		Yes		0
		// 1 	1 day 								Yes 	No 			No		60*60*24
	    // 2 	3 days 								Yes 	No 			No		60*60*24*3
	    // 3 	1 week 								Yes 	No 			No		60*60*24*7
		// 4 	2 weeks 							Yes 	Yes 		No		60*60*24*7*2
	    // 5 	1 month 							Yes 	Yes 		No		60*60*24*30,5
		// 6 	3 months 							No 		Yes 		No		60*60*24*30,5*3
	    // 7 	6 months 							No 		Yes 		No		60*60*24*30,5*6
	    // 8 	Filter by incomplete tasks 			No 		No 			Yes		-1
		'0' 		=> '0',
	    '1' 		=> '86400',
		'2' 		=> '259200',
		'3' 		=> '604800',
	    '4' 		=> '1209600',
		'5' 		=> '2635200',
	    '6' 		=> '7905600',
	    '7' 		=> '15811200',
		'8' 		=> '-1',
	];

	// size conversion table
	const SIZE 	   = [
		'0' 		=> 0,					// 0 Truncate all body text.
		'1' 		=> 4096,				// 1 Truncate text over 4,096 characters.
		'2' 		=> 5120,				// 2 Truncate text over 5,120 characters.
		'3' 		=> 7168,				// 3 Truncate text over 7,168 characters.
		'4' 		=> 10240,				// 4 Truncate text over 10,240 characters.
		'5' 		=> 20480,				// 5 Truncate text over 20,480 characters.
		'6' 		=> 51200,				// 6 Truncate text over 51,200 characters.
		'7' 		=> 102400,				// 7 Truncate text over 102,400 characters.
		'8' 		=> -1,					// 8 Do not truncate; send complete MIME data.
	];

    /**
     * 	Singleton instance of object
     * 	@var masHandler
     */
    static private $_obj = null;

    /**
     *  MIME conversion table
     *  @var array
     */
    public $MIME = [];

    /**
     * 	ActiveSync options
     *
     * 	@var array
     */
    private $_opts = [];

    /**
	 * 	Call parameter array()
	 * 	@var array
	 */
	private $_parms = [];

	/**
	 *	Processing flag
	 *	@var int
	 */
	private $_stat;

	/**
	 * 	HTTP return code
	 * 	@var integer
	 */
	private $_rc;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance() {

	   	if (!self::$_obj) {

            self::$_obj = new self();

			// set log message codes 16011-16100
			Log::getInstance()->setLogMsg( [
					16011 => 'Error loading [%s]',
			        16012 => 'ActiveSync class \'%s\' not found',
			]);

			if (!self::$_obj->loadFile(Config::getInstance()->getVar(Config::ROOT).
										'activesync-bundle/assets/activesync.xml'))
				Log::getInstance()->logMsg(Log::ERR, 16011, 'activesync.xml');

			foreach (Util::HID(Util::HID_CNAME, DataStore::ALL) as $hid => $unused) {

				if ($hid & DataStore::CALENDAR)
					self::$_obj->MIME[$hid] = mimAsCalendar::MIME[0];
				if ($hid & DataStore::CONTACT)
					self::$_obj->MIME[$hid] = mimAsContact::MIME[0];
				if ($hid & DataStore::TASK)
					self::$_obj->MIME[$hid] = mimAsTask::MIME[0];
				if ($hid & DataStore::NOTE)
					self::$_obj->MIME[$hid] = mimAsNote::MIME[0];
				if ($hid & DataStore::MAIL)
					self::$_obj->MIME[$hid] = mimAsMail::MIME[0];
			}
			$unused; // disable Eclipse warning
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'ActiveSync handler');

		Server::getInstance()->getBundleInfo($xml, 'activesync-bundle/src', 'activesync',
				[ 'mime', 'masStatus', 'masFolderType' ]);
	}

	/**
	 * 	Process client request
	 */
	public function Process(): void {

		$http = HTTP::getInstance();
		$sess = Session::getInstance();
		$cnf  = Config::getInstance();
		$usr  = User::getInstance();
		$log  = Log::getInstance();
		$typ  = strval($http->getHTTPVar('Content-Type'));
		$ext  = $http->getHTTPVar(HTTP::RCV_BODY);

		// create / restart session
		if (!$sess->mkSession()) {

			$http->send(400);
		    return;
		}

		$this->_stat = 0;
		$this->_rc   = 0;

		// load call parameter
		$this->_parms = [ 'DeviceId' => '' ];
		if ($req = $http->getHTTPVar('Request')) {

			if (strpos($req, '=') !== false) {

				$req = substr($req, strpos($req, '?') + 1);
				if ($p = $http->getHTTPVar('SERVER_PROTOCOL'))
					$req = substr($req, 0, strpos($req, $p));
				$p = explode('&', $req);
				foreach ($p as $l) {

					list($c, $p) = explode('=', $l);
					// special hook to catch wrong formatted commands send by Samsung phones
					if ($c == 'Cmd' && substr($p, 0, 1) == '_')
					    $p = substr($p, 1);
					$this->_parms[$c] = urldecode(trim($p));
				}
			}
		}
		$this->_parms['BinVer'] = floatval($http->getHTTPVar('MS-ASProtocolVersion'));
		if (isset($this->_parms['DeviceType']) && $this->_parms['DeviceType'] == 'WinMail')
			$cnf->updVar(Config::HACK, $cnf->getVar(Config::HACK)|Config::HACK_WINMAIL);

		Msg::InfoMsg($this->_parms, 'Call parameter');

		// user in call parameter? so we check if HTTP user is available...
		if (isset($this->_parms['User']) && !$http->getHTTPVar('User')) {

			if ($i = strpos($this->_parms['User'], '\\'))
				$http->updHTTPVar(HTTP::RCV_HEAD, 'User', substr($this->_parms['User'], $i));
			else
				$http->updHTTPVar(HTTP::RCV_HEAD, 'User', $this->_parms['User']);
		}

		// [MS-ASHTTP]: 2.2.3.1.2 The authorization header is required
		if (!$usr->Login($http->getHTTPVar('User'), $http->getHTTPVar('Password'), $this->_parms['DeviceId'])) {

			if ($usr->getVar('Banned'))
				$http->send(456);
			else {

				$http->addHeader('WWW-Authenticate', 'Basic realm=masHANDLER');
				$http->send(401);
			}
			return;
		}

		// check for authorization
		if ($http->getHTTPVar('REQUEST_METHOD') == 'OPTIONS') {

			// client checks what we support
			$http->addHeader('Allow', 'OPTIONS, POST');
			$http->addHeader('Public', 'OPTIONS, POST');

    		// send supported protocol
    		if ($this->_parms['BinVer'] < 12.1)
    			$http->addHeader('Content-Type', 'application/vnd.ms-sync.wbxml');
    		else
    			$http->addHeader('Content-Type', 'application/vnd.ms-sync');

	    	// show supported version
    		$http->addHeader('MS-ASProtocolVersions', '2.5,12.0,12.1,14.0,14.1,16.0,16.1');

    		// show active serer version
			$http->addHeader('MS-Server-ActiveSync', $cnf->getVar(Config::VERSION));

    		// get enabled data stores
			$ena = $cnf->getVar(Config::ENABLED);

			// show commands which we support
	   	    parent::xpath('//Cmds/*/Version/..');
        	$cmds = [];
        	$ver  = $http->getHTTPVar('MS-ASProtocolVersions');
	   		while (parent::getItem() !== null) {

				$s = parent::savePos();

	   			// get name of command
	   			$n = parent::getName();

	   			// e-mail handler required
	   			parent::restorePos($s);
	   			$e = parent::getVar('MailHandler', false);

	   			// do we support command?
	   			parent::restorePos($s);
	   			if (($e && !($ena & DataStore::MAIL)) ||
	   				strpos(parent::getVar('Version', false), $ver) === null ||
	   				!file_exists(__DIR__.'/mas'.$n.'.php'))
	   				continue;

   				// save command
				$cmds[] = $n;
	   		}
   			$http->addHeader('MS-ASProtocolCommands', implode(',', $cmds));

    		// send data
   			$http->send(200);
			return;
		} else {

			// empty body?
			if (!is_object($ext) && !strlen($ext)) {

				// called command?
				// skip <Autodiscover> - this is a Win 10/11 e-mail program bug
				if (isset($this->_parms['Cmd'])) {

					$ext = new XML();
					$ext->loadXML('<'.$this->_parms['Cmd'].'/>');
					$typ = 'text/xml';
				} else {

					// last chance to catch <Autodiscover>
					if (stripos($req, 'autodiscover') === false) {

						$http->send(400);
						return;
					}
					$ext = new XML();
					$ext->loadXML('<Autodiscover/>');
					$typ = 'text/xml';
				}
			}

			// Autodiscover may not provide user authentication data
			if (strpos($typ, 'text/xml') === false || $ext->getVar('Autodiscover') !== null) {

				// for <Autodiscover> no authentication is required

				// save device type?
				if (isset($this->_parms['DeviceType']))
					Device::getInstance()->updVar('DeviceType', $this->_parms['DeviceType']);
			}
			if (!is_object($ext)) {

				$http->send(501);
				return;
			}
			$ext->setTop();
		}

		// output document
		$out = new XML();

		Msg::InfoMsg('Parsing main structure (level #1)');
		$ext->getChild();
		while ($ext->getItem() !== null) {

			$ip  = $ext->savePos();
			$tag = $ext->getName();
			if (!file_exists($hd = __DIR__.'/mas'.$tag.'.php'))
			    // we should never go here!
				$log->logMsg(Log::WARN, 16012, $hd);
			else {

				$hd = 'syncgw\activesync\mas'.$tag;
				if (method_exists($hd, 'getInstance')) {

					$hd = $hd::getInstance();
			        if (!$hd->Parse($ext, $out)) {
			        	$http->send($this->_rc);
                        return;
			        }

                    if (!$out)
    	   		        break;
			    }
			}
			$ext->restorePos($ip);
		}

		// stop everything <Ping>
		if ($this->_stat == self::EXIT)
			return;

		// stop processing? <GetAttachment>, <Ping>, <SendMail>
		if ($this->_stat == self::STOP) {
			$http->send(200);
			return;
		}

		// encode data?
		$http->addBody($out);

        // send data
		$http->send(200);
	}

	/**
	 * 	Get call parameter
	 *
	 * 	@param	- Parameter to get
	 * 	@return	- Parameter value (string | float) or null if not found
	 */
	public function callParm(string $name) {

		return isset($this->_parms[$name]) ? $this->_parms[$name] : null;
	}

	/**
	 * 	Set call parameter
	 *
	 * 	@param	- Parameter to set
	 * 	qparam 	- Value to save
	 */
	public function setCallParm(string $name, $val): void {

		$this->_parms[$name] = $val;
	}

	/**
	 * 	Load options from input data
	 *
	 *	@param  - Tag name ('Find', 'GetItemEstimate', 'ItemOperations', 'ResolveRecipients', 'Search', 'Sync')
	 *	@param  - Optional handler id
	 */
	public function loadOptions(string $tag, XML &$in): void {

		$ip   = $in->savePos();
		$cls  = Util::HID(Util::HID_TAB, DataStore::ALL, true);
		$clss = Util::HID(Util::HID_PREF, DataStore::ALL, true);

		// clear options
		$this->_opts['Tag'] = $tag;

		// [MS-ASAIRS] [2.2.2.12] BodyPreference
		// The contents of the <airsync:Options>, <itemoperations:Options>, or <search:Options> element specify preferences for
		// all of the content that the user is interested in searching, synchronizing, or retrieving. These preferences are
		// persisted by the server from request to request for the specified client, and can be changed by the inclusion
		// of an <airsync:Options> element in any subsequent request.

		// load options
		switch ($tag) {
		case 'Sync':
			$in->xpath('//Collection');
			while ($in->getItem() !== null) {

				$op = $in->savePos();

				// get options destination
				if (!($gid = $in->getVar('CollectionId', false))) {

					$in->restorePos($op);
					if (($val = $in->getVar('Class', false)) !== null) {

						$v = array_search($val, $cls);
						if (!$v)
							Msg::ErrMsg('Undefined value "'.$val.'" for <Class>');
						$gid = $v ? $v : $val;
					}
				}
				self::_loadOpts($tag, $gid);
				$opts = &$this->_opts[$gid];

				// Time window for the objects that are sent from the server to the client in seconds
				$in->restorePos($op);
				if (($val = $in->getVar('FilterType', false)) !== null) {

					$val = strval($val);
					// Specifying a <FilterType> of 9 or above for when the <CollectionId> element identifies
					// any email, contact, calendar or task collection results in a Status element value of 103.
					if (!isset(self::FILTER[$val]))
						Msg::ErrMsg('Undefined value "'.$val.'" for <Filtertype>');
					else
						$opts['FilterType'] = self::FILTER[$val];
				}

			 	// How to resolve the conflict that occurs when an object has been changed on both the client and the server
			 	// Value Meaning
				// 0	 Client object replaces server object.
				// 1	 Server object replaces client object. (default)
				$in->restorePos($op);
				if (($val = $in->getVar('Conflict', false)) !== null)
					$opts['Conflict'] = $val;

				// Whether the MIME data of the email item SHOULD be truncated when it is sent from the server to the client
				// Value Meaning
				// 0	 Truncate all body text.
				// 1	 Truncate text over 4,096 characters.
				// 2	 Truncate text over 5,120 characters.
				// 3	 Truncate text over 7,168 characters.
				// 4	 Truncate text over 10,240 characters.
				// 5	 Truncate text over 20,480 characters.
				// 6	 Truncate text over 51,200 characters.
				// 7	 Truncate text over 102,400 characters.
				// 8	 Do not truncate; send complete MIME data (default)
				$in->restorePos($op);
				if (($val = $in->getVar('MIMETruncation', false)) !== null) {

					if (!isset(self::SIZE[$val]))
						Msg::ErrMsg('Undefined value "'.$val.'" for <MIMETruncation>');
					else
						$opts['MIMETruncation'] = self::SIZE[$val];
				}

				// Enables MIME support for email items that are sent from the server to the client
				// 0 Never send MIME data. (default)
			   	// 1 Send MIME data for S/MIME [RFC5751] messages only. Send regular body (non S/MIME) data for all other messages.
			    // 2 Send MIME data for all messages. This flag could be used by clients to build a more rich and complete Inbox solution. -->
				$in->restorePos($op);
				if (($val = $in->getVar('MIMESupport', false)) !== null)
					$opts['MIMESupport'] = $val;

				// The max. number of recipients (that is, the top N most frequently used recipients) to keep in RI
				// it only specifies the number of recipients to keep synchronized
				$in->restorePos($op);
				if (($val = $in->getVar('MaxItems', false)) !== null)
					$opts['MaxItems'] = $val;

				// <Truncation> Specifies how the body text of a calendar, contact, email, or task item is to be truncated when
				// it is sent from the server to the client
				// 0 - Truncate all body text.
				// 1 - Truncate body text that is more than 512 characters.
				// 2 - Truncate body text that is more than 1,024 characters.
				// 3 - Truncate body text that is more than 2,048 characters.
				// 4 - Truncate body text that is more than 5,120 characters.
				// 5 - Truncate body text that is more than 10,240 characters.
				// 6 - Truncate body text that is more than 20,480 characters.
				// 7 - Truncate body text that is more than 51,200 characters.
				// 8 - Truncate body text that is more than 102,400 characters.
				// 9 - Do not truncate body text.
				$in->restorePos($op);
				if (($val = $in->getVar('Truncation', false)) !== null)
					$opts['Truncation'] = $val;

				$in->restorePos($op);
				self::_bodyOpts($in, $opts);

				// How the server returns rights-managed email messages to the client
				// The value of this element is a boolean.
				// - If the value of this element is true (1), the server will decompress and decrypt rights-managed email messages
				//   before sending them to the client.
				// - If the value is false (0), the server will not decompress or decrypt rights-managed email messages before
				//   sending them to the client. (default)
				$in->restorePos($op);
				if (($val = $in->getVar('RightsManagementSupport', false)) !== null)
					$opts['RightsManagementSupport'] = $val;

				// -------------------------------------------------------------------------------------------------------------------
				// not part of <Options>
				// -------------------------------------------------------------------------------------------------------------------

				// indicates that any deleted items SHOULD be moved to the deleted items folder
				if (($val = $in->getVar('DeletesAsMoves')) !== null)
					$opts['DeletesAsMoves'] = $val;

				// Request the server to include any pending changes to the collection that is specified by the ServerId element
				if (($val = $in->getVar('GetChanges')) !== null)
					$opts['GetChanges'] = $val == '0' ? '0' : '1';

				// Specifies a maximum number of changed items that SHOULD be included in the synchronization response
				if (($val = $in->getVar('WindowSize')) !== null)
					$opts['WindowSize'] = $val;
			}
			break;

		case 'Find':

			$op = $in->savePos();

			self::_loadOpts($tag);

			// specifies the maximum number of matching entries to return
			// $in->restorePos($op);
			if (($val = $in->getVar('Range', false)) !== null)
				$this->_opts['Range'] = $val;

			// If the <DeepTraversal> element and the <CollectionId> element are not present, all folders returned
			// in <FolderSync> and their subfolders will be searched. If the <DeepTraversal> element and the <CollectionId>
			// element are present, the folder specified by the <CollectionId> and all its subfolders are searched.
			$in->restorePos($op);
			if ($in->getVar('DeepTraversal', false) === null)
				$this->_opts['DeepTraversal'] = '1';

			// client is requesting that contact photos be returned in the server response
			// A value of 0 indicates whole item is fetched
			$in->restorePos($op);
			if ($in->getVar('Picture', false) !== null) {

				$p = $in->savePos();

				// limits the size of contact photos returned in the server response
				if (($val = $in->getVar('MaxSize', false)) !== null)
					$this->_opts['MaxSize'] = $val;

				// Limits the number of contact photos returned in the server response
				$in->restorePos($p);
				if (($val = $in->getVar('MaxPictures', false)) !== null)
					$this->_opts['MaxPictures'] = $val;
			}
			break;

		case 'GetItemEstimate':

			$in->xpath('//Collection');
			while ($in->getItem() !== null) {
				$op = $in->savePos();

				// get options destination
				if (!($gid = $in->getVar('CollectionId', false))) {
					$in->restorePos($op);
					if (($val = $in->getVar('Class', false)) !== null) {
						if ($v = array_search($val, $cls))
							Msg::ErrMsg('Undefined value "'.$val.'" for <Class>');
						$gid = $hid = $v ? $v : $val;
					}
				} else
					$hid = array_search(substr($gid, 0, 1), $clss);

				self::_loadOpts($tag, $gid);
				$opts = &$this->_opts[$gid];

				// Time window for the objects that are sent from the server to the client in seconds
				$in->restorePos($op);
				if (($val = $in->getVar('FilterType', false)) !== null) {
					$val = strval($val);
					// Specifying a <FilterType> of 9 or above for when the <CollectionId> element identifies
					// any email, contact, calendar or task collection results in a Status element value of 103.
					if (!isset(self::FILTER[$val]))
						Msg::ErrMsg('Undefined value "'.$val.'" for <Filtertype>');
					else
						$opts['FilterType'] = self::FILTER[$val];
					// special hack for Tasks
					if (self::FILTER[$val] == -1 && !($hid & DataStore::TASK))
						$opts['FilterType'] = 0;
				}

				// specifies the maximum number of items to include in the response
				// Including <MaxItems> when the <CollectionId> element is set to anything other than "RI" results
				// in an invalid XML error, Status element value of 2.
				$in->restorePos($op);
				if (($val = $in->getVar('MaxItems', false)) !== null)
					$opts['MaxItems'] = $val;
			}
			break;

		case 'ItemOperations':

			$op = $in->savePos();

			foreach ([ 'Move', 'EmptyFolderContents', 'Fetch', ] as $stag) {

				if ($in->xpath('//'.$stag)) {

					while ($in->getItem() !== null) {

						$op = $in->savePos();

						if ($stag == 'Move')
							$gid = $in->getVar('ConversationId', false);
						elseif ($stag == 'EmptyFolderContents')
							$gid = $in->getVar('CollectionId', false);
						else
							// 'Fetch'
							$gid = strcasecmp($in->getVar('Store'), 'mailbox') !== null ? DataStore::MAIL : DataStore::DOCLIB;

						self::_loadOpts($tag, $gid);
						$opts = &$this->_opts[$gid];

						// indicates whether to move the specified conversation, including all future emails in the conversation,
						// to the folder specified by the <DstFldId> element value
						$in->restorePos($op);
						if ($in->getVar('MoveAlways', false) !== null)
							$opts['MoveAlways'] = '1';

						// indicates whether to delete the subfolders of the specified folder
						$in->restorePos($op);
						if (($val = $in->getVar('DeleteSubFolders', false)) !== null)
							$opts['DeleteSubFolders'] = $val;

						// The schema of the item to be <Fetch> (e-mail only)
						// $in->restorePos($op);
						if ($in->getVar('Schema', false) !== null)
							; // not yet implemented

						// Specifies the range of bytes that the client can receive in response to the <Fetch> operation
						// A max. value of 0 indicates whole item is fetched
						$in->restorePos($op);
						if (($val = $in->getVar('Range', false)) !== null)
							$opts['Range'] = $val;

						// The user name leveraged to <Fetch> the desired item in <ItemOperations>
				   		$in->restorePos($op);
				   		if (($val = $in->getVar('UserName', false)) !== null)
				   			$opts['UserName'] = $val;

						// The password for the <User> in <ItemOperations>
   						$in->restorePos($op);
   						if (($val = $in->getVar('Password', false)) !== null)
		   					$opts['Password'] = $val;

						// Enables MIME support for email items that are sent from the server to the client
				  		// 0 Never send MIME dat (default)
					   	// 1 Send MIME data for S/MIME [RFC5751] messages only. Send regular body (non S/MIME) data for all other messages.
					    // 2 Send MIME data for all messages. This flag could be used by clients to build a more rich and complete Inbox solution. -->
						$in->restorePos($op);
						if (($val = $in->getVar('MIMESupport', false)) !== null)
							$opts['MIMESupport'] = $val;

						// How the server returns rights-managed email messages to the client
						// The value of this element is a boolean.
						// - If the value of this element is true (1), the server will decompress and decrypt rights-managed email messages
						//   before sending them to the client.
						// - If the value is false (0), the server will not decompress or decrypt rights-managed email messages before
						//   sending them to the client. (default)
						$in->restorePos($op);
						if (($val = $in->getVar('RightsManagementSupport', false)) !== null)
							$opts['RightsManagementSupport'] = $val;

						$in->restorePos($op);
						self::_bodyOpts($in, $opts);

						$in->restorePos($op);
					}
				}
			}
			break;

		case 'ResolveRecipients':

			$op = $in->savePos();

			self::_loadOpts($tag);

			// Specifies whether S/MIME certificates are returned by the server for each resolved recipient
	    	// 1 Do not retrieve certificates for the recipient (default).
		    // 2 Retrieve the full certificate for each resolved recipient.
		    // 3 Retrieve the mini certificate for each resolved recipient.
			// $in->restorePos($op);
			if (($val = $in->getVar('CertificateRetrieval', false)) !== null)
				$this->_opts['CertificateRetrieval'] = $val;

			// Limits the total number of certificates that are returned by the server in <ResolveRecipients>
			$in->restorePos($op);
			if (($val = $in->getVar('MaxCertificates', false)) !== null)
				$this->_opts['MaxCertificates'] = $val;

		   	//  Limits the number of suggestions that are returned for each ambiguous recipient node in <ResolveRecipients>
			$in->restorePos($op);
			if (($val = $in->getVar('MaxAmbiguousRecipients', false)) !== null)
				$this->_opts['MaxAmbiguousRecipients'] = $val;

			// identifies the start time and end time of the free/busy data to retrieve
			// the server uses a default end time value of seven days after the StartTime value
			// '0-0' - do not server free/busy time request
			$in->restorePos($op);
			if ($in->getVar('Availability', false) !== null) {

				$p   = $in->savePos();
				$val = time();
				if (($v = $in->getVar('StartTime', false)) !== null)
			   		$val = Util::unxTime($v);
				$in->restorePos($p);
				if (($v = $in->getVar('EndTime', false)) !== null)
		   			$val .= '/'.Util::unxTime($v);
				else
					$val .= '/'.($val + 604800);
		   		$this->_opts['Availability'] = $val;
			}

			// client is requesting that contact photos be returned in the server response
			$in->restorePos($op);
			if ($in->getVar('Picture', false) !== null) {

				$p = $in->savePos();

				// limits the size of contact photos returned in the server response
				if (($val = $in->getVar('MaxSize', false)) !== null)
					$this->_opts['MaxSize'] = $val;

				// Limits the number of contact photos returned in the server response
				$in->restorePos($p);
				if (($val = $in->getVar('MaxPictures', false)) !== null)
					$this->_opts['MaxPictures'] = $val;
			}
			break;

		case 'Search':

			if (!$in->xpath('//Store'))
				break;

			while ($in->getItem() !== null) {

				$op = $in->savePos();

				// Store			Options
				// GAL				Range
				// 					UserName
				// 					Password
				// 					Picture
				// Mailbox			Range
				// 					DeepTraversal
				// 					RebuildResults
				// 					airsyncbase:BodyPreference
				// 					airsyncbase:BodyPartPreference
				// 					rm:RightsManagementSupport
				// Document Library	Range
				// 					UserName
				// 					Password

				if (stripos($n = $in->getVar('Name'), 'gal') !== false)
					$gid = DataStore::CONTACT;
				elseif (stripos($n, 'mailbox') !== false)
					$gid = DataStore::MAIL;
				else
					$gid = DataStore::DOCLIB;

				self::_loadOpts($tag, $gid);
				$opts = &$this->_opts[$gid];

				// Enables MIME support for email items that are sent from the server to the client
		  		// 0 Never send MIME data (default)
			   	// 1 Send MIME data for S/MIME [RFC5751] messages only. Send regular body (non S/MIME) data for all other messages.
			    // 2 Send MIME data for all messages. This flag could be used by clients to build a more rich and complete Inbox solution. -->
			    // $in->restorePos($op);
				if (($val = $in->getVar('MIMESupport', false)) !== null)
					$opts['MIMESupport'] = $val;

				// How the server returns rights-managed email messages to the client
				// The value of this element is a boolean.
				// - If the value of this element is true (1), the server will decompress and decrypt rights-managed email messages
				//   before sending them to the client.
				// - If the value is false (0), the server will not decompress or decrypt rights-managed email messages before
				//   sending them to the client. (default)
				$in->restorePos($op);
				if (($val = $in->getVar('RightsManagementSupport', false)) !== null)
					$opts['RightsManagementSupport'] = $val;

				// specifies the range of bytes that the client can receive in response to the fetch operation. Max. values:
			 	// Store 			Default range value Maximum results returned
		        // Mailbox 			0-99 				100
			   	// DocumentLibrary 	0-999 				1000
			    // GAL 				0-99 				100
				$in->restorePos($op);
				if (($val = $in->getVar('Range', false)) !== null)
					$opts['Range'] = $val;

				// The user name leveraged to fetch the desired item
		   		$in->restorePos($op);
				if (($val = $in->getVar('UserName', false)) !== null)
					$opts['UserName'] = $val;

				// The password for the <UserName>
				$in->restorePos($op);
	   			if (($val = $in->getVar('Password', false)) !== null)
	   				$opts['Password'] = $val;

				// the client wants the server to search all subfolders for the folder that is specified in the query
				// If the <DeepTraversal> element is not present, the subfolders are not searched.
				$in->restorePos($op);
				if ($in->getVar('DeepTraversal', false) !== null)
					$opts['DeepTraversal'] = '1';

				// forces the server to rebuild the search folder that corresponds to a given query
				$in->restorePos($op);
				if ($in->getVar('RebuildResults', false) !== null)
					$opts['RebuildResults'] = '1';

				$in->restorePos($op);
				self::_bodyOpts($in, $opts);

				// client is requesting that contact photos be returned in the server response
				$in->restorePos($op);
				if ($in->getVar('Picture', false) !== null) {

					$p = $in->savePos();

					// limits the size of contact photos returned in the server response
					if (($val = $in->getVar('MaxSize', false)) !== null)
						$opts['MaxSize'] = $val;

					// Limits the number of contact photos returned in the server response
					$in->restorePos($p);
					if (($val = $in->getVar('MaxPictures', false)) !== null)
						$opts['MaxPictures'] = $val;
				}
			}
			break;
		}

		$in->restorePos($ip);

		// save options
		self::_saveOpts($tag);

		Msg::InfoMsg('Filtering of events and tasks from trace parameters enabled');
		if (0) {
			$cnf = Config::getInstance();
			if (isset($opts['FilterType']) && $opts['FilterType'] && $cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE) {
	           	Msg::WarnMsg('Disabling filter for calendar debugging purpose (original is "'.$opts['FilterType'].'")');
	           	$opts['FilterType'] = 0;
			}
		}
	}

	/**
	 * 	Get options
	 *
	 *	@param  - <Sync>				<CollectionId> or HID
	 *			- <Find>				Empty
	 *			- <ResolveRecipients>	Empty
	 *			- <ItemOperations>		<ConversationId> or <CollectionId> or HID
	 *			- <GetItemEstimate>		<CollectionId> or HID
	 *			- <Search>				HID or null
	 *			- -1					Get last options loaded
	 *	@return - Options []
	 */
	public function getOption(string $key = ''): array {

		// last options loaded?
		if ($key == -1) {
			if (!isset($this->_opts['Last'])) {

				Msg::WarnMsg('Last option never read - setting defaults');
				$this->_opts['Error'] = [];
				$this->_opts['Last'] = &$this->_opts['Error'];

				if (Config::getInstance()->getVar(Config::DBG_SCRIPT) == 'MIME01' ||
					Config::getInstance()->getVar(Config::DBG_SCRIPT) == 'MIME02')

					foreach ($this->_opts as $k => $v)

						if (is_numeric($k)) {

							$this->_opts['Last'] = &$this->_opts[$k];
							break;
						}
			}
		}

		switch ($this->_opts['Tag']) {
		case 'Sync':
		case 'GetItemEstimate':
		case 'Search':
		case 'ItemOperations':

			// <CollectionId>
			if (isset($this->_opts[$key])) {
				$this->_opts['Last'] = &$this->_opts[$key];
				break;
			}

			// <Class>
			$cls = Util::HID(Util::HID_PREF, DataStore::ALL, true);
			$v = array_search(substr($key, 0, 1), $cls);
			if (isset($this->_opts[$v])) {
				$this->_opts['Last'] = &$this->_opts[$v];
				break;
			}

			if ($this->_opts['Tag'] == 'Search')
				$key = strval(DataStore::CONTACT);

			// get defaults
			self::_loadOpts($this->_opts['Tag'], $key);
			$this->_opts['Last'] = &$this->_opts[$key];
			break;

		case 'Find':
		case 'ResolveRecipients':
			if (!isset($this->_opts))
				self::_loadOpts($this->_opts['Tag']);
			$this->_opts['Last'] = &$this->_opts;

		default:
			break;
		}

		Msg::InfoMsg($this->_opts['Last'], '<Options> loaded for "'.$key.'"');

		return $this->_opts['Last'];
	}

	/**
	 * 	Set options
	 *
	 *	@param 	- Group ID
	 *	@param 	- Variable name
	 *	@param 	- New value
	 */
	public function setOption(string $grp, string $name, string $val): void {

		$this->_opts[$grp][$name] = $val;
	}

	/**
	 *  Load defaults and stored options
	 *
	 *	@param  - For which tag
	 *	@param 	- GID or HID
	 */
	private function _loadOpts(string $tag, $id = ''): void {

		$dev = Device::getInstance();

		// set default options
		switch ($tag) {
		case 'Sync':
			$this->_opts[$id]['FilterType'] = '0';
			$this->_opts[$id]['Conflict'] = '1';
			$this->_opts[$id]['MIMETruncation'] = self::SIZE['8'];
			$this->_opts[$id]['MIMESupport'] = '0';
			$this->_opts[$id]['MaxItems'] = '0';
			$this->_opts[$id]['Truncation'] = '9';
			$this->_opts[$id]['RightsManagementSupport'] = '0';
			$this->_opts[$id]['DeletesAsMoves'] = '1';
			$this->_opts[$id]['GetChanges'] = '0';
			$this->_opts[$id]['WindowSize'] = '512';
			foreach ( [ 'BodyPreference', 'BodyPartPreference' ] as $tag) {
				foreach (fldBody::TYP_AS as $typ) {
					$this->_opts[$id][$tag.$typ] = [];
					$this->_opts[$id][$tag.$typ]['AllOrNone'] = 0;
				}
			}
			break;

		case 'Find':
			$this->_opts['Range'] = '0-999';
			$this->_opts['DeepTraversal'] = '1';
			$this->_opts['MaxSize'] = '0';
			$this->_opts['MaxPictures'] = '0';
			break;

		case 'GetItemEstimate':
			$this->_opts[$id]['FilterType'] = '0';
			$this->_opts[$id]['MaxItems'] = '0';
			break;

		case 'ItemOperations':
			$this->_opts[$id]['MoveAlways'] = '0';
			$this->_opts[$id]['DeleteSubFolders'] = '0';
			$this->_opts[$id]['Schema'] = '';
			$this->_opts[$id]['Range'] = '0-9999999';
			$this->_opts[$id]['UserName'] = '';
			$this->_opts[$id]['Password'] = '';
			$this->_opts[$id]['MIMESupport'] = '0';
			$this->_opts[$id]['RightsManagementSupport'] = '0';
			foreach ( [ 'BodyPreference', 'BodyPartPreference' ] as $tag) {
				foreach (fldBody::TYP_AS as $typ) {
					$this->_opts[$id][$tag.$typ] = [];
					$this->_opts[$id][$tag.$typ]['AllOrNone'] = 0;
				}
			}
			break;

		case 'ResolveRecipients':
			$this->_opts['CertificateRetrieval'] = '1';
			$this->_opts['MaxCertificates'] = '9999';
			$this->_opts['MaxAmbiguousRecipients'] = '9999';
			$this->_opts['Availability'] = '0/0';
			$this->_opts['MaxSize'] = '0';
			$this->_opts['MaxPictures'] = '0';
			break;

		case 'Search':
			$this->_opts[$id]['MIMESupport'] = '0';
			$this->_opts[$id]['RightsManagementSupport'] = '0';
			$this->_opts[$id]['Range'] = '0-99';
			$this->_opts[$id]['UserName'] = '';
			$this->_opts[$id]['Password'] = '';
			$this->_opts[$id]['DeepTraversal'] = '0';
			$this->_opts[$id]['RebuildResults'] = '0';
			$this->_opts[$id]['MaxSize'] = '0';
			$this->_opts[$id]['MaxPictures'] = '0';

		default:
			break;
		}


		if (!$dev->xpath('//Options/'.$tag))
			return;
		$dev->getItem();

		switch ($tag) {
		case 'Sync':

			$dev->xpath('BodyPreference', false);
			while ($dev->getItem() !== null) {

				$ip = $dev->savePos();
				$gid = $dev->getVar('Id', false);
				$dev->restorePos($ip);
				$typ = $dev->getVar('Type', false);
				$this->_opts[$gid]['BodyPreference'.$typ] = [];
				foreach ([ 'TruncationSize', 'AllOrNone', 'Preview', ] as $id) {

					$dev->restorePos($ip);
					if (($val = $dev->getVar($id, false)) !== null)
						$this->_opts[$gid]['BodyPreference'.$typ][$id] = $val;
				}
				$dev->restorePos($ip);
			}
			break;

		case 'ItemOperations':
		case 'Search':
			$dev->xpath('BodyPreference', false);
			while ($dev->getItem() !== null) {

				$ip = $dev->savePos();
				$hid = $dev->getVar('Id', false);
				$dev->restorePos($ip);
				$typ = $dev->getVar('Type', false);
				$this->_opts[$hid]['BodyPreference'.$typ] = [];
				foreach ([ 'TruncationSize', 'AllOrNone', 'Preview', ] as $id) {

					$dev->restorePos($ip);
					if (($val = $dev->getVar($id, false)) !== null)
						$this->_opts[$hid]['BodyPreference'.$typ][$id] = $val;
				}
				$dev->restorePos($ip);
			}
			break;

		default:
			break;
		}

	}

	/**
	 *  Save <BodyPreferences> options for specific tag
	 *
	 *	@param 	- Tag name
	 */
	private function _saveOpts(string $tag): void {

		$dev = Device::getInstance();

		// any option already saved?
		if ($dev->getVar('Options') === null) {

			$dev->getVar('Data');
			$dev->addVar('Options');
		}

		// any data to tag already saved?
		$op = $dev->savePos();
		if ($dev->getVar($tag, false) === null) {

			$dev->restorePos($op);
			$dev->addVar($tag);
		}
		$op = $dev->savePos();

		switch ($tag) {
		case 'Sync':
			foreach ($this->_opts as $gid => $opts) {

				if ($gid == 'Last' || $gid == 'Tag')
					continue;
				// delete all saved options
				$dev->xpath('*[Id="'.$gid.'"]', false);
				while ($dev->getItem() !== null)
					$dev->delVar(null, false);

				foreach (fldBody::TYP_AS as $typ) {
					if (isset($opts['BodyPreference'.$typ])) {

						$dev->addVar('BodyPreference');
						$dev->addVar('Id', strval($gid));
						$dev->addVar('Type', $typ);
						foreach ($opts['BodyPreference'.$typ] as $k => $v)
							$dev->addVar($k, strval($v));
						$dev->restorePos($op);
					}
				}
			}
			break;

		case 'ItemOperations':
		case 'Search':
			foreach ($this->_opts as $hid => $opts) {

				if ($hid == 'Last' || $hid == 'Tag')
					continue;
				// delete all saved options
				$dev->xpath('*[Id="'.$hid.'"]', false);
				while ($dev->getItem() !== null)
					$dev->delVar(null, false);
				foreach (fldBody::TYP_AS as $typ) {
					if (isset($opts['BodyPreference'.$typ])) {

						$dev->addVar('BodyPreference');
						$dev->addVar('Id', strval($hid));
						$dev->addVar('Type', $typ);
						foreach ($opts['BodyPreference'.$typ] as $k => $v)
							$dev->addVar($k, strval($v));
						$dev->restorePos($op);
					}
				}
			}
			break;
		}
	}

	/**
	 *  Get body options
	 *
	 *  @param 	- XML input document
	 *  @param  - Where to store options
	 */
	private function _bodyOpts(XML &$in, array &$opts): void {

		$ip = $in->savePos();

		// check for options
		foreach ( [ 'BodyPreference', 'BodyPartPreference' ] as $tag) {

			if (!$in->xpath('Options/'.$tag, false))
				continue;

			while ($in->getItem() !== null) {

				$op = $in->savePos();

				// 1 Plain text
			    // 2 HTML
			    // 3 Rich Text Format
			    // 4 MIME
				// $in->restorePos($p);
				$typ = $in->getVar('Type', false);

				// The size of content is used for the request
				// The maximum value for <TruncationSize> is 4,294,967,295.
				// If the <TruncationSize> element is absent, the entire content is used for the request.
				$in->restorePos($op);
				if (($val = $in->getVar('TruncationSize', false)) !== null)
					$opts[$tag.$typ]['TruncationSize'] = $val;

				// Along with the <TruncationSize> element, it is instructing the server not to return a truncated response for that type
				// when the size (in bytes) of the available data exceeds the value of the <TruncationSize> element.
				// If the client also includes the <AllOrNone> element with a value of 1 (true) along with the <TruncationSize> element,
				// it is instructing the server not to return a truncated response for that type when the size (in bytes) of the
				// available data exceeds the value of the <TruncationSize> element.
				// For example, a client can use these two elements to signify that it cannot process partial Rich Text Format (RTF) data
				// (a <Type> element value of 3). In this case, if the client has specified multiple <BodyPreference> elements,
				// the server selects the next <BodyPreference> element that will return the maximum amount of body text to the client.
				$in->restorePos($op);
				if (($val = $in->getVar('AllOrNone', false)) !== null)
					$opts[$tag.$typ]['AllOrNone'] = $val;

				// Specifies the maximum length of the Unicode plain text message or message part preview to be returned to the client.
				// This element MUST have a value set from 0 to 255, inclusive.
				$in->restorePos($op);
				if (($val = $in->getVar('Preview', false)) !== null)
					$opts[$tag.$typ]['Preview'] = $val;

				$in->restorePos($op);
			}
		}

		$in->restorePos($ip);
	}

	/**
	 * 	Handle <Ping> status
	 *
	 *  @param  - Modus<fieldset>
	 *            masHandler::LOAD		load
	 *            masHandler::SET		set status
	 *            masHandler::DEL		delete options
	 *  @param  - Handler ID
	 *	@param 	- Group ID
	 * 	@return - List of GUIDS (only for masHandler::LOAD)
	 */
	public function PingStat(int $mod, int $hid, string $grp = ''): array {

		$usr = User::getInstance();
		$act = $usr->getVar('ActiveDevice');

		// load list of GUID?
		if ($mod & self::LOAD) {

			$rc = [];
			if ($usr->xpath('//Device[DeviceId="'.$act.'"]/DataStore[HandlerID="'.$hid.'"]/Ping/Group')) {

				while (($val = $usr->getItem()) !== null)
			        $rc[] = $val;
			}
			return $rc;
		}

		// set status?
		if ($mod & self::SET) {

			// be sure to delete existing entries
			self::PingStat(self::DEL, $hid, $grp);

			// create new Ping block
			$usr->xpath('//Device[DeviceId="'.$act.'"]/DataStore[HandlerID="'.$hid.'"]/Ping');
		    $usr->getItem();
			$usr->addVar('Group', $grp);
		}

		// delete ping status?
		if ($mod & self::DEL) {

		    if ($usr->xpath('//Device[DeviceId="'.$act.'"]/DataStore[HandlerID="'.$hid.'"]/Ping[Group="'.$grp.'"]')) {
				while ($usr->getItem() !== null)
					$usr->delVar(null, false);
			}
		}

		return [];
	}

	/**
	 * 	Handle "Search" results
	 *
	 *  @param  - $mod 					Modus<fieldset>
	 *  		- masHandler::LOAD		Load & Delete ($parm=Handleer ID)<br>
	 *          - masHandler::SET		Save ($parm=array()))<br>
	 *  @param  - $parm					[ $hid, $grp, $gid ]
	 * 	@return - 					    [ $hid, $grp, $gid ]
	 */
	public function searchId(int $mod, ?array $parm = null): array {

		$usr = User::getInstance();
		$act = $usr->getVar('ActiveDevice');
		$rc  = [];

		// load list of GUID?
		if ($mod & self::LOAD) {

			$usr->xpath('//Device[DeviceId="'.$act.'"]/DataStore[HandlerID="'.$parm.'"]/Search/.');
			while ($usr->getItem()) {

				$ip = $usr->savePos();
				$usr->xpath('Record');
				while (($v = $usr->getItem()) !== null) {

					$v = explode('/', $v);
					$rc[] = [ $parm, $v[1], $v[2] ];
					$usr->delVar(null, false);
				}
				$usr->restorePos($ip);
			}
		}

		// save search result
    	if ($mod & self::SET) {

    		$hid = 0;
    		foreach ($parm as $rec) {

    			if ($hid != $rec[0]) {
    				$hid = $rec[0];
					$usr->xpath('//Device[DeviceId="'.$act.'"]/DataStore[HandlerID="'.$hid.'"]/Search/.');
    				$usr->getItem();
    			}
				$usr->addVar('Record', $rec[0].'/'.$rec[1].'/'.$rec[2]);
    		}
		}

		return $rc;
	}

	/**
	 * 	Set processing status
	 *
	 * 	@param 	- Status
	 */
	public function setStat(int $stat): void {

		$this->_stat = $stat;
	}

	/**
	 * 	Set HTTP return code
	 */
	public function setHTTP(int $rc): void {

		$this->_rc = $rc;
	}

}
