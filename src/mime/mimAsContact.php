<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync contact class
 *
 *	@package	sync*gw
 *	@subpackage	mim support
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsContact extends mimAs {

	const MIME = [

    	// note: this is a virtual non-existing MIME type
		[ 'application/activesync.contact+xml', 1.0 ],
	];
    const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// Document source														Exchange ActiveSync: Contact Class Protocol
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	    'Alias'																=> 'fldAlias',
    	'Anniversary'                                                       => 'fldAnniversary',
       	'AssistantName'														=> 'fldAssistant',
	   	'AssistantPhoneNumber'												=> 'fldAssistantPhone',
	   	'Birthday'															=> 'fldBirthday',
	   	'Body'																=> 'fldBody',
	// 	'Body/Type'																// Handled by fldBody
	//  'Body/EstimatedDataSize'												// Handled by fldBody
    //  'Body/Truncated'														// Handled by fldBody
	//  'Body/Data'																// Handled by fldBody
	//  'Body/Part'																// Handled by fldBody
	//  'Body/Preview'															// Handled by fldBody
	//	'BodyTruncated'															// Handled by fldBody
	//	'BodySize'																// Handled by fldBody
        'BusinessAddressCity'												=> 'fldAddressBusiness',
	//  'BusinessAddressCountry'												// Handled by fldAddressBusiness
	//  'BusinessAddressPostalCode'												// Handled by fldAddressBusiness
	//  'BusinessAddressState'													// Handled by fldAddressBusiness
	//  'BusinessAddressStreet'													// Handled by fldAddressBusiness
        'BusinessFaxNumber'													=> 'fldBusinessFax',
        'BusinessPhoneNumber'												=> 'fldBusinessPhone',
        'Business2PhoneNumber'												=> 'fldBusinessPhone2',
        'CarPhoneNumber'													=> 'fldCarPhone',
        'Categories'														=> 'fldCategories',
    //  'Categories/Category'													// Handled by fldCategories
	    'Children'															=> 'fldChild',
    //  'Children/Child'														// Handled by fldChilD
        'CompanyName'														=> 'fldCompany',
	    'Department'														=> 'fldDepartment',
	    'Email1Address'														=> 'fldMailHome',
	    'Email2Address'														=> 'fldMailWork',
	    'Email3Address'														=> 'fldMailOther',
	    'FileAs'															=> 'fldFullName',
	    'FirstName'															=> 'fldFirstName',
	    'HomeAddressCity'													=> 'fldAddressHome',
	//  'HomeAddressCountry'													// Handled by fldAddressHome
	//  'HomeAddressPostalCode'													// Handled by fldAddressHome
	//  'HomeAddressState'														// Handled by fldAddressHome
	//  'HomeAddressStreet'														// Handled by fldAddressHome
        'HomeFaxNumber'														=> 'fldHomeFax',
	    'HomePhoneNumber'													=> 'fldHomePhone',
	    'Home2PhoneNumber'													=> 'fldHomePhone2',
	    'JobTitle'															=> 'fldTitleJob',
		'LastName'															=> 'fldLastName',
	    'MiddleName'														=> 'fldMiddleName',
        'MobilePhoneNumber'													=> 'fldMobilePhone',
        'OfficeLocation'													=> 'fldOffice',
	    'OtherAddressCity'													=> 'fldAddressOther',
	//  'OtherAddressCountry'													// Handled by fldAddressOther
	//  'OtherAddressPostalCode'												// Handled by fldAddressOther
	//  'OtherAddressState'														// Handled by fldAddressOther
	//  'OtherAddressStreet'													// Handled by fldAddressOther
	    'PagerNumber'														=> 'fldPager',
	    'Picture'															=> 'fldPhoto',
	    'RadioPhoneNumber'													=> 'fldRadioPhone',
	    'Spouse'															=> 'fldSpouse',
	    'Suffix'															=> 'fldSuffix',
	    'Title'																=> 'fldTitle',
	    'WebPage'															=> 'fldURLOther',
	    'WeigthedRank'														=> 'fldWeigthedRank',
	    'YomiCompanyName'													=> 'fldYomiCompany',
	    'YomiFirstName'														=> 'fldYomiFirstName',
	    'YomiLastName'														=> 'fldYomiLastName',

        'AccountName'														=> 'fldAccountName',
	    'CompanyMainPhone'													=> 'fldCompanyPhone',
        'CustomerId'														=> 'fldCustomerId',
        'GovernmentId'														=> 'fldGovernmentId',
	    'IMAddress'															=> 'fldIMskype',
	    'IMAddress2'														=> 'fldIMicq',
	    'IMAddress3'														=> 'fldIMmsn',
	    'ManagerName'														=> 'fldManagerName',
	    'MMS'																=> 'fldMMSPhone',
	    'NickName'															=> 'fldNickName',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
    ];

    /**
     * 	Singleton instance of object
     * 	@var mimAsContact
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsContact {

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
