<?php
declare(strict_types=1);

/*
 * 	MIME decoder / encoder for ActiveSync task class
 *
 *	@package	sync*gw
 *	@subpackage	MIME support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\activesync\mime;

use syncgw\lib\DataStore;

class mimAsTask extends mimAs {

	const MIME = [

		// note: this is a virtual MIME type (non-existing)
		[ 'application/activesync.task+xml', 1.0 ],
	];
	const MAP = [
	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	// Document source     													Exchange ActiveSync: Task Class Protocol
    // ----------------------------------------------------------------------------------------------------------------------------------------------------------
        'Body'																=> 'fldBody',
	//  'Body/Type'																// Handled by fldBody
	//  'Body/EstimatedDataSize'												// Handled by fldBody
    //  'Body/Truncated'														// Handled by fldBody
	//  'Body/Data'																// Handled by fldBody
	//  'Body/Part'																// Handled by fldBody
	//  'Body/Preview'															// Handled by fldBody
	//	'BodySize'																// Handled by fldBody
		'Categories'														=> 'fldCategories',
   	//  'Categories/Category'													// Handled by fldCategories
		'Complete'															=> 'fldStatus',
    	'DateCompleted'														=> 'fldCompleted',
    	'DueDate'															=> 'fldDueDate',
	  	'UtcDueDate'														=> 'fldDueDate',
		'Importance'														=> 'fldPriority',
	  	'OrdinalDate'														=> 'fldOrdinal',
	    'Recurrence'														=> 'fldRecurrence',
	//  'Recurrence/CalendarType'												// Handled by fldRecurrence (fldCalendarType)
	//  'Recurrence/DayOfMonth'													// Handled by fldRecurrence
	//  'Recurrence/DayOfWeek'													// Handled by fldRecurrence
	//  'Recurrence/FirstDayOfWeek'												// Handeld by fldRecurrence
    //  'Recurrence/Interval'													// Handled by fldRecurrence
	//  'Recurrence/IsLeapMonth'												// Handeld by fldRecurrence (fldIsLeap)
	//  'Recurrence/MonthOfYear'												// Handled by fldRecurrence
	//  'Recurrence/Occurrences'												// Handled by fldRecurrence
	//  'Recurrence/Type'														// Handled by fldRecurrence
	//  'Recurrence/Until'														// Handled by fldRecurrence (fldEndTime)
	//  'Recurrence/WeekOfMonth'												// Handled by fldRecurrence
	//  'Recurrence/DeadOccur'													// Handled by fldRecurrence
	//  'Recurrence/Start'														// Handled by fldRecurrence (fldStartTime)
	//  'Recurrence/Regenerate'													// Handled by fldRecurrence
	    'ReminderTime'														=> 'fldAlarm',
	//  'ReminderSet'															// Handled in fldTrigger
	    'Sensitivity'														=> 'fldClass',
        'StartDate'															=> 'fldStartTime',
        'UtcStartDate'														=> 'fldStartTime',
		'Subject'															=> 'fldSummary',
		'SubOrdinalDate'													=> 'fldOrdinalSub',
 	// ----------------------------------------------------------------------------------------------------------------------------------------------------------
	];

    /**
     /**
     * 	Singleton instance of object
     * 	@var mimAsTask
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mimAsTask {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_mime = self::MIME;
			self::$_obj->_hid  = DataStore::TASK;

			foreach (self::MAP as $tag => $class) {

				$class = 'syncgw\\document\\field\\'.$class;
			    $class = $class::getInstance();
			    self::$_obj->_map[$tag] = $class;
			}
		}

		return self::$_obj;
	}

}
