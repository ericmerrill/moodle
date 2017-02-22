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
 * Class for converting files between different file formats using unoconv.
 *
 * @package   fileconverter_unoconv_cron
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @author    Eric Merrill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_unoconv_cron;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
use stored_file;

use \core_files\conversion;

/**
 * Class for converting files between different formats using unoconv.
 *
 * @package   fileconverter_unoconv_cron
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @author    Eric Merrill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter extends \fileconverter_unoconv\converter {

    /**
     * Convert a document to a new format and return a conversion object relating to the conversion in progress.
     *
     * @param   conversion $conversion The file to be converted
     * @return  conversion
     */
    public function start_document_conversion(\core_files\conversion $conversion) {
        global $CFG;

        if (!self::are_requirements_met()) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        $file = $conversion->get_sourcefile();

        // Sanity check that the conversion is supported.
        $fromformat = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
        if (!self::is_format_supported($fromformat)) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        $format = $conversion->get('targetformat');
        if (!self::is_format_supported($format)) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        // Update the status to IN_PROGRESS.
        $conversion->set('status', \core_files\conversion::STATUS_IN_PROGRESS);

        // Cleanup.
        return $conversion;
    }

    /**
     * Run all the conversions waiting to be run. Intended to be run from the command line or cron.
     */
    public function process_waiting_conversions() {
        global $DB;

        $params = array('converter' => '\fileconverter_unoconv_cron\converter',
                        'status'    => \core_files\conversion::STATUS_IN_PROGRESS);
        $conversions = \core_files\conversion::get_records($params, 'timecreated');

        foreach ($conversions as $conversion) {
            mtrace("Running conversion ".$conversion->get('id'));
            $this->run_conversion($conversion);
            $conversion->update();
        }
    }

    /**
     * Actually run a conversion using unoconv.
     *
     * @param   conversion $conversion The file to be converted
     * @return  conversion
     */
    protected function run_conversion($conversion) {
        global $CFG;

        if (!self::are_requirements_met()) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        $file = $conversion->get_sourcefile();

        if (empty($file)) {
            $conversion->set('status', conversion::STATUS_FAILED);
            return $conversion;
        }

        // Sanity check that the conversion is supported.
        $fromformat = pathinfo($file->get_filename(), PATHINFO_EXTENSION);

        if (!self::is_format_supported($fromformat)) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        $format = $conversion->get('targetformat');
        if (!self::is_format_supported($format)) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        // Update the status to IN_PROGRESS.
        $conversion->set('status', \core_files\conversion::STATUS_IN_PROGRESS);

        // Copy the file to the tmp dir.
        $uniqdir = make_unique_writable_directory(make_temp_directory('core_file/conversions'));
        \core_shutdown_manager::register_function('remove_dir', array($uniqdir));
        $localfilename = $file->get_id() . '.' . $fromformat;

        $filename = $uniqdir . '/' . $localfilename;
        try {
            // This function can either return false, or throw an exception so we need to handle both.
            if ($file->copy_content_to($filename) === false) {
                throw new file_exception('storedfileproblem', 'Could not copy file contents to temp file.');
            }
        } catch (file_exception $fe) {
            throw $fe;
        }

        // The temporary file to copy into.
        $newtmpfile = pathinfo($filename, PATHINFO_FILENAME) . '.' . $format;
        $newtmpfile = $uniqdir . '/' . clean_param($newtmpfile, PARAM_FILE);

        $cmd = escapeshellcmd(trim($CFG->pathtounoconv)) . ' ' .
               escapeshellarg('-f') . ' ' .
               escapeshellarg($format) . ' ' .
               escapeshellarg('-o') . ' ' .
               escapeshellarg($newtmpfile) . ' ' .
               escapeshellarg($filename);

        $output = null;
        $currentdir = getcwd();
        chdir($uniqdir);
        $result = exec($cmd, $output);
        chdir($currentdir);
        touch($newtmpfile);
        if (filesize($newtmpfile) === 0) {
            return $conversion->set('status', conversion::STATUS_FAILED);
        }

        $context = \context_system::instance();
        $path = '/' . $format . '/';
        $record = [
            'contextid' => $context->id,
            'component' => 'core',
            'filearea'  => 'documentconversion',
            'itemid'    => 0,
            'filepath'  => $path,
            'filename'  => $file->get_contenthash(),
        ];

        $fs = get_file_storage();
        if ($existing = $fs->get_file($context->id, 'core', 'documentconversion', 0, $path, $file->get_contenthash())) {
            $existing->delete();
        }
        $convertedfile = $fs->create_file_from_pathname($record, $newtmpfile);

        $conversion
            ->set_destfile($convertedfile)
            ->set('status', conversion::STATUS_COMPLETE);

        // Cleanup.
        return $conversion;
    }

    public function poll_conversion_status(conversion $conversion) {
        return $conversion;
    }

}
