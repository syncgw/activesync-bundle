<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync note class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsNote extends mimAs {

	const MIME = [

		// note: this is a virtual non-existing MIME type
		[ 'application/activesync.note+xml', 1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// Document source     													Exchange ActiveSync: Notes Class Protocol
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
		'Subject'															=> 'fldSummary',
        'MessageClass'														=> 'fldMessageClass',
        'LastModifiedDate'													=> 'fldLastMod',
	    'Categories'														=> 'fldCategories',
	//  'Categories/Category'													// Handled by fldCategories
	    'Body'																=> 'fldBody',
	//  'Body/Type'																// Handled by fldBody
	//  'Body/EstimatedDataSize'												// Handled by fldBody
    //  'Body/Truncated'														// Handled by fldBody
	//  'Body/Data'																// Handled by fldBody
	//  'Body/Preview'															// Handled by fldBody
	//  'Body/Part'																// Handled by fldBody
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	];

    /**
     * 	Singleton instance of object
     * 	@var mimAsNote
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsNote {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::NOTE;

			foreach (self::MAP as $tag => $class) {

				$class = 'syncgw\\document\\field\\'.$class;
			    $class = $class::getInstance();
			    self::$_obj->_map[$tag] = $class;
			}
		}

		return self::$_obj;
	}

}
