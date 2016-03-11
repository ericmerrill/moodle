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
 * Document representation.
 *
 * @package    search_solr
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace search_solr;

defined('MOODLE_INTERNAL') || die();

/**
 * Respresents a document to index.
 *
 * @copyright  2015 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document extends \core_search\document {

    /**
     * Any fields that are engine specifc. These are fields that are solely used by a seach engine plugin
     * for internal purposes.
     *
     * Uses same format as other fields above.
     *
     * @var array
     */
    protected static $enginefields = array(
        'solr_filegroupingid' => array(
            'type' => 'string',
            'stored' => true,
            'indexed' => true
        )
    );

    /**
     * Formats the timestamp according to the search engine needs.
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_time_for_engine($timestamp) {
        return gmdate(\search_solr\engine::DATE_FORMAT, $timestamp);
    }

    /**
     * Formats the timestamp according to the search engine needs.
     *
     * @param int $timestamp
     * @return string
     */
    public static function format_string_for_engine($string) {
        // 2^15 default. We could convert this to a setting as is possible to
        // change the max in solr.
        return \core_text::str_max_bytes($string, 32766);
    }

    /**
     * Returns a timestamp from the value stored in the search engine.
     *
     * @param string $time
     * @return int
     */
    public static function import_time_from_engine($time) {
        return strtotime($time);
    }

    /**
     * Overwritten to use markdown format as we use markdown for solr highlighting.
     *
     * @return int
     */
    protected function get_text_format() {
        return FORMAT_MARKDOWN;
    }

    /**
     * Apply any defaults to unset fields before export. Called after document building, but before export.
     *
     * Sub-classes of this should make sure to call parent::apply_defaults().
     */
    protected function apply_defaults() {
        parent::apply_defaults();

        // We want to set the solr_filegroupingid to id if it isn't set.
        if (!isset($this->data['solr_filegroupingid'])) {
            $this->data['solr_filegroupingid'] = $this->data['id'];
        }
    }

    /**
     * Export the data for the given file in relation to this document.
     *
     * @param \stored_file $file The stored file we are talking about.
     * @return array
     */
    public function export_file_for_engine($file) {
        $data = $this->export_for_engine();

        // Going to append the fileid to give it a unique id.
        $data['id'] = $data['id'].'-file'.$file->get_id();
        $data['type'] = \core_search\manager::TYPE_FILE;

        // Truncating long description strings, they are being passed by URL.
        // They are going to be indexed in the main record anyways.
        // Content needs to be moved to tmpcontent, or it will be overwritten by the file.
        $data['tmpcontent'] = \core_text::substr($data['content'], 0, 256);
        unset($data['content']);

        if (isset($data['description1'])) {
            $data['description1'] = \core_text::substr($data['description1'], 0, 256);
        }
        if (isset($data['description2'])) {
            $data['description2'] = \core_text::substr($data['description2'], 0, 256);
        }

        // Some additional data that may be useful.
        $data['fileid'] = $file->get_id();
        $data['filename'] = $file->get_filename();

        return $data;
    }
}
