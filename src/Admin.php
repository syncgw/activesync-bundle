<?php
declare(strict_types=1);

/*
 * 	Administration interface handler class
 *
 *	@package	sync*gw
 *	@subpackage	File handler
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\interface\file;

use syncgw\lib\Config;
use syncgw\lib\DataStore;
use syncgw\interface\DBAdmin;
use syncgw\gui\guiHandler;

class Admin implements DBAdmin {

    /**
     * 	Singleton instance of object
     * 	@var Admin
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Admin {

	   	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

     /**
	 * 	Show/get installation parameter
	 */
	public function getParms(): void {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		if (!($cnf->getVar(Config::DATABASE))) {

			if(!($c = $gui->getVar('FileDir')))
				$c = $cnf->getVar(Config::FILE_DIR);

			$gui->putQBox('Data base root directory',
							'<input name="FileDir" type="text" size="40" maxlength="250" value="'.$c.'" />',
							'Please specify where files should be stored. Please be aware you need to enable access to this directory '.
							'for your web server user id (<a class="sgwA" href="http://www.php.net/manual/en/ini.sect.safe-mode.php#ini.open-basedir"'.
							' target="_blank">more information</a>).', false);

		}
	}

	/**
	 * 	Connect to handler
	 *
	 * 	@return - true=Ok; false=Error
	 */
	public function Connect(): bool {

		$gui = guiHandler::getInstance();
		$cnf = Config::getInstance();

		// connection already established?
		if ($cnf->getVar(Config::DATABASE))
			return true;

		$v = $gui->getVar('FileDir');
		if (!$v || !realpath($v)) {

			$gui->clearAjax();
			$gui->putMsg('Directory does not exist', Config::CSS_ERR);
			return false;

		}

		// does directory exist?
		if (!file_exists($v))
			mkdir($v, 0755, true);

		// check attributes
		if (!is_writable($v)) {

			$gui->clearAjax();
			$gui->putMsg('Error accessing directory - please check file permission on directory \'syncgw\'', Config::CSS_ERR);
			return false;

		}

		// save path
		$cnf->updVar(Config::FILE_DIR, $v);

		return true;
	}

	/**
	 * 	Disconnect from handler
	 *
	 * 	@return - true=Ok; false=Error
 	 */
	public function DisConnect(): bool {

		return true;
	}

	/**
	 * 	Return list of supported data store handler
	 *
	 * 	@return - Bit map of supported data store handler
	 */
	public function SupportedHandlers(): int {

		return DataStore::DATASTORES&~DataStore::MAIL;
	}

}
