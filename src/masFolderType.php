<?php
declare(strict_types=1);

/*
 * 	ActiveSync type definitions
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

class masFolderType {

	// [MS-ASCMD] 2.2.3.186.3 Type (FolderSync)
	const F_GENERIC		= '1';
	const F_INBOX		= '2';
	const F_DRAFT		= '3';
	const F_DELETED		= '4';
	const F_SENT		= '5';
	const F_OUTBOX		= '6';
	const F_TASK		= '7';
	const F_CALENDAR	= '8';
	const F_CONTACT		= '9';
	const F_NOTE		= '10';
	const F_JOURNAL		= '11';
	const F_UMAIL		= '12';
	const F_UCALENDAR	= '13';
	const F_UCONTACT	= '14';
	const F_UTASK		= '15';
	const F_UJOURNAL	= '16';
	const F_UNOTE		= '17';
	const F_UNKNOWN		= '18';
	const F_CACHE		= '19';

	// type description
	const TYPE          = [
		self::F_GENERIC			=>	'User-created folder (generic)',
		self::F_INBOX			=>	'Default "Inbox" folder',
		self::F_DRAFT			=>	'Default "Drafts" folder',
		self::F_DELETED			=>	'Default "Deleted" folder',
		self::F_SENT			=> 	'Default "Sent" folder',
		self::F_OUTBOX			=>	'Default "Outbox" folder',
		self::F_TASK			=>	'Default "Tasks" folder',
		self::F_CALENDAR		=> 	'Default "Calendar" folder',
		self::F_CONTACT			=>	'Default "Contacts" folder',
		self::F_NOTE			=>	'Default "Notes" folder',
		self::F_JOURNAL			=>	'Default "Journal" folder',
		self::F_UMAIL			=>	'User-created "Mail" folder',
		self::F_UCALENDAR		=>	'User-created "Calendar" folder',
		self::F_UCONTACT		=>	'User-created "Contacts" folder',
		self::F_UTASK			=>	'User-created "Tasks" folder',
		self::F_UJOURNAL		=>	'User-created "Journal" folder',
		self::F_UNOTE			=>	'User-created "Notes" folder',
		self::F_UNKNOWN			=>	'Unknown folder type',
		self::F_CACHE			=>	'Recipient Information cache',
	];

	/**
	 * 	Get file type
	 *
	 * 	@param	- Type
	 * 	@return	- Description
	 */
	static public function type(string $typ): string {

		return isset(self::TYPE[$typ]) ? self::TYPE[$typ] : '+++ Typ "'.sprintf('%d',$typ).'" not found';
	}

}
