<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync global address list class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsGAL extends mimAs {

	const MIME = [

		// note: this is a virtual non-existing MIME type
    	[ 'application/activesync.gal+xml', 1.0 ],
	];
    const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    // Document source    													Exchange ActiveSync: Command Reference Protocol
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
		'DisplayName'														=> 'fldFullName',
	    'Phone'																=> 'fldBusinessPhone',
	    'Office'															=> 'fldOffice',
		'Title'																=> 'fldTitle',
        'Company'															=> 'fldCompany',
	    'Alias'																=> 'fldAlias',
    	'FirstName'															=> 'fldFirstName',
	    'LastName'															=> 'fldLastName',
	    'HomePhone'															=> 'fldHomePhone',
	    'MobilePhone'														=> 'fldMobilePhone',
        'EmailAddress'														=> 'fldMailHome',
    	'Picture'															=> 'fldPhoto',
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
    ];

    /**
     * 	Singleton instance of object
     * 	@var mimAsGAL
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsGAL {

		if (!self::$_obj) {

			self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::CONTACT;

			foreach (self::MAP as $tag => $class) {

				$class = 'syncgw\\document\\field\\'.$class;
			    $class = $class::getInstance();
			    self::$_obj->_map[$tag] = $class;
			}
		}

		return self::$_obj;
	}

}
