<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync document class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsDocLib extends mimAs {

	const MIME = [

		// note: this is a virtual non-existing MIME type
		[ 'application/activesync.doclib+xml', 1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// Document source     													Exchange ActiveSync: Document Class Protocol
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
		'LinkId'															=> 'fldLinkId',
        'DisplayName'														=> 'fldFullName',
	    'CreationDate'														=> 'fldCreated',
	    'LastModifiedDate'													=> 'fldLastMod',
        'IsFolder'															=> 'fldIsFolder',
	    'IsHidden'															=> 'fldIsHidden',
	    'ContentLength'														=> 'fldContentLength',
	    'ContentType'														=> 'fldContentType',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	];

   	/**
     * 	Singleton instance of object
     * 	@var mimAsDocLib
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsDocLib {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::DOCLIB;

			foreach (self::MAP as $tag => $class) {

				$class = 'syncgw\\document\\field\\'.$class;
			    $class = $class::getInstance();
			    self::$_obj->_map[$tag] = $class;
			}
		}

		return self::$_obj;
	}

}
