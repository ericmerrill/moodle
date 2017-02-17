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
 * Classes for converting files between different file formats.
 *
 * @package    core_files
 * @copyright  2017 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_files;

defined('MOODLE_INTERNAL') || die();

use stored_file;

/**
 * Class representing a conversion currently in progress.
 *
 * @package    core_files
 * @copyright  2017 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversion extends \core\persistent {

    /**
     * Status value representing a conversion waiting to start.
     */
    const STATUS_PENDING = 0;

    /**
     * Status value representing a conversion in progress.
     */
    const STATUS_IN_PROGRESS = 1;

    /**
     * Status value representing a successful conversion.
     */
    const STATUS_COMPLETE = 2;

    /**
     * Status value representing a failed conversion.
     */
    const STATUS_FAILED = -1;

    /**
     * Table name for this persistent.
     */
    const TABLE = 'file_conversion';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'sourcefileid' => [
                'type' => PARAM_INT,
            ],
            'targetformat' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => 'pdf',
            ],
            'status' => [
                'type' => PARAM_INT,
                'choices' => [
                    self::STATUS_PENDING,
                    self::STATUS_IN_PROGRESS,
                    self::STATUS_COMPLETE,
                    self::STATUS_FAILED,
                ],
                'default' => self::STATUS_PENDING,
            ],
            'statusmessage' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'converter' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'destfileid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'data' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        );
    }

    /**
     * Fetch all conversions relating to the specified file.
     *
     * Only conversions which have a valid file are returned.
     *
     * @param   stored_file $file The source file being converted
     * @param   string $format The targetforamt to filter to
     * @return  conversion[]
     */
    public static function get_conversions_for_file(stored_file $file, $format) {
        global $DB;

        $sqlfields = self::get_sql_fields('c', 'conversion');
        $sql = "SELECT {$sqlfields}
                FROM {" . self::TABLE . "} c
                INNER JOIN {files} sf ON sf.id = c.sourcefileid
                LEFT JOIN {files} df ON df.id = c.destfileid
                WHERE
                    sf.id = :sourcefileid
                AND c.targetformat = :format
                AND (
                    c.destfileid IS NULL OR df.id IS NOT NULL
                )";
        $records = $DB->get_records_sql($sql, [
            'sourcefileid' => $file->get_id(),
            'format' => $format,
        ]);

        $instances = [];
        foreach ($records as $record) {
            $data = self::extract_record($record, 'conversion');
            $newrecord = new static(0, $data);
            $instances[] = $newrecord;
        }

        return $instances;
    }

    /**
     * Set the source file id for the conversion.
     *
     * @param   stored_file $file The file to convert
     */
    public function set_sourcefile(stored_file $file) {
        $this->raw_set('sourcefileid', $file->get_id());

        return $this;
    }

    /**
     * Fetch the source file.
     *
     * @return  stored_file
     */
    public function get_sourcefile() {
        $fs = get_file_storage();

        return $fs->get_file_by_id($this->get('sourcefileid'));
    }

    /**
     * Set the destination file id for the conversion.
     *
     * @param   stored_file $file The converted file
     * @return  $this
     */
    public function set_destfile(stored_file $file) {
        $this->raw_set('destfileid', $file->get_id());

        return $this;
    }

    /**
     * Get the destination file.
     *
     * @return  stored_file
     */
    public function get_destfile() {
        $fs = get_file_storage();

        return $fs->get_file_by_id($this->get('destfileid'));
    }

    /**
     * Helper to ensure that the returned status is always an int.
     *
     * @return  int
     */
    protected function get_status() {
        return (int) $this->raw_get('status');
    }

    /**
     * Get an instance of the current converter.
     *
     * @return  converter_interface|false
     */
    public function get_converter_instance() {
        $currentconverter = $this->get('converter');

        if ($currentconverter) {
            return new $currentconverter();
        } else {
            return false;
        }
    }

    /**
     * Transform data into a storable format.
     *
     * @param   stdClass $data The data to be stored
     * @return  $this
     */
    protected function set_data($data) {
        $this->raw_set('data', json_encode($data));

        return $this;
    }

    /**
     * Transform data into a storable format.
     *
     * @param   stdClass $data The data to be stored
     * @return  $this
     */
    protected function get_data() {
        $data =$this->raw_get('data');

        if (!empty($data)) {
            return json_decode($data);
        }

        return (object) [];
    }
}
