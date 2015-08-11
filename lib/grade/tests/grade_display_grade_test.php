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
 * @category  phpunit
 * @copyright 2015 Eric Merrill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/fixtures/lib.php');


class core_grade_display_grade_testcase extends grade_base_testcase {

    public function test_grade_display_grade() {
        $this->setUp();
        $this->sub_test_fetch();
        $this->sub_test_fetch_all();

        $this->sub_test_update_delete();
        $this->sub_test_get_grade_min_and_max();
    }

    protected function sub_test_fetch() {
        $grade = grade_display_grade::fetch(array('id' => $this->grade_grades[0]->id));

        $this->assertInstanceOf('grade_display_grade', $grade);
    }

    protected function sub_test_fetch_all() {
        $grades = grade_display_grade::fetch_all(array('itemid' => $this->grade_items[0]->id));

        foreach ($grades as $grade) {
            $this->assertInstanceOf('grade_display_grade', $grade);
        }
    }

    public function test_fetch_user_course_grades() {

    }

    public function test_create_temp_grade() {

    }

    public function test_get_formatted_temp_grade() {

    }

    public function test_get_formatted_grade_grade() {

    }

    public function test_construct() {

    }

    public function test_compute_display_grade() {

    }

    protected function sub_test_update_delete() {
        $grade = grade_display_grade::fetch(array('id' => $this->grade_grades[0]->id));

        $this->assertFalse($grade->update());
        $this->assertDebuggingCalled();

        $this->assertFalse($grade->delete());
        $this->assertDebuggingCalled();
    }

    protected function sub_test_get_grade_min_and_max() {
        global $CFG;


    }

    public function test_get_formatted_grade() {

    }

}
