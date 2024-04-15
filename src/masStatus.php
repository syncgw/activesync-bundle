<?php
declare(strict_types=1);

/*
 * 	ActiveSync status definitions
 *
 *	@package	sync*gw
 *	@subpackage	ActiveSync support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync;

class masStatus {

	// [MS-ASCMD] 2.2.2 Common Status Codes
	// Supported by: 14.0, 14.1, 16.0, 16.1
	const OK	  		= '1';
	const CONTENT		= '101'; // 12.0, or 12.1 is used an HTTP 400 response is returned
	const WBXML			= '102';
	const XML			= '103'; // 12.0, or 12.1 is used an HTTP 400 response is returned
	const DATETIME 		= '104';
	const COMBIDS		= '105';
	const IDS			= '106';
	const MIME 			= '107';
	const DeviceId			= '108';
	const DEVTYPE		= '109';
	const SERVER		= '110'; // 12.0, or 12.1 is used an HTTP 500 response is returned
	const RETRY			= '111'; // 12.0, or 12.1 is used an HTTP 503 response is returned
	const ACCESS 		= '112'; // 12.0, or 12.1 is used an HTTP 403 response is returned
	const QUOTA 		= '113'; // 12.0, or 12.1 is used an HTTP 507 response is returned
	const OFFLINE 		= '114';
	const SENDQUOTA		= '115';
	const RECIP			= '116';
	const NOREPLY 		= '117';
	const SEND 			= '118';
	const NORECIP		= '119';
	const SUBMIT		= '120';
	const REPLYERR		= '121';
	const ATTACHMENT	= '122';
	const MAILBOX 		= '123';
	const ANONYM 		= '124';
	const USER 			= '125'; // 12.0, or 12.1 is used an HTTP 403 response is returned
	const MAS 			= '126'; // 12.0, or 12.1 is used an HTTP 403 response is returned
	const NOSYNC		= '127';
	const MAILYSNC		= '128';
	const DEVSYNC 		= '129';
	const ACTION		= '130';
	const DISABLED 		= '131';
	const DATA			= '132'; // 12.0, or 12.1 is used, an HTTP 403 response for the Provision command,
								 // or an HTTP 500 response is returned instead of this status value
	const DEVLOCK		= '133';
	const DEVSTATE		= '134';
	const EXISTS 		= '135';
	const VERSION 		= '136';
	const COMMAND 		= '137'; // 12.0, or 12.1 is used an HTTP 501 response is returned
	const CMDVER 		= '138'; // 12.0, or 12.1 is used an HTTP 400 response is returned
	const PROVISION 	= '139';
	const WIPEREQUEST 	= '140'; // 12.0, or 12.1 is used an HTTP 403 response is returned
	const NOPROVISION 	= '141'; // 12.0, or 12.1 is used an HTTP 449 response is returned
	const NOTPROISION	= '142'; // 12.0, or 12.1 is used an HTTP 449 response is returned
	const POLREFRESH 	= '143'; // 12.0, or 12.1 is used an HTTP 449 response is returned
	const POLKEY		= '144'; // 12.0, or 12.1 is used an HTTP 449 response is returned
	const EXTMANAGED 	= '145';
	const MEETRECUR		= '146';
	const UNKNOWN 		= '147'; // 12.0, or 12.1 is used an HTTP 400 response is returned
	const NOSSL 		= '148';
	const REQUEST 		= '149';
	const NOTFOUND 		= '150';
	const MAILFOLDER 	= '151';
	const MAILNOFOLDER	= '152';
	const MOVE			= '153';
	const MAILMOVE		= '154';
	const CONVMOVE 		= '155';
	const DESTMOVE 		= '156';
	const RECIPMATCH 	= '160';
	const DISTLIST 		= '161';
	const TRANSIENT 	= '162';
	const AVAIL 		= '163';
	const BODYPART 		= '164';
	const DEVINF 		= '165';
	const ACCID 		= '166';
	const ACCSEND		= '167';
	const IRMDISABLED	= '168';
	const IRMTRANSIENT	= '169';
	const IRMERR		= '170';
	const TEMPLID		= '171';
	const IRMOP 		= '172';
	const NOPIC 		= '173';
	const PICSIZE  		= '174';
	const PICLIMIT 		= '175';
	const CONVSIZE 		= '176';
	const DEVLIMIT 		= '177';
	const SMARTFWD 		= '178';
	const SMARTFWDRD	= '179'; // 16.0, 16.1
	const DNORECIP		= '183'; // 16.0, 16.1
	const EXCEPTION		= '184'; // 16.0, 16.1

	const STAT			= [
		self::OK			=>	'Success',
		self::CONTENT		=> 'The body of the HTTP request sent by theclient is invalid',
		self::WBXML			=> 'The request contains WBXML but it could not be decoded into XML',
		self::XML			=> 'The XML provided in the request does not follow the protocol requirements',
		self::DATETIME		=> 'The request contains a timestamp that could not be parsed into a valid date and time',
		self::COMBIDS		=> 'The request contains a combination of parameters that is invalid',
		self::IDS			=> 'The request contains one or more IDs that could not be parsed into valid values',
		self::MIME			=> 'The request contains MIME that could not be parsed',
		self::DeviceId			=> 'The device ID is either missing or has an invalid format',
		self::DEVTYPE		=> 'The device type is either missing or has an invalid format',
		self::SERVER		=> 'The server encountered an unknown error, the device SHOULD NOT retry later',
		self::RETRY			=> 'The server encountered an unknown error, the device SHOULD NOT retry later',
		self::ACCESS		=> 'The server does not have access to read/write to an object in the directory service',
		self::QUOTA			=> 'The mailbox has reached its size quota',
		self::OFFLINE		=> 'The mailbox server is offline',
		self::SENDQUOTA		=> 'The request would exceed the send quota',
		self::RECIP			=> 'One of the recipients could not be resolved to an email address',
		self::NOREPLY		=> 'The mailbox server will not allow a reply of this message',
		self::SEND			=> 'The message was already sent in a previous request or the request contains a message ID that was already used in a recent message',
		self::NORECIP		=> 'The message being sent contains no recipient',
		self::SUBMIT		=> 'The server failed to submit the message for delivery',
		self::REPLYERR		=> 'The server failed to create a reply message',
		self::ATTACHMENT	=> 'The attachment is too large to be processed by this request',
		self::MAILBOX		=> 'A mailbox could not be found for the user',
		self::ANONYM		=> 'The request was sent without credentials. Anonymous requests are not allowed',
		self::USER			=> 'The user was not found in the directory service',
		self::MAS			=> 'The user object in the directory service indicates that this user is not allowed to use ActiveSync',
		self::NOSYNC		=> 'The server is configured to prevent user\'s from syncing',
		self::MAILYSNC		=> 'The server is configured to prevent user\'s on legacy server\'s from syncing',
		self::DEVSYNC		=> 'The user is configured to allow only some devices to sync. This device is not the allowed device',
		self::ACTION		=> 'The user is not allowed to perform that request',
		self::DISABLED		=> 'The user\'s account is disabled',
		self::DATA			=> 'The server\'s data file that contains the state of the client was unexpectedly missing',
		self::DEVLOCK		=> 'The server\'s data file that contains the state of the client is locked',
		self::DEVSTATE		=> 'The server\'s data file that contains the state of the client appears to be corrupt',
		self::EXISTS		=> 'The server\'s data file that contains the state of the client already exists',
		self::VERSION		=> 'The version of the server\'s data file that contains the state of the client is invalid',
		self::COMMAND		=> 'The version of the server’s data file that contains the state of the client is invalid',
		self::PROVISION		=> 'The device uses a protocol version that cannot send all the policy settings the admin enabled',
		self::WIPEREQUEST	=> 'A remote wipe was requested',
		self::NOPROVISION	=> 'A policy is in place but the device is not provisionable',
		self::NOTPROISION	=> 'There is a policy in place; the device needs to provision',
		self::POLREFRESH	=> 'The policy is configured to be refreshed every few hours',
		self::POLKEY		=> 'The devices policy key is invalid',
		self::EXTMANAGED	=> 'The device claimed to be externally managed, but the server does not allow externally managed devices to sync',
		self::MEETRECUR		=> 'The request tried to forward an occurrence of a meeting that has no recurrence',
		self::UNKNOWN		=> 'The request tried to operate on a type of items unknown to the server',
		self::NOSSL			=> 'The request needs to be proxied to another server but that server does not have SSL enabled',
		self::REQUEST		=> 'The server had stored the previous request from that device. When the device sent an empty request, the server tried to re-execute that previous request but it was found to be impossible',
		self::NOTFOUND		=> 'The value of either the <ItemId> element or the <InstanceId> element specified in the <SmartReply> or the <SmartForward> command request could not be found in the mailbox',
		self::MAILFOLDER	=> 'The mailbox contains too many folders. By default, the mailbox cannot contain more than 1000 folders',
		self::MAILNOFOLDER	=> 'The mailbox contains no folders',
		self::MOVE			=> 'After moving items to the destination folder, some of those items could not be found',
		self::MAILMOVE		=> 'The mailbox server returned an unknown error while moving items',
		self::CONVMOVE		=> 'An <ItemOperations> command request to move a conversation is missing the <MoveAlways> element',
		self::DESTMOVE		=> 'The destination folder for the move is invalid',
		self::RECIPMATCH	=> 'The command has exceeded the maximum number of exactly matched recipients that it can request availability for',
		self::DISTLIST		=> 'The size of the distribution list is larger than the availability service is configured to process',
		self::TRANSIENT		=> 'Availability service request failed with a transient error',
		self::AVAIL			=> 'Availability service request failed with an error',
		self::BODYPART		=> 'The <BodyPartPreference> node (as specified in has an unsupported Type element value',
		self::DEVINF		=> 'The required DeviceInformation element is missing in the Provision request',
		self::ACCID			=> 'The <AccountId> value is not valid',
		self::ACCSEND 		=> 'The <AccountId> value is not valid',
		self::IRMDISABLED	=> 'The Information Rights Management feature is disabled',
		self::IRMTRANSIENT	=> 'Information Rights Management encountered an transient error',
		self::IRMERR		=> 'Information Rights Management encountered an transient error',
		self::TEMPLID		=> 'The Template ID value is not valid',
		self::IRMOP			=> 'Information Rights Management does not support the specified operation',
		self::NOPIC			=> 'The user does not have a contact photo',
		self::PICSIZE		=> 'The contact photo exceeds the size limit set by the <MaxSize> element',
		self::PICLIMIT		=> 'The number of contact photos returned exceeds the size limit set by the <MaxPictures> element',
		self::CONVSIZE		=> 'The conversation is too large to compute the body parts',
		self::DEVLIMIT		=> 'The user\'s account has too many device partnerships',
		self::SMARTFWD		=> 'The SmartForward command request included elements that are not allowed to be combined with either the <Forwardees> element or the <Body> element',
		self::SMARTFWDRD	=> 'The <Forwardees> element or the <Body> element in the <SmartForward> command request could not be parsed',
		self::DNORECIP		=> 'A draft email either has no recipients or has a recipient email address that is not in valid SMTP format',
		self::EXCEPTION		=> 'The server failed to successfully save all of the exceptions specified in a Sync command request to add a calendar series with exceptions',
	];

	/**
	 * 	Get status message
	 *
	 * 	@param	- Status
	 * 	@return	- Description
	 */
	static public function status(string $stat): string {

		return isset(self::MSG[$stat]) ? self::MSG[$stat] : '+++ Status "'.sprintf('%d',$stat).'" not found';
	}

}
