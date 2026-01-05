<?php
declare(strict_types=1);

/*
 * 	Process HTTP input / output
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Config;
use syncgw\lib\HTTP;
use syncgw\lib\Log;
use syncgw\lib\Util;
use syncgw\lib\WBXML;
use syncgw\lib\XML;

class masHTTP extends HTTP {

    /**
     * 	Singleton instance of object
     * 	@var masHTTP
     */
    static private $_obj = null;

	/**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masHTTP {

	   	if (!self::$_obj) {

            self::$_obj = new self();

			// set log message codes 16001-16010
			Log::getInstance()->setLogMsg( [
					16001 => 'Error loading XML data from device - aborting connection',
					16002 => 'Invalid data received from device - aborting connection',
			]);

			// initialize parent
			parent::getInstance();
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Opt', '<a href="https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-ashttp" '.
					'target="_blank">[MS-ASHTTP]</a> Exchange ActiveSync: HTTP Protocol handler');
		$xml->addVar('Stat', 'v22.0');
	}

	/**
	 * 	Check HTTP input
	 *
	 * 	@return - HTTP status code
	 */
	public function checkIn(): int {

		$uri = isset(self::$_http[HTTP::SERVER]['REQUEST_URI']) ? self::$_http[HTTP::SERVER]['REQUEST_URI'] : '';
		if (isset(self::$_http[HTTP::SND_HEAD]['Content-Type']))
			$ct = self::$_http[HTTP::SND_HEAD]['Content-Type'];
		elseif (isset(self::$_http[HTTP::SERVER]['CONTENT_TYPE']))
			$ct = self::$_http[HTTP::SERVER]['CONTENT_TYPE'];
		else
			$ct = '';

		// are we responsible?
		if (!isset(self::$_http[HTTP::SERVER]['HTTP_MS_ASPROTOCOLVERSION']) &&
			// /Microsoft-Server-ActiveSync
			stripos($uri, 'activesync') === false &&
			// /Autodiscover/Autodiscover.xml
			stripos($uri, 'autodiscover') === false &&
			strpos($ct, 'ms-sync') == false)
			return 200;

		// set handler we discovered
		$cnf = Config::getInstance();
		$cnf->updVar(Config::HANDLER, 'MAS');

		$hck = 0;
		$log = Log::getInstance();
		$xml = null;
		$mas = masHandler::getInstance();

		// process query string
		$qry = isset(self::$_http[HTTP::SERVER]['QUERY_STRING']) ? self::$_http[HTTP::SERVER]['QUERY_STRING'] : '';

		// [MS-ASHTTP] 2.2.1.1.1.1 Base64-Encoded Query Value
		// is string base64 encoded?
		$v   = base64_decode($qry);
		if ($v && $qry == base64_encode($v)) {

        	// 1 byte       - protocol version (0)
        	$n 	 = sprintf('%d', ord($v[0]));
			self::$_http[self::RCV_HEAD]['MS-ASProtocolVersion'] = substr($n, 0, 2).'.'.substr($n, 2);

        	// 1 byte       - command code (1)
        	if (!$mas->xpath('//Cmds/*/Code[text()="'.sprintf('%d', ord($v[1])).'"]/..'))
				$qry = 'Cmd=Unknown'.sprintf('%d', ord($v[1]));
        	else {

        		$mas->getItem();
        		$qry = 'Cmd='.$mas->getName();
        	}

        	// 2 bytes      - locale (2)
        	if (!$mas->xpath('//LcID/*[text()="'.sprintf('0x%04x', ord(substr($v, 2, 2))).'"]/.'))
				$qry .= '&LcID=Unknown'.sprintf('0x%04x', ord(substr($v, 2, 2)));
        	else {

        		$mas->getItem();
	       		$qry .= '&LcID='.$mas->getName();
	        }

	        // 1 byte       - device ID length
	        // variable     - device ID (device ID length + device id)
	        if ($n = ord($v[4]))
	        	$qry .= '&DeviceId='.base64_encode(substr($v, 5, $n));
	        $p = 5 + $n;

	        // 1 byte       - policy key length
	        // 0 or 4 bytes - policy key (policy key length + policy key)
	        if ($n = ord($v[$p]))
	        	self::$_http[self::RCV_HEAD]['X-MS-PolicyKey'] = sprintf('%d', substr($v, $p + 1, $n));
	        $p += 1 + $n;

	        // 1 byte       - device type length
	        // variable     - device type (device type length + device type)
	        if ($n = ord($v[$p]))
	        	$qry .= '&DeviceType='.substr($v, $p + 1, $n);
	        $p += 1 + $n;

	        // variable     - command parameters, array which consists of:
	        //                      1 byte      - tag
	        //                      1 byte      - length
	        //                      variable    - value of the parameter
	        $v = substr($v, $p);
	        while (strlen($v) > 0) {

	        	// [MS-ASHTTP] 2.2.1.1.1.1.2 Command Codes
	        	$tag = null;
	            if (!$mas->xpath('//CmdParm/*[text()="'.sprintf('%d', ord($v[0])).'"]/.'))
					$qry .= '&Unknown'.sprintf('%d', ord($v[0])).'=';
	        	else {

	        		$mas->getItem();
	        		$qry .= '&'.($tag = $mas->getName()).'=';
	        	}
	        	// get length
	        	$n = ord($v[1]);
	        	if ($tag == 'Options') {

		            if (!$mas->xpath('//Options/*[text()="'.sprintf('%d', ord($v[2])).'"]/.'))
						$qry .= '&Unknown'.sprintf('%d', ord($v[2])).'=';
	    	    	else {

	        			$mas->getItem();
	        			$qry .= ($tag = $mas->getName());
	    	    	}
	    	    	// [MS-ASHTTP] 2.2.1.1.1.1.3 Command Parameters
	    	    	if ($tag == 'AcceptMultiPart')
						self::$_http[self::RCV_HEAD]['MS-ASAcceptMultiPart'] = 'T';
	    	    	// else
	    	    	// 	SaveInSent
	    	    	// Set this flag to instruct the server to save the Message object in the user's Sent Items folder.
	    	    	// Valid for SendMail, SmartForward, and SmartReply.
	        	} else
		        	$qry .= substr($v, 2, $n);

		        $v = substr($v, 2 + $n);
			}
		}

		// do we need to replace query string?
		if ($qry) {

			$t = explode(' ', self::$_http[self::RCV_HEAD]['Request']);
			$p = explode('?', $t[1]);
			$v = base64_decode($p[1]);
			if ($v && $p[1] == base64_encode($v))
				self::$_http[self::RCV_HEAD]['Request'] = $t[0].' '.$p[0].'?'.$qry.' '.$t[2];
			self::$_http[HTTP::SERVER]['QUERY_STRING'] = $qry;

	    	if (strpos($qry, 'AcceptMultiPart'))
				self::$_http[self::RCV_HEAD]['MS-ASAcceptMultiPart'] = 'T';
		}

		// debug modifications?
		if ($cnf->getVar(Config::DBG_LEVEL) == Config::DBG_TRACE) {

			foreach ( [ 'REDIRECT_QUERY_STRING' => HTTP::SERVER,
					    'QUERY_STRING' => HTTP::SERVER,
						'REQUEST_URI' => HTTP::SERVER,
						'Request' => HTTP::RCV_HEAD, ] as $name => $typ) {

				if (!isset(self::$_http[$typ][$name]))
					continue;

				// change DeviceId=
				if (($p = strpos(self::$_http[$typ][$name], 'DeviceId')) !== false) {

					$t1 = substr(self::$_http[$typ][$name], 0, $p + 9);
					$t2 = substr(self::$_http[$typ][$name], $p + 9);
					if (($p1 = strpos($t2, '&')) !== false)
						$t2 = substr($t2, 0, $p1);
					else
						$p = 0;
					if (strcmp(substr($t2, 0, Config::DBG_PLEN), Config::DBG_PREF)) {

						$t3 = substr(self::$_http[$typ][$name], $p + $p1 + 9);
						self::$_http[$typ][$name] = $t1.Config::DBG_PREF.$t2.$t3;
					}
				}

				// change User=
				if (($p = strpos(self::$_http[$typ][$name], 'User')) !== false) {

					$t1 = substr(self::$_http[$typ][$name], 0, $p + 5);
					$t2 = substr(self::$_http[$typ][$name], $p + 5);
					$t2 = substr($t2, 0, $p1 = strpos($t2, '&'));
					if (strcmp(substr($t2, 0, Config::DBG_PLEN), Config::DBG_PREF)) {
						$t3 = substr(self::$_http[$typ][$name], $p + $p1 + 5);
						self::$_http[$typ][$name] = $t1.$cnf->getVar(Config::DBG_USR).$t3;
					}
				}

			}
		}


		// decode body?
		if ($body = self::$_http[HTTP::RCV_BODY]) {

			if (stripos($ct, 'ms-sync')) {
				$wb = WBXML::getInstance();
				if (($xml = $wb->Decode($body)) === null) {

					Util::Save(__FUNCTION__.'%d.wbxml', $body);
					$log->logMsg(Log::ERR, 16002);
					return 501;
				}
			}
			elseif (strpos($ct, 'message/rfc822') > 0) {

				$c   = $mas->callParm('Cmd');
				$xml = new XML();
				$xml->loadXML('<'.$c.'><Mime>'.$body.'</Mime></'.$c.'>');
			} else {

				// any data?
				if (!strlen(self::$_http[HTTP::RCV_BODY])) {

					// check for empty commands
				    if (!($c = $mas->callParm('Cmd')))
						return 501;

					$body = '<'.$c.'/>';
					self::$_http[HTTP::SERVER]['CONTENT_TYPE'] = 'application/vnd.ms-sync';
					if ($mas->callParm('BinVer') < 12.1)
						self::$_http[HTTP::SERVER]['CONTENT_TYPE'] .= '.wbxml';
				}
				// convert all "xmlsns=" tags to "xml-ns="
				$body = str_replace([ 'xmlns=', 'xmlns:' ], [ 'xml-ns=', 'xml-ns:' ], $body);
				$xml  = new XML();
				if (!$xml->loadXML($body)) {

					Util::Save(__FUNCTION__.'%d.xml', $body);
					$log->logMsg(Log::ERR, 16001);
					return 501;
				}
			}
			self::$_http[HTTP::RCV_BODY] = $body;
		}

   		// swap used protocol version
		if (!isset(self::$_http[HTTP::SERVER]['HTTP_MS_ASPROTOCOLVERSION']))
   			self::$_http[HTTP::SERVER]['HTTP_MS_ASPROTOCOLVERSION'] = masHandler::MSVER;

		// special hack for "Nine" application
		if (isset(self::$_http[HTTP::SERVER]['HTTP_USER_AGENT']) &&
				  strpos(self::$_http[HTTP::SERVER]['HTTP_USER_AGENT'], 'nine-sdk') !== false)
   		    $hck |= Config::HACK_NINE;

		// set special hack flag
		$cnf->updVar(Config::HACK, $hck);

		// set received data
		if ($xml)
			self::$_http[HTTP::RCV_BODY] = $xml;

		return 200;
	}

	/**
	 * 	Check HTTP output
	 *
	 * 	@return - HTTP status code
	 */
	public function checkOut(): int {

		$cnf = Config::getInstance();

		// output processing
		if ($cnf->getVar(Config::HANDLER) != 'MAS')
			return 200;

		$ct    = isset(self::$_http[HTTP::SND_HEAD]['Content-Type']) ? self::$_http[HTTP::SND_HEAD]['Content-Type'] :
			     (isset(self::$_http[HTTP::RCV_HEAD]['Content-Type']) ? self::$_http[HTTP::RCV_HEAD]['Content-Type'] : '');
		$parts = null;

		if (is_object(self::$_http[HTTP::SND_BODY])) {

			$xml = self::$_http[HTTP::SND_BODY];

			if (strpos($ct, 'ms-sync') !== false) {

				// If this header is present and the value is 'T(rue', the client is requesting that the server
				// return content in multipart format. If the header is not present, or is present and set
				// to 'F(alse', the client is requesting that the server return content in inline format.
				if (isset(self::$_http[HTTP::RCV_HEAD]['MS-ASAcceptMultiPart']) &&
						  self::$_http[HTTP::RCV_HEAD]['MS-ASAcceptMultiPart'] === 'T') {

					$xml->xpath('//Data');
					while ($data = $xml->getItem()) {

						$parts[] = base64_decode($data);
						$xml->delVar();
					}

				}

				// set to top
				$xml->setTop();
				$wbx = WBXML::getInstance();
    			// convert data back to WBXML
    			$data = $wbx->Encode($xml, false);

    			// multipart?
				if ($parts) {

					// add WBXML code to multipart array
					array_unshift($parts, $data);

					$cnt = count($parts);

					// change response typ
					$ct = 'application/vnd.ms-sync.multipart';

					// PartCount (4 bytes)
					$data = pack('V', $cnt);

    		        // PartsMetaData (8 bytes)
    		        foreach ($parts as $part)
						$data .= pack('V', $cnt++ * 8 + 4).pack('V', strlen($part));

    		        // Parts (variable) - now add data itself
    		        foreach ($parts as $part)
    		        	$data .= $part;
				}
		    } else {

    			// convert to string
   				$data = $xml->saveXML(true, true);

    			// delete optional character set attributes
    			$data = preg_replace('/(\sCHARSET)(=[\'"].*[\'"])/iU', '', $data);

    			$data = str_replace([ 'xml-ns=', 'xml-ns:', ], [ 'xmlns=', 'xmlns:', ], $data);
		    }
		} else
			$data = self::$_http[HTTP::SND_BODY];

		$n = $data ? strlen($data) : 0;

		if ($cnf->getVar(Config::DBG_LEVEL) != Config::DBG_OFF && $n) {

			if ($ct == 'application/vnd.ms-sync.multipart' && is_array($parts)) {

				$cnt = unpack('V', substr($data, 0, 4))[1];
				$parts = null;
				$pos = 4;
				while ($cnt--) {

					$off = unpack('V', substr($data, $pos, 4))[1];
					$len = unpack('V', substr($data, $pos + 4, 4))[1];
					$pos += 8;
					$parts[] = substr($data, $off, $len);
				}

				$data = array_shift($parts);
				foreach ($parts as $part)
    				Util::Save('Parts%d.bin', $part);
			}

    		// convert data back to WBXML
			elseif (substr($data, 0, 2) != '<?') {

	    		$wbx = WBXML::getInstance();
				$data = $wbx->Decode($data);
	    		// delete optional character set attributes
    			$data = preg_replace('/(\sCHARSET)(=[\'"].*[\'"])/iU', '', $data->saveXML());
			}

			if (substr($data, 0, 2) == '<?')
		   		$data = str_replace([ 'xml-ns=', 'xml-ns:', ], [ 'xmlns=', 'xmlns:', ], $data);
 		}

		self::addBody($data);

		// send header
		if ($n) {

			self::addHeader('Content-Type', $ct);
			self::addHeader('Connection', 'Keep-Alive');
    	    self::addHeader('Vary', 'Accept-Encoding');
		}

	    self::addHeader('Content-Length', strval($n));
		self::addHeader('Date', gmdate(Config::RFC_TIME));

		return 200;
	}

}
