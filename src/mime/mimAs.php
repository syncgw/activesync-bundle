<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync classes
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\Config;
use syncgw\lib\Msg;
use syncgw\lib\XML;
use syncgw\lib\DataStore;
use syncgw\document\field\fldTimezone;

class mimAs extends XML {

	/**
	 *  Handler ID
	 *  @var int
	 */
	protected $_hid = 0;

	/**
	 *  mim types
	 *  @var array
	 */
	public $_mime = [];

	/**
	 *  Mapping table
	 */
	public $_map = [];

	/**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
 	 */
	public function getInfo(XML &$xml): void {

		foreach ($this->_mime as $mime) {

			$xml->addVar('Opt', sprintf('MIME type handler "%s"', $mime[0]));
			$xml->addVar('Stat', $mime[1] ? sprintf('%.1F', $mime[1]) : '');
		}
	}

	/**
	 * 	Convert MIME data to internal document
	 *
	 *	@param	- MIME type
	 *  @param  - MIME version
	 *  @param  - External document
	 * 	@param 	- Internal document
	 * 	@return	- true = Ok; false = We're not responsible
	 */
	public function import(string $typ, float $ver, XML &$ext, XML &$int): bool {

		$xp = $ext->savePos();

		// be sure to set proper position in internal document
		$int->getVar('Data');

 		// swap data
 		$rc = false;
		foreach ($this->_map as $tag => $class) {
		   	if ($class->import($typ, $ver, $tag, $ext, '', $int))
		   		$rc = true;
		    $ext->restorePos($xp);
		}
		$tag; // disable Eclipse warning

		$int->getVar('syncgw');
		Msg::InfoMsg($int, 'Imported document');

		return $rc;
	}

	/**
	 * 	Export to external document
	 *
	 *	@param	- Requested MIME type
	 *  @param  - Requested MIME version
	 * 	@param 	- Internal document
	 *  @param  - External document
	 * 	@return	- true = Ok; false = We're not responsible
	 */
	public function export(string $typ, float $ver, XML &$int, XML &$ext): bool {

		$int->getVar('syncgw');
		Msg::InfoMsg($int, 'Input document');
		$ip = $int->savePos();

		// add time zone parameter
		if ($this->_hid & DataStore::CALENDAR && !$int->getVar(fldTimezone::TAG)) {
			$int->getVar('Data');
			$cnf = Config::getInstance();
			$int->addVar(fldTimezone::TAG, $cnf->getVar(Config::TIME_ZONE));
		    $int->restorePos($ip);
		}

		$ext->addVar('ApplicationData');
		$xp = $ext->savePos();

		// swap data
		foreach ($this->_map as $tag => $class) {
		    $class->export($typ, $ver, 'Data/', $int, $tag, $ext);
		    $int->restorePos($ip);
		}
		$tag; // disable Eclipse warning

		$ext->restorePos($xp);
		Msg::InfoMsg($ext, 'Output document');

		return true;
	}

}