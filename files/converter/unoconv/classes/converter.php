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
 * @package    convert_unoconv
 * @copyright  2017 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace fileconverter_unoconv;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
use stored_file;

use \core_files\conversion;

/**
 * Class for converting files between different formats using unoconv.
 *
 * @package    convert_unoconv
 * @copyright  2017 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter implements \core_files\converter_interface {

    /** No errors */
    const UNOCONVPATH_OK = 'ok';

    /** Not set */
    const UNOCONVPATH_EMPTY = 'empty';

    /** Does not exist */
    const UNOCONVPATH_DOESNOTEXIST = 'doesnotexist';

    /** Is a dir */
    const UNOCONVPATH_ISDIR = 'isdir';

    /** Not executable */
    const UNOCONVPATH_NOTEXECUTABLE = 'notexecutable';

    /** Test file missing */
    const UNOCONVPATH_NOTESTFILE = 'notestfile';

    /** Version not supported */
    const UNOCONVPATH_VERSIONNOTSUPPORTED = 'versionnotsupported';

    /** Any other error */
    const UNOCONVPATH_ERROR = 'error';


    /**
     * @var bool $requirementsmet Whether requirements have been met.
     */
    protected static $requirementsmet = null;

    /**
     * @var array $formats The list of formats supported by unoconv.
     */
    protected static $formats;

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

    /**
     * Poll an existing conversion for status update.
     *
     * @param   conversion $conversion The file to be converted
     * @return  conversion
     */
    public function poll_conversion_status(conversion $conversion) {
        // Unoconv does not support asynchronous conversions.
        // If we are polling, then something has gone wrong.
        // Fail the conversion.
        $conversion->set('status', conversion::STATUS_FAILED);
        return $conversion;
    }

    /**
     * Generate and serve the test document.
     *
     * @return  stored_file
     */
    public function serve_test_document() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => 'test',
            'filearea' => 'fileconverter_unoconv',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'unoconv_test.docx'
        ];

        // Get the fixture doc file content and generate and stored_file object.
        $fs = get_file_storage();
        $testdocx = $fs->get_file($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
                $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']);

        if (!$testdocx) {
            $fixturefile = dirname(__DIR__) . '/tests/fixtures/unoconv-source.docx';
            $testdocx = $fs->create_file_from_pathname($filerecord, $fixturefile);
        }

        // Convert the doc file to pdf and send it direct to the browser.
        $testfile = $this->start_document_conversi($testdocx, 'pdf', true);
        readfile_accel($testfile, 'application/pdf', true);
    }

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        if (self::$requirementsmet === null) {
            $requirementsmet = self::test_unoconv_path()->status === self::UNOCONVPATH_OK;
            $requirementsmet = $requirementsmet && self::is_minimum_version_met();
            self::$requirementsmet = $requirementsmet;
        }

        return self::$requirementsmet;
    }

    /**
     * Whether the minimum version of unoconv has been met.
     *
     * @return  bool
     */
    protected static function is_minimum_version_met() {
        global $CFG;

        $currentversion = 0;
        $supportedversion = 0.7;
        $unoconvbin = \escapeshellarg($CFG->pathtounoconv);
        $command = "$unoconvbin --version";
        exec($command, $output);

        // If the command execution returned some output, then get the unoconv version.
        if ($output) {
            foreach ($output as $response) {
                if (preg_match('/unoconv (\\d+\\.\\d+)/', $response, $matches)) {
                    $currentversion = (float) $matches[1];
                }
            }
            if ($currentversion < $supportedversion) {
                return false;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the plugin is fully configured.
     *
     * @return  bool
     */
    public static function test_unoconv_path() {
        global $CFG;

        $unoconvpath = $CFG->pathtounoconv;

        $ret = new \stdClass();
        $ret->status = self::UNOCONVPATH_OK;
        $ret->message = null;

        if (empty($unoconvpath)) {
            $ret->status = self::UNOCONVPATH_EMPTY;
            return $ret;
        }
        if (!file_exists($unoconvpath)) {
            $ret->status = self::UNOCONVPATH_DOESNOTEXIST;
            return $ret;
        }
        if (is_dir($unoconvpath)) {
            $ret->status = self::UNOCONVPATH_ISDIR;
            return $ret;
        }
        if (!\file_is_executable($unoconvpath)) {
            $ret->status = self::UNOCONVPATH_NOTEXECUTABLE;
            return $ret;
        }
        if (!self::is_minimum_version_met()) {
            $ret->status = self::UNOCONVPATH_VERSIONNOTSUPPORTED;
            return $ret;
        }

        return $ret;

    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        $formats = self::fetch_supported_formats();

        return self::is_format_supported($from) && self::is_format_supported($to);
    }

    /**
     * Whether the specified file format is supported.
     *
     * @return  bool
     */
    protected static function is_format_supported($format) {
        $formats = self::fetch_supported_formats();

        $format = trim(\core_text::strtolower($format));
        return in_array($format, $formats);
    }

    /**
     * Fetch the list of supported file formats.
     *
     * @return  array
     */
    protected static function fetch_supported_formats() {
        global $CFG;

        if (!isset(self::$formats)) {
            // Ask unoconv for it's list of supported document formats.
            $cmd = escapeshellcmd(trim($CFG->pathtounoconv)) . ' --show';
            $pipes = array();
            $pipesspec = array(2 => array('pipe', 'w'));
            $proc = proc_open($cmd, $pipesspec, $pipes);
            $programoutput = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($proc);
            $matches = array();
            preg_match_all('/\[\.(.*)\]/', $programoutput, $matches);

            $formats = $matches[1];
            self::$formats = array_unique($formats);
        }

        return self::$formats;
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function get_supported_conversions() {
        return implode(', ', self::fetch_supported_formats());
    }
}
