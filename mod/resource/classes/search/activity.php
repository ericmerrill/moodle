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
 * Search area for mod_resource activities.
 *
 * @package    mod_resource
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_resource\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for mod_resource activities.
 *
 * @package    mod_resource
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity extends \core_search\area\base_activity {
    /**
     * Returns true if this area supports file indexing.
     *
     * @return bool
     */
    public function supports_file_indexing() {
        return true;
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
        return true;
    }

    /**
     * Add the main file to the index.
     *
     * @param document $document The current document
     * @param stdClass $record The db record for the current document
     * @return null
     */
    protected function attach_files($document, $record) {
        $fs = get_file_storage();

        $cm = $this->get_cm($this->get_module_name(), $record->id, $record->course);
        $context = \context_module::instance($cm->id);

        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);

        // Only index the main file (sort order of 1).
        $mainfile = $files ? reset($files) : null;

        if ($mainfile->get_sortorder() > 0) {
            $document->add_stored_file($mainfile);
        }
    }

}
