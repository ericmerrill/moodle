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
 * Task for cron if needed.
 *
 * @package   fileconverter_unoconv_cron
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @author    Eric Merrill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_unoconv_cron\task;

defined('MOODLE_INTERNAL') || die();

class convert_files_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('convertfilestask', 'fileconverter_unoconv_cron');
    }

    /**
     * Run forum cron.
     */
    public function execute() {
        $converter = new \fileconverter_unoconv_cron\converter();

        $converter->process_waiting_conversions();
    }

}
