<?php
declare(strict_types=1);

/*
 * 	<Provision> handler class
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

use syncgw\lib\Config;
use syncgw\lib\ErrorHandler;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\XML;

class masProvision extends XML {

	// status codes
	const NOPOLICY   = '2';
	const TYPE	 	 = '3';
	const CORRUPT	 = '4';
	const KEY		 = '5';
	// status description
	const STAT       = [
		self::NOPOLICY		=>	'There is no policy for this client',
		self::TYPE			=>	'Unknown PolicyType value',
		self::CORRUPT		=> 	'The policy data on the server is corrupted (possibly tampered with)',
		self::KEY			=>	'The client is acknowledging the wrong policy key',
	];

   /**
     * 	Singleton instance of object
     * 	@var masProvision
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): masProvision {

		if (!self::$_obj) {
            self::$_obj = new self();

			// set log message codes 16101-16200
			Log::getInstance()->setLogMsg([
					16101 => 'Error loading [%s]',
					16102 => 'Provision status %d received from device',
			]);

			// load policy
			if (!self::$_obj->loadFile($file = Config::getInstance()->getVar(Config::ROOT).
									  'activesync-bundle/assets/masPolicy.xml'))
	        	ErrorHandler::getInstance()->Raise(16101, $file);
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
  	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Opt', '<a href="https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-asprov" '.
					 'target="_blank">[MS-ASPROV]</a>Exchange ActiveSync: Provisioning handler');
		$xml->addVar('Stat', '21.1');

		$xml->addVar('Opt', 'Exchange ActiveSync server policy version');
		parent::getVar('Policy');
		$v = parent::getAttr('ver');
		$xml->addVar('Stat', $v ? 'v'.$v : 'N/A');
	}

	/**
	 * 	Parse XML node
	 *
	 * 	@param	- Input document
	 * 	@param	- Output document
	 * 	@return	- true = Ok; false = Error
	 */
	public function Parse(XML &$in, XML &$out): bool {

		Msg::InfoMsg($in, '<Provision> input');

		$out->addVar('Provision', null, false, $out->setCP(XML::AS_PROVISION));

		// used for sending the device's properties to the server in an initial <Provision> command request
		// version 14.0 or 12.1 will not send data, but we take what we get :-)
		if ($in->getVar('DeviceInformation') !== null) {

			$op = $out->savePos();
			// create dummy output object
			$xml = new XML();
			$set = masSettings::getInstance();
			$set->Parse($in, $xml);
			$out->addVar('DeviceInformation', null, false, $out->setCP(XML::AS_SETTING));
			$out->addVar('Status', $xml->getVar('Status'), false, $out->setCP(XML::AS_PROVISION));
			$out->restorePos($op);
		}

		$out->addVar('Status', $rc = masStatus::OK, false, $out->setCP(XML::AS_PROVISION));

		// <Policies> -> not used

		$in->xpath('//Policy');
		while ($in->getItem() !== null) {

			$ip = $in->savePos();
			$op = $out->savePos();

			$out->addVar('Policies');
			$out->addVar('Policy');

			// specifies the format in which the policy settings are to be provided to the device
			$typ = $in->getVar('PolicyType', false);
			// any Policy elements that have a value for their PolicyType child element other than "MS-EAS-Provisioning-WBXML" SHOULD be ignored.
			if ($typ != 'MS-EAS-Provisioning-WBXML')
				$typ = 'MS-EAS-Provisioning-WBXML';
			$out->addVar('PolicyType', $typ);

			$out->addVar('Status', $rc = masStatus::OK);

			// mark the state of policy settings on the client in the settings download phase of the <Provision> command
			$in->restorePos($ip);
			if (!($pkey = $in->getVar('PolicyKey', false)))
				$k = time() % 65535;
			else
				$k = $pkey;
			$out->addVar('PolicyKey', strval($k));

			// check client return code
			if ($pkey) {

				$in->restorePos($ip);
				if (($n = $in->getVar('Status', false)) != masStatus::OK && $n != self::NOPOLICY)
					Msg::logMsg(Log::WARN, 16102, $n);
			}

			// send default policy
			if ($rc == masStatus::OK && !$pkey) {

				$out->addVar('Data');
				// specifies the collection of security settings for device provisioning
				$out->addVar('EASProvisionDoc');

				parent::getChild('Policy');
				while (($v = parent::getItem()) !== null)
					$out->addVar(parent::getName(), $v);
			}

			$in->restorePos($ip);
			$out->restorePos($op);
		}

		// @todo WIPE <RemoteWipe> - remote wipe directive
		// specifies either a remote wipe directive from the server or a client's confirmation of a server's remote wipe directive
		$in->xpath('//RemoteWipe');
		while ($in->getItem() !== null) {

			$ip = $in->savePos();
			$op = $out->savePos();


			$in->restorePos($ip);
			$out->restorePos($op);
		}

		// @todo WIPE <AccountOnlyRemoteWipe> - account only remote wipe directive
		// specifies either an account only remote wipe directive from the server or a client's
		// confirmation of a server's account only remote wipe directive
		$in->xpath('//AccountOnlyRemoteWipe');
		while ($in->getItem() !== null) {

			$ip = $in->savePos();
			$op = $out->savePos();


			$in->restorePos($ip);
			$out->restorePos($op);
		}

		$out->getVar('Provision');
		Msg::InfoMsg($out, '<Provision> output');

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
