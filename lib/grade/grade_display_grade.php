<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for displayable grade_grades
 *
 * @package   core_grades
 * @category  grade
 * @copyright 2015 Eric Merrill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Class for displayable grade_grades.
 *
 * @package   core_grades
 * @copyright 2015 Eric Merrill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.8.8, 2.9.2
 */
class grade_display_grade extends grade_grade {
    /** @var int The display mode this was initialized to */
    private $displaymode = false;

    /** @var bool Wether this was initilized with hidden items visible */
    private $viewhidden = false;

    // TODO - error on needsupdate?
    // TODO - work without grade_item?

    /**
     * Returns all grade_display_grades for a user in a course.
     *
     * @param int $userid Userid of user to lookup
     * @param int $courseid Courseid of course to lookup
     * @param null|int $displaymode Display mode to use {@see compute_display_grades()}
     * @param bool $viewhidden Wether the user is allowed to view hidden values {@see compute_display_grades()}
     * @return array|false Array of grade_display_grade instances indexed by itemid, or false if none found
     */
    public static function fetch_user_course_grades($userid, $courseid, $displaymode = null, $viewhidden = true) {
        global $DB;

        $output = array();

        $gradeitems = $DB->get_recordset('grade_items', array('courseid' => $courseid));
        foreach ($gradeitems as $item) {
            $grade = new static(array('userid' => $userid, 'itemid' => $item->id),
                                             true,
                                             $displaymode,
                                             $viewhidden);

            $output[$grade->itemid] = $grade;
        }
        $gradeitems->close();

        if (empty($output)) {
            return false;
        } else {
            return $output;
        }
    }

    /**
     * Creates a simple temporary grade for display.
     *
     * @param float $finalgrade The grade value to use
     * @param object $gradeitem The grade item to use
     * @return object An object of type grade_display_grade that can be used to display the grade
     */
    public static function create_temp_grade($finalgrade, $gradeitem) {
        $params = array('finalgrade' => $finalgrade,
                        'rawgrademax' => $gradeitem->grademax,
                        'rawgrademin' => $gradeitem->grademin,
                        'itemid' => $gradeitem->id);
        $grade = new static($params, false);
        $grade->grade_item = $gradeitem;

        return $grade;
    }

    public static function get_formatted_temp_grade($finalgrade, $gradeitem, $localized=true, $displaytype=null, $decimals=null) {
        $grade = self::create_temp_grade($finalgrade, $gradeitem);

        return $grade->get_formatted_grade($localized, $displaytype, $decimals);
    }

    /**
     * Shortcut to get the formatted grade for a grade_grade.
     *
     * @param object $gradegrade A grade_grade object to work with
     * @param bool $localized use localised decimal separator
     * @param int $displaytype type of display. For example GRADE_DISPLAY_TYPE_REAL
     *                                                      GRADE_DISPLAY_TYPE_PERCENTAGE
     *                                                      GRADE_DISPLAY_TYPE_LETTER
     * @param int $decimals The number of decimal places when displaying float values
     * @return string
     */
    public static function get_formatted_grade_grade($gradegrade, $localized=true, $displaytype=null, $decimals=null) {
        // TODO input checking.
        $displaygrade = new static($gradegrade, false);

        return $displaygrade->get_formatted_grade($localized, $displaytype, $decimals);
    }

    /**
     * Returns all grade_display_grades for a user in a course.
     *
     * @param array $params An array with required parameters for this object {@see grade_object::__construct()}
     * @param bool $fetch Wether or not to attempt to fetch record out of the db {@see grade_object::__construct()}
     * @param null|int $displaymode Display mode to use {@see compute_display_grades()}
     * @param bool $viewhidden Wether the user is allowed to view hidden values {@see compute_display_grades()}
     */
    public function __construct($params=null, $fetch=true, $displaymode = null, $viewhidden = true) {
        parent::__construct($params, $fetch);

        $this->compute_display_grades($displaymode, $viewhidden);
    }

    /**
     * Converts this grade_display_grade to one suitable for display based on display mode and permissions.
     *
     * @param int|null $displaymode Report mode to use. For example GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN
     *                                                              GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN
     *                                                              GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN
     *                 If null, no changes will be done.
     * @param bool $viewhidden Wether the user is allowed to view hidden values
     */
    public function compute_display_grades($displaymode = null, $viewhidden = true) {
        global $DB;

        if (!$this->itemid) {
            // If there is no itemid, we can do nothing more.
            return;
        }

        $this->displaymode = $displaymode;
        $this->viewhidden = $viewhidden;

        // A static cache to hold hiding_affected computations.
        static $hidingaffected = null;

        // A static cache record what user we cached hidingaffected for.
        static $cacheuserid = null;

        // A static cache to hold grade_items for the course.
        static $itemscache = null;

        // Load the grade item from cache array, or fetch it.
        if (!isset($this->grade_item)) {
            if (isset($itemscache[$this->itemid])) {
                $this->grade_item = $itemscache[$this->itemid];
            } else {
                $this->load_grade_item();
            }
        }

        // Check if the course has changed, and if so, reset caches.
        if (!empty($itemscache)) {
            if (reset($itemscache)->courseid != $this->grade_item->courseid) {
                $itemscache = null;
                $hidingaffected = null;
            }
        }

        // Update the local min/max with the correct values so we can reliably override them.
        list($this->rawgrademin, $this->rawgrademax) = parent::get_grade_min_and_max();

        // Set this item to have no value if it shouldn't be used in future computations.
        if ($this->is_hidden()) {
            if (!$viewhidden) {
                // This is a hidden grade, make sure the information is blanked out.
                $this->finalgrade = null;
                $this->aggregationstatus = 'unknown';
                $this->aggregationweight = null;
            }

            // If this is a hidden item, we are going to drop it from calculation.
            if ($displaymode == GRADE_REPORT_SHOW_TOTAL_IF_CONTAINS_HIDDEN) {
                $this->aggregationweight = null;
                $this->aggregationstatus = 'dropped';
            }
        }

        // If we are in the "show real totals" display mode, then there is no need to continue.
        if (is_null($displaymode) || $displaymode == GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN) {
            return;
        }

        // We can't go further without a userid.
        if (!$this->userid) {
            // If there is not userid or itemid, we can do nothing more.
            return;
        }

        // If the userid has changed, reset the caches.
        if ($cacheuserid != $this->userid) {
            $cacheuserid = null;
            $hidingaffected = null;
        }

        // If we don't have get_hiding_affected caches, we are going to get it.
        if (empty($hidingaffected)) {
            if ($itemscache === null) {
                $itemscache = grade_item::fetch_all(array('courseid' => $this->grade_item->courseid));
            }
            $grades = array();
            $sql = "SELECT g.*
                      FROM {grade_grades} g
                      JOIN {grade_items} gi ON gi.id = g.itemid
                     WHERE g.userid = :userid AND gi.courseid = :courseid";
            $params = array('userid' => $this->userid, 'courseid' => $this->grade_item->courseid);

            // For each grade record, make a new grade_grade object.
            $gradesrecords = $DB->get_recordset_sql($sql, $params);
            foreach ($gradesrecords as $grade) {
                $grades[$grade->itemid] = new grade_grade($grade, false);
            }
            $gradesrecords->close();

            // Check for any missing grade_grades, and make placeholders.
            foreach ($itemscache as $itemid => $unused) {
                if (!isset($grades[$itemid])) {
                    $gradegrade = new grade_grade();
                    $gradegrade->userid = $this->userid;
                    $gradegrade->itemid = $itemscache[$itemid]->id;
                    $grades[$itemid] = $gradegrade;
                }
                $grades[$itemid]->grade_item =& $itemscache[$itemid];
            }

            // Get the recomputed grades.
            $hidingaffected = grade_grade::get_hiding_affected($grades, $itemscache);
            $cacheuserid = $this->userid;
            unset($grades);
        }

        if (array_key_exists($this->itemid, $hidingaffected['altered']) ||
                array_key_exists($this->itemid, $hidingaffected['alteredgrademin']) ||
                array_key_exists($this->itemid, $hidingaffected['alteredgrademax']) ||
                array_key_exists($this->itemid, $hidingaffected['alteredaggregationstatus']) ||
                array_key_exists($this->itemid, $hidingaffected['alteredaggregationweight'])) {

            // If this item has been altered by hiding, act on it.
            if (($displaymode == GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN) &&
                    array_key_exists($this->itemid, $hidingaffected['altered'])) {
                // Hide the grade if it has been changed by hiding.
                $this->finalgrade = null;
            } else {
                // Update values that were changed by hiding.
                if (array_key_exists($this->itemid, $hidingaffected['altered'])) {
                    $this->finalgrade = $hidingaffected['altered'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredgrademin'])) {
                    $this->rawgrademin = $hidingaffected['alteredgrademin'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredgrademax'])) {
                    $this->rawgrademax = $hidingaffected['alteredgrademax'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredaggregationstatus'])) {
                    $this->aggregationstatus = $hidingaffected['alteredaggregationstatus'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredaggregationweight'])) {
                    $this->aggregationweight = $hidingaffected['alteredaggregationweight'][$this->itemid];
                }

                if ($displaymode == GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN) {
                    // If the course total is hidden we must hide the weight otherwise
                    // it can be used to compute the course total.
                    // TODO - does this make sense here?
                    $this->aggregationstatus = 'unknown';
                    $this->aggregationweight = null;
                }
            }

        } else if (!empty($hidingaffected['unknown'][$this->itemid])) {
            // Not sure whether or not this item depends on a hidden item.
            if ($displaymode == GRADE_REPORT_HIDE_TOTAL_IF_CONTAINS_HIDDEN) {
                // Hide the grade.
                $this->finalgrade = null;
            } else {
                // Update values that were changed by hiding.
                $this->finalgrade = $hidingaffected['unknown'][$this->itemid];

                if (array_key_exists($this->itemid, $hidingaffected['alteredgrademin'])) {
                    $this->rawgrademin = $hidingaffected['alteredgrademin'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredgrademax'])) {
                    $this->rawgrademax = $hidingaffected['alteredgrademax'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredaggregationstatus'])) {
                    $this->aggregationstatus = $hidingaffected['alteredaggregationstatus'][$this->itemid];
                }
                if (array_key_exists($this->itemid, $hidingaffected['alteredaggregationweight'])) {
                    $this->aggregationweight = $hidingaffected['alteredaggregationweight'][$this->itemid];
                }
            }
        }
    }

    /**
     * Finds and returns a grade_grade instance based on params.
     *
     * @param array $params associative arrays varname=>value
     * @return grade_grade Returns a grade_grade instance or false if none found
     */
    public static function fetch($params) {
        return grade_object::fetch_helper('grade_grades', 'grade_display_grade', $params);
    }

    /**
     * Finds and returns all grade_grade instances based on params.
     *
     * @param array $params associative arrays varname=>value
     * @return array array of grade_grade instances or false if none found.
     */
    public static function fetch_all($params) {
        return grade_object::fetch_all_helper('grade_grades', 'grade_display_grade', $params);
    }

    /**
     * Blocks a DB operation from being done with a display object.
     *
     * @param string $source from where was the object inserted (mod/forum, manual, etc.)
     * @return bool success
     */
    public function update($source=null) {
        // Because this is a modified version of grade_grade, meant for display, we should not allow DB mods from here.
        debugging('class grade_display_grade does not support update.', DEBUG_DEVELOPER);

        return false;
    }

    /**
     * Blocks a DB operation from being done with a display object.
     *
     * @param string $source The location the deletion occurred (mod/forum, manual, etc.).
     * @return bool Returns true if the deletion was successful, false otherwise.
     */
    public function delete($source = null) {
        debugging('class grade_display_grade does not support delete.', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Returns the minimum and maximum number of points this grade is graded with respect to.
     *
     * @return array A list containing, in order, the minimum and maximum number of points.
     */
    protected function get_grade_min_and_max() {
        return array($this->rawgrademin, $this->rawgrademax);
    }

    /**
     * Get the formatted string grade for this object.
     *
     * @param bool $localized use localised decimal separator
     * @param int $displaytype type of display. For example GRADE_DISPLAY_TYPE_REAL
     *                                                      GRADE_DISPLAY_TYPE_PERCENTAGE
     *                                                      GRADE_DISPLAY_TYPE_LETTER
     * @param int $decimals The number of decimal places when displaying float values
     * @return string
     */
    public function get_formatted_grade($localized=true, $displaytype=null, $decimals=null) {
        $gradeitem = $this->load_grade_item();

        // No grade.
        if (is_null($this->finalgrade)) {
            return '-';
        }

        if ($gradeitem->gradetype == GRADE_TYPE_NONE or $gradeitem->gradetype == GRADE_TYPE_TEXT) {
            return '';
        }
        if ($gradeitem->gradetype != GRADE_TYPE_VALUE and $gradeitem->gradetype != GRADE_TYPE_SCALE) {
            // We don't know what to do with this type.
            return '';
        }

        if (is_null($displaytype)) {
            // Load default display type.
            $displaytype = $gradeitem->get_displaytype();
        }

        if (is_null($decimals)) {
            // Load default decimals.
            $decimals = $gradeitem->get_decimals();
        }
        // For the various display types, build and return the string.
        switch ($displaytype) {
            case GRADE_DISPLAY_TYPE_REAL:
                return $this->get_formatted_real($decimals, $localized);
            case GRADE_DISPLAY_TYPE_PERCENTAGE:
                return $this->get_formatted_percentage($decimals, $localized);
            case GRADE_DISPLAY_TYPE_LETTER:
                return $this->get_formatted_letter();
            case GRADE_DISPLAY_TYPE_REAL_PERCENTAGE:
                return $this->get_formatted_real($decimals, $localized) . ' (' .
                        $this->get_formatted_percentage($decimals, $localized) . ')';
            case GRADE_DISPLAY_TYPE_REAL_LETTER:
                return $this->get_formatted_real($decimals, $localized) . ' (' .
                        $this->get_formatted_letter() . ')';
            case GRADE_DISPLAY_TYPE_PERCENTAGE_REAL:
                return $this->get_formatted_percentage($decimals, $localized) . ' (' .
                        $this->get_formatted_real($decimals, $localized) . ')';
            case GRADE_DISPLAY_TYPE_LETTER_REAL:
                return $this->get_formatted_letter() . ' (' .
                        $this->get_formatted_real($decimals, $localized) . ')';
            case GRADE_DISPLAY_TYPE_LETTER_PERCENTAGE:
                return $this->get_formatted_letter() . ' (' .
                        $this->get_formatted_percentage($decimals, $localized) . ')';
            case GRADE_DISPLAY_TYPE_PERCENTAGE_LETTER:
                return $this->get_formatted_percentage($decimals, $localized) . ' (' .
                        $this->get_formatted_letter() . ')';
            default:
                return '';
        }
    }

    /**
     * Returns a string representing the range of grademin - grademax for this grade item.
     *
     * @param int $rangesdisplaytype
     * @param int $rangesdecimalpoints
     * @return string
     */
    public function get_formatted_range($rangesdisplaytype=null, $rangesdecimalpoints=null) {
        global $USER;
        $item = $this->load_grade_item();

        // Determine which display type to use for this average
        if (isset($USER->gradeediting) && array_key_exists($item->courseid, $USER->gradeediting)
                && $USER->gradeediting[$item->courseid]) {
            $displaytype = GRADE_DISPLAY_TYPE_REAL;

        } else if ($rangesdisplaytype == GRADE_REPORT_PREFERENCE_INHERIT) { // no ==0 here, please resave report and user prefs
            $displaytype = $item->get_displaytype();
        } else {
            $displaytype = $rangesdisplaytype;
        }

        // Override grade_item setting if a display preference (not default) was set for the averages
        if ($rangesdecimalpoints == GRADE_REPORT_PREFERENCE_INHERIT) {
            $decimalpoints = $item->get_decimals();
        } else {
            $decimalpoints = $rangesdecimalpoints;
        }

        if ($displaytype == GRADE_DISPLAY_TYPE_PERCENTAGE) {
            $grademin = "0 %";
            $grademax = "100 %";
        } else {
            $orginalgrade = $this->finalgrade;

            $this->finalgrade = $this->get_grade_min();
            $grademin = $this->get_formatted_grade(true, $displaytype, $decimalpoints);

            $this->finalgrade = $this->get_grade_max();
            $grademax = $this->get_formatted_grade(true, $displaytype, $decimalpoints);

            $this->finalgrade = $orginalgrade;
        }

        return $grademin.'&ndash;'. $grademax;


    }

    /**
     * Get the formatted percentage grade for this object.
     *
     * @param int $decimals The number of decimal places
     * @param bool $localized use localised decimal separator
     * @return string
     */
    public function get_formatted_percentage($decimals, $localized = true) {
        $percentage = $this->get_percentage();
        if (is_null($percentage)) {
            return '-';
        }
        if ($percentage === false) {
            return '';
        }
        return format_percentage($percentage, $decimals, $localized);
    }

    /**
     * Get the formatted real grade for this object.
     *
     * @param int $decimals The number of decimal places
     * @param bool $localized use localised decimal separator
     * @return string
     */
    public function get_formatted_real($decimals, $localized = true) {
        $this->load_grade_item();
        $value = $this->finalgrade;
        if (is_null($value)) {
            return '-';
        }
        // Handle scales differently.
        if ($this->grade_item->gradetype == GRADE_TYPE_SCALE) {
            if (!$scale = $this->grade_item->load_scale()) {
                return get_string('error');
            }
            $value = $this->bounded_grade($value);
            return format_string($scale->scale_items[$value - 1]);
        } else {
            return format_float($value, $decimals, $localized);
        }
    }

    /**
     * Get the grade letter for this object.
     *
     * @return string
     */
    public function get_formatted_letter() {
        $this->load_grade_item();
        $context = context_course::instance($this->grade_item->courseid, IGNORE_MISSING);
        if (!$letters = grade_get_letters($context)) {
            // We seem to be missing letters in this context.
            return '';
        }
        if (is_null($this->finalgrade)) {
            return '-';
        }
        $percentage = $this->get_percentage();
        $value = bounded_number(0, $percentage, 100); // Just in case.
        foreach ($letters as $boundary => $letter) {
            if ($percentage >= $boundary) {
                return format_string($letter);
            }
        }
        // We didn't find a match.
        return '-';
    }
}

