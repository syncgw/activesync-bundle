<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync calendar class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsCalendar extends mimAs {

	const MIME = [

		// note: this is a virtual MIME type (non-existing)
		[ 'application/activesync.calendar+xml', 1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// Document source     													Exchange ActiveSync: Calendar Class Protocol
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
	//  'AllDayEvent'														   	// Handled by fldEndTime
		'AppointmentReplyTime'												=> 'fldAppointmentReply',
		'Attachments'														=> 'fldAttach', 	// Warning: Not part of [MS-ACAL]
		'Attendees'															=> 'fldAttendee',
    //  'Attendees/Attendee'													// Handled by fldAttendee
	//  'Attendees/Attendee/AttendeeStatus'										// Handled by fldAttendee
	//  'Attendees/Attendee/AttendeeType'										// Handled by fldAttendee
    //  'ResponseRequested'														// Handled by fldAttendee
    //  'Attendees/Attendee/Email'												// Handled by fldAttendee
	//  'Attendees/Attendee/Name'												// Handled by fldAttendee
    //  'Attendees/Attendee/ProposedStartTime'									// Handled by fldAttendee
	//  'Attendees/Attendee/ProposedEndTime'									// Handled by fldAttendee
	    'Body'																=> 'fldBody',
	//  'Body/Type'																// Handled by fldBody
	//  'Body/EstimatedDataSize'												// Handled by fldBody
    //  'Body/Truncated'														// Handled by fldBody
	//  'Body/Data'																// Handled by fldBody
	//  'Body/Part'																// Handled by fldBody
	//  'Body/Preview'															// Handled by fldBody
	//	'BodyTruncated'															// Handled by fldBody
	//	'BodySize'																// Handled by fldBody
		'BusyStatus'														=> 'fldBusyStatus',
	    'Categories'														=> 'fldCategories',
    //  'Categories/Category'													// Handled by fldCategories
    	'ClientUid'															=> 'fldClientUid',
	    'DisallowNewTimeProposal'											=> 'fldDisallowNewProposal',
	//  'DtStamp'																// Ignored
        'StartTime'															=> 'fldStartTime',
		'EndTime'															=> 'fldEndTime',
		'Exceptions'														=> 'fldExceptions',
    //  'Exceptions/Exception'													// Handled by fldExceptions
	//  'Exceptions/Exception/AllDayEvent'										// Handled by fldExceptions (fldEndTime)
	//  'Exceptions/Exception/AppointmentReplyTime'								// Handler by fldExceptions (fldAppointmentReply)
	//  'Exceptions/Exception/Attachments'										// Handler by fldExceptions (fldAttach)
    //  'Exceptions/Exception/Attachments/DisplayName'							// Handler by fldExceptions (fldAttach)
    //  'Exceptions/Exception/Attachments/FileReference'						// Handler by fldExceptions (fldAttach)
    //  'Exceptions/Exception/Attachments/ClientId'								// Handler by fldExceptions (fldAttach)
    //  'Exceptions/Exception/Attachments/Method'								// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attachments/EstimatedDataSize'					// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attachments/ContentId'							// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attachments/ContentLocation'						// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attachments/IsInline'								// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attachments/UmAttDuration'						// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attachments/UmAttOrder'							// Handler by fldExceptions (fldAttach)
	//  'Exceptions/Exception/Attendees'										// Handler by fldExceptions (fldAttendee)
	//  'Exceptions/Exception/Attendee'											// Handled by fldExceptions (fldAttendee)
	//  'Exceptions/Exception/Attendees/Attendee/AttendeeStatus'				// Handled by fldExceptions (fldAttendee)
	//  'Exceptions/Exception/Attendees/Attendee/AttendeeType'					// Handled by fldExceptions (fldAttendee)
	//  'Exceptions/Exception/Body'												// Handler by fldExceptions (fldBody)
   	//  'Exceptions/Exception/BusyStatus'										// Handler by fldExceptions (fldBusyStatus)
	//  'Exceptions/Exception/Categories'										// Handler by fldExceptions (fldCategories)
    //  'Exceptions/Exception/Categories/Category'								// Handler by fldExceptions (fldCategories)
    //  'Exceptions/Exception/Deleted'											// Handler by fldExceptions
    //  'Exceptions/Exception/DtStamp'											// Ignored
    //  'Exceptions/Exception/EndTime'											// Handled by fldExceptions (fldEndTime)
    //  'Exceptions/Exception/ExceptionStartTime'								// Handled by fldExceptions (fldStartTimeException)
    //  'Exceptions/Exception/InstanceId'										// Handler by fldExceptions
    //  'Exceptions/Exception/Location'											// Handler by fldExceptions (fldLocation)
    //  'Exceptions/Exception/Location/DisplayName'								// Handler by fldExceptions (fldLocation)
    //  'Exceptions/Exception/Location/Annotation'								// Handler by fldExceptions (fldComment)
    //  'Exceptions/Exception/Location/Street'									// Handler by fldExceptions (fldAddressOther)
    //  'Exceptions/Exception/Location/City'									// Handler by fldExceptions (fldAddressOther)
	//  'Exceptions/Exception/Location/State'									// Handler by fldExceptions (fldAddressOther)
	//  'Exceptions/Exception/Location/Country'									// Handler by fldExceptions (fldAddressOther)
	//  'Exceptions/Exception/Location/PostalCode'								// Handler by fldExceptions (fldAddressOther)
	//  'Exceptions/Exception/Location/Longitude'								// Handler by fldExceptions (fldGeoPosition)
	//  'Exceptions/Exception/Location/Latitude'								// Handler by fldExceptions (fldGeoPosition)
	//  'Exceptions/Exception/Location/Accuracy'								// Handler by fldExceptions (fldGeoPosition)
	//  'Exceptions/Exception/Location/Altitude'								// Handler by fldExceptions (fldGeoPosition)
	//  'Exceptions/Exception/Location/AltitudeAccuracy'						// Handler by fldExceptions (fldGeoPosition)
	//  'Exceptions/Exception/Location/LocationUri'								// Handler by fldExceptions (fldURLOther)
	//  'Exceptions/Exception/MeetingStatus'									// Handler by fldExceptions (fldMeetingStatus)
    //  'Exceptions/Exception/NativeBodyType'									// Handler by fldExceptions (fldBodyType)
    //  'Exceptions/Exception/OnlineMeetingConfLink'							// Handler by fldExceptions (fldConference)
    //  'Exceptions/Exception/OnlineMeetingExternalLink'						// Handler by fldExceptions (fldConferenceExt)
    //  'Exceptions/Exception/Reminder'											// Handler by fldExceptions (fldAlarm)
    //  'Exceptions/Exception/ResponseType'										// Handler by fldExceptions (fldRType)
	//  'Exceptions/Exception/Sensitivity'										// Handler by fldExceptions (fldClass)
    //  'Exceptions/Exception/StartTime'										// Handled by fldExceptions (fldStartTime)
	//  'Exceptions/Exception/Subject'											// Handler by fldExceptions (fldSummary)
	//  'Exceptions/Exception/UID'												// Handler by fldExceptions (fldUid)
        'Location'															=> 'fldLocation',
	//  'Location/Accuracy'														// Handler by fldExceptions (fldGeoPosition)
	//  'Location/Altitude'														// Handler by fldExceptions (fldGeoPosition)
	//  'Location/AltitudeAccuracy'												// Handler by fldExceptions (fldGeoPosition)
	//  'Location/Annotation'													// Handler by fldExceptions (fldComment)
	//  'Location/City'															// Handler by fldExceptions (fldAddressOther)
	//  'Location/Country'														// Handler by fldExceptions (fldAddressOther)
	//  'Location/DisplayName'													// Handler by fldExceptions
	//  'Location/Latitude'														// Handler by fldExceptions (fldGeoPosition)
	//  'Location/LocationUri'													// Handler by fldExceptions (fldURLOther)
	//  'Location/Longitude'													// Handler by fldExceptions (fldGeoPosition)
	//  'Location/PostalCode'													// Handler by fldExceptions (fldAddressOther)
	//  'Location/State'														// Handler by fldExceptions (fldAddressOther)
	//  'Location/Street'														// Handler by fldExceptions (fldAddressOther)
		'MeetingStatus'														=> 'fldMeetingStatus',
    	'NativeBodyType'													=> 'fldBodyType',
	    'OnlineMeetingConfLink'												=> 'fldConference',
        'OnlineMeetingExternalLink'											=> 'fldConferenceExt',
	    'OrganizerName'														=> 'fldFullName',
	    'OrganizerEmail'													=> 'fldMailOther',
	    'Recurrence'														=> 'fldRecurrence',
	//  'Recurrences/Recurrence/CalendarType'									// Handled by fldRecurrence (fldCalendarType)
	//  'Recurrences/Recurrence/DayOfMonth'										// Handled by fldRecurrence
	//  'Recurrences/Recurrence/DayOfWeek'										// Handled by fldRecurrence
	//  'Recurrences/Recurrence/FirstDayOfWeek'									// Handeld by fldRecurrence
	//  'Recurrences/Recurrence/Interval'										// Handled by fldRecurrence
	//  'Recurrences/Recurrence/IsLeapMonth'									// Handeld by fldRecurrence (fldIsLeap)
	//  'Recurrences/Recurrence/MonthOfYear'									// Handled by fldRecurrence
	//  'Recurrences/Recurrence/Occurrences'									// Handled by fldRecurrence
	//  'Recurrences/Recurrence/Type'											// Handled by fldRecurrence
	//  'Recurrences/Recurrence/Until'											// Handled by fldRecurrence (fldEndTime)
	//  'Recurrences/Recurrence/WeekOfMonth'									// Handled by fldRecurrence
	  	'ResponseType'														=> 'fldRType',
		'Reminder'															=> 'fldAlarm',
	    'Sensitivity'														=> 'fldClass',
	    'Subject'															=> 'fldSummary',
		'Timezone'															=> 'fldTimezone',
	    'UID'																=> 'fldUid',
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	];

    /**
     * 	Singleton instance of object
     * 	@var mimAsCalendar
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsCalendar {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::CALENDAR;

			foreach (self::MAP as $tag => $class) {

				$class = 'syncgw\\document\\field\\'.$class;
			    $class = $class::getInstance();
			    self::$_obj->_map[$tag] = $class;
			}
		}

		return self::$_obj;
	}

}
