<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync mail class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsMail extends mimAs {

	const MIME = [

		// note: this is a virtual MIME type (non-existing)
		[ 'application/activesync.mail+xml', 1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// Document source     													Exchange ActiveSync: Email Class Protocol
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
		'AccountId'															=> 'fldAccountId',
		'Attachments'														=> 'fldAttach',
    //  'Attachments/Attachment'												// Handled by fldAttach
    //  'Attachments/Attachment/DisplayName'									// Handled by fldAttach
	//  'Attachments/Attachment/FileReference'									// Handled by fldAttach
    //  'Attachments/Attachment/ClientId'										// Handled by fldAttach
	//  'Attachments/Attachment/Method'											// Handled by fldAttach
    //  'Attachments/Attachment/EstimatedDataSize'								// Handled by fldAttach
    //  'Attachments/Attachment/ContentId'										// Handled by fldAttach
	//  'Attachments/Attachment/ContentLocation'								// Handled by fldAttach
	//  'Attachments/Attachment/IsInline'										// Handled by fldAttach
	//  'Attachments/Attachment/UmAttDuration'									// Handled by fldAttach
	//  'Attachments/Attachment/UmAttOrder'										// Handled by fldAttach
		'Bcc'																=> 'fldMailBcc',
		'Body'																=> 'fldBody',
	//  'Body/EstimatedDataSize'												// Handled by fldBody
    //  'Body/Truncated'														// Handled by fldBody
	//  'Body/Data'																// Handled by fldBody
	//  'Body/Part'																// Handled by fldBody
	//  'Body/Preview'															// Handled by fldBody
	//	'BodyTruncated'															// Handled by fldBody
	//	'BodySize'																// Handled by fldBody
	//	'BodyPart'																// Handled by fldBody
		'Categories'														=> 'fldCategories',
    //  'Categories/Category'													// Handled by fldCategories
		'Cc'																=> 'fldMailCc',
		'ContentClass'														=> 'fldContentClass',
    	'ConversationId'													=> 'fldConversationId',
		'ConversationIndex'													=> 'fldConversationIndex',
		'DateReceived'														=> 'fldCreated',
		'DisplayTo'															=> 'fldMailDisplayTo',
		'Flag' 																=> 'fldFlag',
	//	'Flag/Subject'															// Handled by fldFlag (fldSummary)
	//	'Flag/Status'															// Handled by fldFlag (fldStatus)
	//	'Flag/FlagType'															// Handled by fldFlag
	//	'Flag/DateCompleted'													// Handled by fldFlag (fldCompleted)
	//	'Flag/CompleteTime'														// Handles by fldFlag (fldCompleted)
	//	'Flag/StartDate'														// Handled by fldFlag (fldStartTime)
	//	'Flag/UtcStartDate'														// Handled by fldFlag (fldStartTime)
	//	'Flag/DueDate'															// Handled by fldFlag (fldDueDate)
	//	'Flag/UtcDueDate'														// Handled by fldFlag (fldDueDate)
	//	'Flag/ReminderTime'														// Handled by fldFlag
	//	'Flag/ReminderSet'														// Handled in fldFlag
	//	'Flag/OrdinalDate'														// Handled in fldFlag (fldOrdinal)
	//	'Flag/SubOrdinalDate'													// Handled in fldFlag (fldOrdinalSub)
		'From'																=> 'fldMailFrom',
		'Importance'														=> 'fldImportance',
		'InternetCPID'														=> 'fldInternetCPID',
		'IsDraft'															=> 'fldIsDraft',
		'LastVerbExecuted'													=> 'fldLastVerb',
		'LastVerbExecutionTime'												=> 'fldLastVerbExecuted',
		'MeetingRequest'													=> 'fldMeetingRequest',
	//  'MeetingRequest/AllDayEvent'											// Handled by fldMeetingRequest (fldEndTime)
    //	'MeetingRequest/BusyStatus'												// Handled by fldMeetingRequest (fldBusyStatus)
    //  'MeetingRequest/DisallowNewTimeProposal'								// Handled by fldMeetingRequest (fldDisallowNewProposal)
	//  'MeetingRequest/DtStamp'												// Ignored
	//	'MeetingRequest/EndTime'												// Handled by fldMeetingRequest
	//  'MeetingRequest/Forwardees'												// Handled by fldMeetingRequest (fldMailOther)
	//  'MeetingRequest/GlobalObjId'											// Handled by fldMeetingRequest (fldGlobalId)
	// 	'MeetingRequest/InstanceType'											// Handled by fldMeetingRequest
	//  'MeetingRequest/Location'												// Handler by fldMeetingRequest (fldLocation)
    //  'MeetingRequest/Location/Accuracy'										// Handler by fldMeetingRequest (fldGeoPosition)
	//  'MeetingRequest/Location/Altitude'										// Handler by fldMeetingRequest (fldGeoPosition)
	//  'MeetingRequest/Location/AltitudeAccuracy'								// Handler by fldMeetingRequest (fldGeoPosition)
	//  'MeetingRequest/Location/Annotation'									// Handler by fldMeetingRequest (fldComment)
    //  'MeetingRequest/Location/City'											// Handler by fldMeetingRequest (fldAddressOther)
	//  'MeetingRequest/Location/Country'										// Handler by fldMeetingRequest (fldAddressOther)
	//  'MeetingRequest/Location/DisplayName'									// Handler by fldMeetingRequest
    //  'MeetingRequest/Location/Latitude'										// Handler by fldMeetingRequest (fldGeoPosition)
	//  'MeetingRequest/Location/LocationUri'									// Handler by fldMeetingRequest (fldURLOther)
	//  'MeetingRequest/Location/Longitude'										// Handler by fldMeetingRequest (fldGeoPosition)
	//  'MeetingRequest/Location/PostalCode'									// Handler by fldMeetingRequest (fldAddressOther)
	//  'MeetingRequest/Location/State'											// Handler by fldMeetingRequest (fldAddressOther)
	//  'MeetingRequest/Location/Street'										// Handler by fldMeetingRequest (fldAddressOther)
	//  'MeetingRequest/MeetingMessageType'										// Handled by fldMeetingRequest
    //	'MeetingRequest/Organizer'												// Handled by fldMeetingRequest (fldOrganizer)
	//  'MeetingRequest/ProposedEndTime'										// Handled by fldMeetingRequest (fldEndTimeProposal)
    //  'MeetingRequest/ProposedStartTime'										// Handled by fldMeetingRequest (fldStartTimeProposal)
    // 	'MeetingRequest/RecurrenceId'											// Handled by fldMeetingRequest (fldRecurrenceId)
	// 	'MeetingRequest/Reminder'												// Handled by fldMeetingRequest
	//	'MeetingRequest/Recurrences'											// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/CalendarType'					// Handled by fldMeetingRequest (fldCalendarType)
	//  'MeetingRequest/Recurrences/Recurrence/DayOfMonth'						// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/DayOfWeek'						// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/FirstDayOfWeek'					// Handeld by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/Interval'						// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/IsLeapMonth'						// Handeld by fldMeetingRequest (fldIsLeap)
	//  'MeetingRequest/Recurrences/Recurrence/MonthOfYear'						// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/Occurrences'						// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/Type'							// Handled by fldMeetingRequest
	//  'MeetingRequest/Recurrences/Recurrence/Until'							// Handled by fldMeetingRequest (fldEndTime)
	//  'MeetingRequest/Recurrences/Recurrence/WeekOfMonth'						// Handled by fldMeetingRequest
	// 	'MeetingRequest/ResponseRequested'										// Handled by fldMeetingRequest
	//	'MeetingRequest/Sensitivity'											// Handled by fldMeetingRequest (fldClass)
	//  'MeetingRequest/StartTime'												// Handled by fldMeetingRequest (fldStartTime)
	//	'MeetingRequest/Timezone'												// Handled by fldMeetingRequest (fldTimezone)
	//	'MeetingRequest/Uid'													// Handled by fldMeetingRequest (fldUid)
		'MessageClass'														=> 'fldMessageClass',
	//	'MIMEData'																// Handled by masSync.php
	//	'MIMESize'																// Handled by masSync.php
	//	'MIMETruncated'															// Handled by masSync.php
		'NativeBodyType'													=> 'fldBodyType',
		'Read'																=> 'fldRead',
		'ReceivedAsBcc'														=> 'fldMailReceivedAsBcc',
		'ReplyTo'															=> 'fldMailReplyTo',
	//	'RightsManagementLicense'											=> '',
	//	'Send'																	// Handled by masSync.php
		'Sender'															=> 'fldMailSender',
		'Subject'															=> 'fldSummary',
		'ThreadTopic'														=> 'fldThreadTopic',
		'To'																=> 'fldMailTo',
		'UmCallerID'														=> 'fldUmCallerID',
		'UmUserNotes'														=> 'fldUmNote',

	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	];

    /**
     * 	Singleton instance of object
     * 	@var mimAsMail
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsMail {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::MAIL;

			foreach (self::MAP as $tag => $class) {

				$class = 'syncgw\\document\\field\\'.$class;
			    $class = $class::getInstance();
			    self::$_obj->_map[$tag] = $class;
			}
		}

		return self::$_obj;
	}

}
