<?php
declare(strict_types=1);

/*
 * 	Intermal file data base interface class
 *
 *	@package	sync*gw
 *	@subpackage	File handler
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

/**
 * 	structure of GUID control table:
 * 	[ <GUID> => [ 0 => <LUID>, 1 => <Group>, 2 => <Type>, 3 => <SyncStat> ]]
 */

namespace syncgw\interface\file;

use syncgw\lib\Config;
use syncgw\lib\DB;
use syncgw\lib\DataStore;
use syncgw\lib\ErrorHandler;
use syncgw\lib\Lock;
use syncgw\lib\Log;
use syncgw\lib\Msg;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\interface\DBintHandler;

class Handler implements DBintHandler {

	// control file name
	const CONTROL 		 = 'FileDB.id';

	// control fields
	const LUID 	  		 = 'LUID';
	const GROUP   		 = 'Group';
	const TYPE	  		 = 'Type';
	const STAT 	  		 = 'Stat';

 	/**
	 * 	Control table
	 *
	 * 	[ File name ]
	 *
	 * 	@var array
	 */
	private $_ctl = [];

    /**
     * 	Singleton instance of object
     * 	@var Handler
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): Handler {

		if (!self::$_obj) {

            self::$_obj = new self();

			$cnf = Config::getInstance();

			// set log message codes 20001-30100
			Log::getInstance()->setLogMsg([
					20001 => 'Error creating [%s]',
			        20002 => 'Document validation failed - cannot save empty XML objects',
					20003 => 'Invalid XML data in record \'%s\' in data store %s for user (%s)',
					20804 => 'User not set',
			]);

			// is handler enabled?
			if (get_class(self::$_obj) == __CLASS__ && $cnf->getVar(Config::DATABASE) != 'file')
				return self::$_obj;

			// get base directory
			if (!($base = $cnf->getVar(Config::FILE_DIR)))
				$base = $cnf->getVar(Config::TMP_DIR);

			// make sure to create root directory
			if (!self::$_obj->_mkDir($base))
				return self::$_obj;

			// create data store path names
			$unam = Util::HID(Util::HID_TAB, DataStore::USER, true);
			foreach (Util::HID(Util::HID_TAB, DataStore::ALL, true) as $k => $v) {

				if ($k & DataStore::SYSTEM) {

					if (!self::$_obj->_mkDir($base.$v))
						return self::$_obj;
					self::$_obj->_ctl[$k] = $base.$v.'/';
				} elseif ($k & DataStore::DATASTORES)
					self::$_obj->_ctl[$k] = $base.$unam.'/%s/'.$v.'/';
			}
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Name', 'File interface handler');

		$xml->addVar('Opt', 'Status');
		$cnf = Config::getInstance();
		if ($cnf->getVar(Config::DATABASE) != 'file') {
			$xml->addVar('Stat', 'Disabled');
			return;
		}
		$xml->addVar('Stat', 'Enabled');

		$xml->addVar('Opt', 'Directory path');
		$xml->addVar('Stat', $cnf->getVar(Config::FILE_DIR));
	}

	/**
	 * 	Perform query on internal data base
	 *
	 * 	@param	- Handler ID
	 * 	@param	- Query command:<fieldset>
	 * 			  DataStore::ADD 	  Add record                             $parm= XML object<br>
	 * 			  DataStore::UPD 	  Update record                          $parm= XML object<br>
	 * 			  DataStore::DEL	  Delete record or group (inc. sub-recs) $parm= GUID<br>
	 * 			  DataStore::RLID     Read single record                     $parm= LUID<br>
	 * 			  DataStore::RGID     Read single record       	             $parm= GUID<br>
	 * 			  DataStore::GRPS     Read all group records                 $parm= None<br>
	 * 			  DataStore::RIDS     Read all records in group              $parm= Group ID or '' for record in base group<br>
	 * 			  DataStore::RNOK     Read recs with SyncStat != STAT_OK     $parm= Group ID
	 * 	@return	- According  to input parameter<fieldset>
	 * 			  DataStore::ADD 	  New record ID or false on error<br>
	 * 			  DataStore::UPD 	  true=Ok; false=Error<br>
	 * 			  DataStore::DEL	  true=Ok; false=Error<br>
	 * 			  DataStore::RLID     XML object; false=Error<br>
	 * 			  DataStore::RGID	  XML object; false=Error<br>
	 * 			  DataStore::GRPS	  [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::RIDS     [ "GUID" => Typ of record ]<br>
	 * 			  DataStore::RNOK     [ "GUID" => Typ of record ]
	 */
	public function Query(int $hid, int $cmd, $parm = '') {

		$log = Log::getInstance();

		if (($hid & DataStore::EXT))
			return $cmd & (DataStore::RIDS|DataStore::RNOK|DataStore::GRPS) ? [] : false;

		if (($hid & DataStore::SYSTEM))
			$uid = 0;
		else {
			// get user ID
			$usr = User::getInstance();
			if (!($uid = intval($usr->getVar('LUID')))) {
				if (Config::getInstance()->getVar(Config::DBG_SCRIPT))
					$uid = 11;
				else {
					$log->logMsg(Log::ERR, 20804);
					return $cmd & (DataStore::RIDS|DataStore::RNOK|DataStore::GRPS) ? [] : false;
				}
			}
		}

		// get directory name
		$dir = sprintf($this->_ctl[$hid], $uid);

		// check subdirectories
		if ($hid & DataStore::DATASTORES && !file_exists($dir)) {

			// create user base directory
			if (!self::_mkDir($dir))
				return $cmd & (DataStore::RIDS|DataStore::RNOK|DataStore::GRPS) ? [] : false;

			// create user data store
			if (!self::_mkDir($dir))
				return $cmd & (DataStore::RIDS|DataStore::RNOK|DataStore::GRPS) ? [] : false;
		}

		$dmsg = 'Query('.Util::HID(Util::HID_CNAME, $hid, true).', '.DB::OPS[$cmd].', '.
		        (is_object($parm) ? get_class($parm) : $parm).', uid='.$uid.')';

		// set default return value
		$out  = true;

		// build control table
		$gids = [];
		$lids = [];
		$ctl  = sprintf($this->_ctl[$hid].self::CONTROL, $uid);
        $lck  = Lock::getInstance();

		// lock control table
		if ($cmd & (DataStore::ADD|DataStore::UPD|DataStore::DEL))
			$lck->lock($ctl, true);

		// load control table
		if (file_exists($ctl)) {
			if (!($gids = unserialize(file_get_contents($ctl))))
			    $gids = [];
			foreach ($gids as $gid => $v)
				$lids[$v[self::LUID]] = $gid;
		}

		// replace parameter
		switch ($cmd) {
		case DataStore::ADD:
		case DataStore::UPD:

			// add/update <GUID> in control file
			$gid = $parm->getVar('GUID');

			// return "new" record id?
			if ($cmd & DataStore::ADD)
			    $out = $gid;

		    // save control variables
			$gids[$gid][self::LUID]  = $parm->getVar('LUID');
			$gids[$gid][self::GROUP] = $parm->getVar('Group');
			$gids[$gid][self::TYPE]  = $parm->getVar('Type');
			$gids[$gid][self::STAT]  = $parm->getVar('SyncStat');

		    // replace any non a-z, A-Z and 0-9 character with "-" in file name
			$path = $dir.preg_replace('|[^a-zA-Z0-9]+|', '-', $gid).'.xml';
			Msg::InfoMsg($dmsg.'"'.$path.'"');

			// check document consistency
			if ($parm->getVar('syncgw') === null)  {

                $log->logMsg(Log::WARN, 20001, $path);
			    if (Config::getInstance()->getVar(Config::DBG_SCRIPT) ) {
                    foreach (ErrorHandler::Stack() as $r)
                        $log->logMsg(Log::WARN, 10001, $r);
                }
    			$out = false;
			    break;
    		}

    		// save file
    		if ($parm->saveFile($path, true) === false) {
                $log->logMsg(Log::ERR, 20001, $path);
                DbgXMLError();
                Util::Save(__FUNCTION__.'%d.xml', $parm->saveXML(true));
                if (Config::getInstance()->getVar(Config::DBG_SCRIPT) ) {
                    foreach (ErrorHandler::Stack() as $r)
                        $log->logMsg(Log::WARN, 10001, $r);
                }
    			$out = false;
    		}
			break;

		case DataStore::DEL:
			// do we know record?
			if (!isset($gids[$parm])) {
				$out = false;
				break;
			}
			// delete whole group?
			if (substr($parm, 0, 1) != DataStore::TYP_DATA) {
			    foreach ($gids as $k => $v) {
			        if ($v[self::GROUP] == $parm) {
            		    // replace any non a-z, A-Z and 0-9 character with "-" in file name
            			$path = $dir.preg_replace('|[^a-zA-Z0-9]+|', '-', $k).'.xml';
            			if (file_exists($path))
                            unlink($path);
			             unset($gids[$k]);
			        }
			    }
			}
			// delete record itself
		    // replace any non a-z, A-Z and 0-9 character with "-" in file name
			$path = $dir.preg_replace('|[^a-zA-Z0-9]+|', '-', $parm).'.xml';
			if (file_exists($path))
                unlink($path);
			unset($gids[$parm]);
            Msg::InfoMsg($dmsg.'"'.$path.'"');
			break;

		case DataStore::RGID:
		case DataStore::RLID:
			if ($cmd & DataStore::RGID) {
				if (!isset($gids[$parm])) {
   	 	            $out = false;
    	            break;
				}
			} elseif (!isset($lids[$parm])) {
   	 	        $out = false;
    	        break;
			}
			// replace any non a-z, A-Z and 0-9 character with "-" in file name
			$path = $dir.preg_replace('|[^a-zA-Z0-9]+|', '-', $cmd & DataStore::RGID ? $parm : $lids[$parm]).'.xml';
			Msg::InfoMsg($dmsg.'"'.$path.'"');
			if (!file_exists($path))
			    $out = false;
			elseif (($out = file_get_contents($path)) !== false) {
				$xml = new XML();
				if (!$xml->loadXML($out)) {
				    $id = [];
					// extract <GUID> from record to get reference record number for error message
				    preg_match('#(?<=\<GUID\>).*(?=\</GUID\>)#', null, $id);
					ErrorHandler::getInstance()->Raise(20003, $id[0], Util::HID(Util::HID_ENAME, $hid, $uid));
					$out = false;
				} else
					$out = $xml;
			}
			break;

	    case DataStore::GRPS:
			Msg::InfoMsg($dmsg.'"'.$this->_ctl[$hid].'"');
			$out = [];
			foreach ($gids as $gid => $v) {
				if ($v[self::TYPE] == DataStore::TYP_GROUP)
					$out[$gid] = $v[self::TYPE];
			}
			break;

	    case DataStore::RIDS:
			Msg::InfoMsg($dmsg.'"'.$this->_ctl[$hid].'"');
			$out = [];
			foreach ($gids as $gid => $v) {
				if ($parm == $v[self::GROUP])
					$out[$gid] = $v[self::TYPE];
			}
			break;

			case DataStore::RNOK:
			Msg::InfoMsg($dmsg.'"'.$this->_ctl[$hid].'"');
			$out = [];
			foreach ($gids as $gid => $v) {
				if ($parm == $v[self::GROUP] && $v[self::STAT] != DataStore::STAT_OK)
					$out[$gid] = $v[self::TYPE];
			}
			break;

		default:
			$out = false;
			break;
		}

		// update control table
		if ($cmd & (DataStore::ADD|DataStore::UPD|DataStore::DEL)) {
		    if (file_put_contents($ctl, serialize($gids)) === false) {
		        // give write a second chance
		        Util::Sleep();
    			if (file_put_contents($ctl, serialize($gids)) === false)
	       		    $out = false;
		    }
		}

		// unlock contol file
		if ($cmd & (DataStore::ADD|DataStore::UPD|DataStore::DEL))
			$lck->unlock($ctl);

		return $out;
	}

	/**
	 * 	Excute raw SQL query on internal data base
	 *
	 * 	@param	- SQL query string
	 * 	@return	- Result string or []; null on error
	 */
	public function SQL(string $query) {}

	/**
	 * 	Create directory
	 *
	 * 	@param	- Directory name
	 * 	@return	- true=Ok; false=Error
	 */
	private function _mkDir(string $dir): bool {

		if (!@is_dir($dir)) {

			if ($base = dirname($dir))
				self::_mkDir($base);

			if (!file_exists($dir)) {

    			if (!@mkdir($dir)) {

    				ErrorHandler::getInstance()->Raise(20001, $dir);
    				$this->_ok = false;
    				return false;
    			}
			}
		}

		return true;
	}

}
