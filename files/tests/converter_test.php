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
 * PHPUnit tests for fileconverter API.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * PHPUnit tests for fileconverter API.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_files_converter_testcase extends advanced_testcase {

    /**
     * Get a testable mock of the abstract files_converter class.
     *
     * @param   array   $mockedmethods A list of methods you intend to override
     *                  If no methods are specified, only abstract functions are mocked.
     * @return  \core_files\converter
     */
    protected function get_testable_mock($mockedmethods = []) {
        $converter = $this->getMockBuilder(\core_files\converter::class)
            ->setMethods($mockedmethods)
            ->getMockForAbstractClass();

        return $converter;
    }

    /**
     * Get a testable mock of the abstract files_converter class.
     *
     * @param   array   $mockedmethods A list of methods you intend to override
     *                  If no methods are specified, only abstract functions are mocked.
     * @return  \core_files\converter_interface
     */
    protected function get_mocked_converter($mockedmethods = []) {
        $converter = $this->getMockBuilder(\core_files\converter_interface::class)
            ->setMethods($mockedmethods)
            ->getMockForAbstractClass();

        return $converter;
    }

    /**
     * Helper to create a stored file objectw with the given supplied content.
     *
     * @param   string  $filecontent The content of the mocked file
     * @param   string  $filename The file name to use in the stored_file
     * @param   array   $mockedmethods A list of methods you intend to override
     *                  If no methods are specified, only abstract functions are mocked.
     * @return  stored_file
     */
    protected function get_stored_file($filecontent = 'content', $filename = null, $filerecord = [], $mockedmethods = null) {
        global $CFG;

        $contenthash = sha1($filecontent);
        if (empty($filename)) {
            $filename = $contenthash;
        }

        $filerecord['contenthash'] = $contenthash;
        $filerecord['filesize'] = strlen($filecontent);
        $filerecord['filename'] = $filename;

        $file = $this->getMockBuilder(stored_file::class)
            ->setMethods($mockedmethods)
            ->setConstructorArgs([get_file_storage(), (object) $filerecord])
            ->getMock();

        return $file;
    }

    /**
     * Get a mock of the file_storage API.
     *
     * @param   array   $mockedmethods A list of methods you intend to override
     * @return  file_storage
     */
    protected function get_file_storage_mock($mockedmethods = []) {
        $fs = $this->getMockBuilder(\file_storage::class)
            ->setMethods($mockedmethods)
            ->disableOriginalConstructor()
            ->getMock();

        return $fs;
    }

    /**
     * Test the get_converted_document function.
     */
    public function test_get_converted_document_existing() {
        $returnvalue = (object) [];

        $fs = $this->get_file_storage_mock([
                'get_file',
            ]);
        $fs->method('get_file')->willReturn($returnvalue);

        $converter = $this->get_testable_mock([
                'start_document_conversion',
                'get_file_storage',
            ]);
        $converter->method('get_file_storage')->willReturn($fs);
        $converter->expects($this->never())
            ->method('start_document_conversion');

        $file = $this->get_stored_file();

        $result = $converter->get_converted_document($file, 'pdf');
        $this->assertEquals($returnvalue, $result);
    }

    /**
     * Test the get_converted_document function.
     */
    public function test_get_converted_document_no_existing() {

        $returnvalue = (object) [];

        $fs = $this->get_file_storage_mock([
                'get_file',
            ]);
        $fs->method('get_file')->willReturn(false);

        $converter = $this->get_testable_mock([
                'start_document_conversion',
                'get_file_storage',
            ]);
        $converter->method('get_file_storage')->willReturn($fs);
        $converter->expects($this->once())
            ->method('start_document_conversion')
            ->willReturn($returnvalue);

        $file = $this->get_stored_file();

        $result = $converter->get_converted_document($file, 'pdf', false);
        $this->assertEquals($returnvalue, $result);
    }

    /**
     * Test the get_converted_document function.
     */
    public function test_get_converted_document_no_existing_forcerefresh() {
        $returnvalue = (object) [];

        $fs = $this->get_file_storage_mock([
                'get_file',
            ]);
        $fs->method('get_file')->willReturn(false);

        $converter = $this->get_testable_mock([
                'start_document_conversion',
                'get_file_storage',
            ]);
        $converter->method('get_file_storage')->willReturn($fs);
        $converter->expects($this->once())
            ->method('start_document_conversion')
            ->willReturn($returnvalue);

        $file = $this->get_stored_file();

        $result = $converter->get_converted_document($file, 'pdf', true);
        $this->assertEquals($returnvalue, $result);
    }

    /**
     * Test the get_converted_document function.
     */
    public function test_get_converted_document_no_existing_fail() {
        $returnvalue = (object) [];

        $fs = $this->get_file_storage_mock([
                'get_file',
            ]);
        $fs->method('get_file')->willReturn(false);

        $converter = $this->get_testable_mock([
                'start_document_conversion',
                'get_file_storage',
            ]);
        $converter->method('get_file_storage')->willReturn($fs);
        $converter->expects($this->once())
            ->method('start_document_conversion')
            ->willReturn(false);

        $file = $this->get_stored_file();
        $result = $converter->get_converted_document($file, 'pdf', true);
        $this->assertFalse($result);
    }

    /**
     * Test the get_converted_document function.
     */
    public function test_get_converted_document_existing_forcerefresh() {
        $returnfile = $this->get_stored_file('content', null, [], [
                'delete',
            ]);
        $returnvalue = (object) [];

        $fs = $this->get_file_storage_mock([
                'get_file',
            ]);
        $fs->method('get_file')->willReturn($returnfile);

        $converter = $this->get_testable_mock([
                'start_document_conversion',
                'get_file_storage',
            ]);
        $converter->method('get_file_storage')->willReturn($fs);
        $converter->expects($this->once())
            ->method('start_document_conversion')
            ->willReturn($returnvalue);

        $returnfile->expects($this->once())
            ->method('delete');

        $file = $this->get_stored_file();

        $result = $converter->get_converted_document($file, 'pdf', true);
        $this->assertEquals($returnvalue, $result);
    }

    /**
     * Test the get_document_converter_classes function with no enabled plugins.
     */
    public function test_get_document_converter_classes_no_plugins() {
        $converter = $this->get_testable_mock(['get_enabled_plugins']);
        $converter->method('get_enabled_plugins')->willReturn([]);

        $method = new ReflectionMethod(\core_files\converter::class, 'get_document_converter_classes');
        $method->setAccessible(true);
        $result = $method->invokeArgs($converter, ['docx', 'pdf']);
        $this->assertEmpty($result);
    }

    /**
     * Test the get_document_converter_classes function when no class was found.
     */
    public function test_get_document_converter_classes_plugin_class_not_found() {
        $converter = $this->get_testable_mock(['get_enabled_plugins']);
        $converter->method('get_enabled_plugins')->willReturn([
                'noplugin' => '\not\a\real\plugin',
            ]);

        $method = new ReflectionMethod(\core_files\converter::class, 'get_document_converter_classes');
        $method->setAccessible(true);
        $result = $method->invokeArgs($converter, ['docx', 'pdf']);
        $this->assertEmpty($result);
    }

    /**
     * Test the get_document_converter_classes function when the returned classes do not meet requirements.
     */
    public function test_get_document_converter_classes_plugin_class_requirements_not_met() {
        $plugin = $this->getMockBuilder(\core_file_converter_requirements_not_met_test::class)
            ->setMethods()
            ->getMock();

        $converter = $this->get_testable_mock(['get_enabled_plugins']);
        $converter->method('get_enabled_plugins')->willReturn([
                'test_plugin' => get_class($plugin),
            ]);

        $method = new ReflectionMethod(\core_files\converter::class, 'get_document_converter_classes');
        $method->setAccessible(true);
        $result = $method->invokeArgs($converter, ['docx', 'pdf']);
        $this->assertEmpty($result);
    }

    /**
     * Test the get_document_converter_classes function when the returned classes do not meet requirements.
     */
    public function test_get_document_converter_classes_plugin_class_met_not_supported() {
        $plugin = $this->getMockBuilder(\core_file_converter_type_not_supported_test::class)
            ->setMethods()
            ->getMock();

        $converter = $this->get_testable_mock(['get_enabled_plugins']);
        $converter->method('get_enabled_plugins')->willReturn([
                'test_plugin' => get_class($plugin),
            ]);

        $method = new ReflectionMethod(\core_files\converter::class, 'get_document_converter_classes');
        $method->setAccessible(true);
        $result = $method->invokeArgs($converter, ['docx', 'pdf']);
        $this->assertEmpty($result);
    }

    /**
     * Test the get_document_converter_classes function when the returned classes do not meet requirements.
     */
    public function test_get_document_converter_classes_plugin_class_met_and_supported() {
        $plugin = $this->getMockBuilder(\core_file_converter_type_supported_test::class)
            ->setMethods()
            ->getMock();
        $classname = get_class($plugin);

        $converter = $this->get_testable_mock(['get_enabled_plugins']);
        $converter->method('get_enabled_plugins')->willReturn([
                'test_plugin' => $classname,
            ]);

        $method = new ReflectionMethod(\core_files\converter::class, 'get_document_converter_classes');
        $method->setAccessible(true);
        $result = $method->invokeArgs($converter, ['docx', 'pdf']);
        $this->assertCount(1, $result);
        $this->assertNotFalse(array_search($classname, $result));
    }

    /**
     * Test the can_convert_storedfile_to function with a directory.
     */
    public function test_can_convert_storedfile_to_directory() {
        $converter = $this->get_testable_mock();

        // A file with filename '.' is a directory.
        $file = $this->get_stored_file('', '.');

        $this->assertFalse($converter->can_convert_storedfile_to($file, 'target'));
    }

    /**
     * Test the can_convert_storedfile_to function with an empty file.
     */
    public function test_can_convert_storedfile_to_emptyfile() {
        $converter = $this->get_testable_mock();

        // A file with filename '.' is a directory.
        $file = $this->get_stored_file('');

        $this->assertFalse($converter->can_convert_storedfile_to($file, 'target'));
    }

    /**
     * Test the can_convert_storedfile_to function with a file with indistinguished mimetype.
     */
    public function test_can_convert_storedfile_to_no_mimetype() {
        $converter = $this->get_testable_mock();

        // A file with filename '.' is a directory.
        $file = $this->get_stored_file('example content', 'example', [
                'mimetype' => null,
            ]);

        $this->assertFalse($converter->can_convert_storedfile_to($file, 'target'));
    }

    /**
     * Test the can_convert_storedfile_to function with a file with indistinguished mimetype.
     */
    public function test_can_convert_storedfile_to_docx() {
        $returnvalue = (object) [];

        $converter = $this->get_testable_mock([
                'can_convert_format_to'
            ]);

        $types = \core_filetypes::get_types();

        // A file with filename '.' is a directory.
        $file = $this->get_stored_file('example content', 'example', [
                'mimetype' => $types['docx']['type'],
            ]);

        $converter->expects($this->once())
            ->method('can_convert_format_to')
            ->willReturn($returnvalue);

        $result = $converter->can_convert_storedfile_to($file, 'target');
        $this->assertEquals($returnvalue, $result);
     }

    /**
     * Test the can_convert_format_to function.
     */
    public function test_can_convert_format_to_found() {
        $converter = $this->get_testable_mock(['get_document_converter_classes']);

        $mock = $this->get_mocked_converter();

        $converter->method('get_document_converter_classes')
            ->willReturn([$mock]);

        $result = $converter->can_convert_format_to('from', 'to');
        $this->assertTrue($result);
    }

    /**
     * Test the can_convert_format_to function.
     */
    public function test_can_convert_format_to_not_found() {
        $converter = $this->get_testable_mock(['get_document_converter_classes']);

        $converter->method('get_document_converter_classes')
            ->willReturn([]);

        $result = $converter->can_convert_format_to('from', 'to');
        $this->assertFalse($result);
    }

    /**
     * Test the can_convert_storedfile_to function with an empty file.
     */
    public function test_start_document_conversion_none_supported() {
        $converter = $this->get_testable_mock([
                'get_document_converter_classes',
            ]);

        $converter->method('get_document_converter_classes')->willReturn([]);
        $file = $this->get_stored_file('example content', 'example', [
                'mimetype' => null,
            ]);

        $this->assertFalse($converter->start_document_conversion($file, 'target'));
    }

    /**
     * Test the start_document_conversion with a single converter which succeeds.
     */
    public function test_start_document_conversion_one_supported_success() {
        $converter = $this->get_testable_mock([
                'get_document_converter_classes',
            ]);

        $converter->method('get_document_converter_classes')
            ->willReturn([\core_file_converter_type_successful::class]);

        $file = $this->get_stored_file('example content', 'example', [
                'mimetype' => null,
            ]);

        $this->assertEquals('success', $converter->start_document_conversion($file, 'target'));
    }

    /**
     * Test the start_document_conversion with a single converter which failes.
     */
    public function test_start_document_conversion_one_supported_failure() {
        $converter = $this->get_testable_mock([
                'get_document_converter_classes',
            ]);

        $mock = $this->get_mocked_converter(['start_document_conversion']);
        $converter->method('get_document_converter_classes')
            ->willReturn([\core_file_converter_type_failed::class]);

        $file = $this->get_stored_file('example content', 'example', [
                'mimetype' => null,
            ]);

        $this->assertFalse($converter->start_document_conversion($file, 'target'));
    }

    /**
     * Test the start_document_conversion with two converters - fail, then succeed.
     */
    public function test_start_document_conversion_two_supported() {
        $converter = $this->get_testable_mock([
                'get_document_converter_classes',
            ]);

        $mock = $this->get_mocked_converter(['start_document_conversion']);
        $converter->method('get_document_converter_classes')
            ->willReturn([
                \core_file_converter_type_failed::class,
                \core_file_converter_type_successful::class,
            ]);

        $file = $this->get_stored_file('example content', 'example', [
                'mimetype' => null,
            ]);

        $this->assertEquals('success', $converter->start_document_conversion($file, 'target'));
    }
}

/**
 * Test class for converter support with requirements are not met.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_file_converter_requirements_not_met_test implements \core_files\converter_interface {

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        return false;
    }

    /**
     * Convert a document to a new format and return a new stored_file matching this doc.
     *
     * @param   stored_file $file The file to be converted
     * @param   string $format The target file format
     * @return  stored_file
     */
    public function start_document_conversion(stored_file $file, $format) {
    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        return false;
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function  get_supported_conversions() {
        return [];
    }
}

/**
 * Test class for converter support with requirements met and conversion not supported.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_file_converter_type_not_supported_test implements \core_files\converter_interface {

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        return true;
    }

    /**
     * Convert a document to a new format and return a new stored_file matching this doc.
     *
     * @param   stored_file $file The file to be converted
     * @param   string $format The target file format
     * @return  stored_file
     */
    public function start_document_conversion(stored_file $file, $format) {
    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        return false;
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function  get_supported_conversions() {
        return [];
    }
}

/**
 * Test class for converter support with requirements met and conversion supported.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_file_converter_type_supported_test implements \core_files\converter_interface {

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        return true;
    }

    /**
     * Convert a document to a new format and return a new stored_file matching this doc.
     *
     * @param   stored_file $file The file to be converted
     * @param   string $format The target file format
     * @return  stored_file
     */
    public function start_document_conversion(stored_file $file, $format) {
    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        return true;
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function  get_supported_conversions() {
        return [];
    }
}

/**
 * Test class for converter support with requirements met and successful conversion.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_file_converter_type_successful implements \core_files\converter_interface {

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        return true;
    }

    /**
     * Convert a document to a new format and return a new stored_file matching this doc.
     *
     * @param   stored_file $file The file to be converted
     * @param   string $format The target file format
     * @return  stored_file
     */
    public function start_document_conversion(stored_file $file, $format) {
        return 'success';
    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        return true;
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function  get_supported_conversions() {
        return [];
    }
}

/**
 * Test class for converter support with requirements met and failed conversion.
 *
 * @package    core_files
 * @copyright  2017 Andrew nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_file_converter_type_failed implements \core_files\converter_interface {

    /**
     * Whether the plugin is configured and requirements are met.
     *
     * @return  bool
     */
    public static function are_requirements_met() {
        return true;
    }

    /**
     * Convert a document to a new format and return a new stored_file matching this doc.
     *
     * @param   stored_file $file The file to be converted
     * @param   string $format The target file format
     * @return  stored_file
     */
    public function start_document_conversion(stored_file $file, $format) {
        return false;
    }

    /**
     * Whether a file conversion can be completed using this converter.
     *
     * @param   string $from The source type
     * @param   string $to The destination type
     * @return  bool
     */
    public static function supports($from, $to) {
        return true;
    }

    /**
     * A list of the supported conversions.
     *
     * @return  string
     */
    public function  get_supported_conversions() {
        return [];
    }
}
