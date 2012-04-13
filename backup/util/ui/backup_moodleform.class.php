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
 * This file contains the generic moodleform bridge for the backup user interface
 * as well as the individual forms that relate to the different stages the user
 * interface can exist within.
 *
 * @package   moodlecore
 * @copyright 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup moodleform bridge
 *
 * Ahhh the mighty moodleform bridge! Strong enough to take the weight of 682 full
 * grown african swallows all of whom have been carring coconuts for several days.
 * EWWWWW!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * @copyright 2010 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class backup_moodleform extends base_moodleform {
    /**
     * Creates the form
     *
     * @param backup_ui_stage $uistage
     * @param moodle_url|string $action
     * @param mixed $customdata
     * @param string $method get|post
     * @param string $target
     * @param array $attributes
     * @param bool $editable
     */
    public function __construct(backup_ui_stage $uistage, $action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true) {
        parent::__construct($uistage, $action, $customdata, $method, $target, $attributes, $editable);
    }
}
/**
 * Initial backup user interface stage moodleform.
 *
 * Nothing to override we only need it defined so that moodleform doesn't get confused
 * between stages.
 */
class backup_initial_form extends backup_moodleform {}
/**
 * Schema backup user interface stage moodleform.
 *
 * Nothing to override we only need it defined so that moodleform doesn't get confused
 * between stages.
 */
class backup_schema_form extends backup_moodleform {
    /**
     * Adds a link/button that controls the checked state of a group of checkboxes.
     *
     * @param int $groupid The id of the group of advcheckboxes this element controls
     * @param string $text The text of the link. Defaults to selectallornone ("select all/none")
     * @param array $attributes associative array of HTML attributes
     * @param int $originalValue The original general state of the checkboxes before the user first clicks this element
     */
    function add_schema_checkbox_controller($groupid, $text = null, $attributes = null, $originalValue = 0) {
        global $CFG, $PAGE;

        // Name of the controller button
        $checkboxcontrollername = 'nosubmit_checkbox_controller' . $groupid;
        $checkboxcontrollerparam = 'checkbox_controller'. $groupid;
        $checkboxgroupclass = 'checkboxgroup'.$groupid;

        // Set the default text if none was specified
        if (empty($text)) {
            $text = get_string('selectallornone', 'form');
        }

        $mform = $this->_form;
        $selectvalue = optional_param($checkboxcontrollerparam, null, PARAM_INT);
        $contollerbutton = optional_param($checkboxcontrollername, null, PARAM_ALPHAEXT);

        $newselectvalue = $selectvalue;
        if (is_null($selectvalue)) {
            $newselectvalue = $originalValue;
        } else if (!is_null($contollerbutton)) {
            $newselectvalue = (int) !$selectvalue;
        }
        // set checkbox state depending on orignal/submitted value by controoler button
        if (!is_null($contollerbutton) || is_null($selectvalue)) {
            foreach ($mform->_elements as $element) {
                if (($element instanceof MoodleQuickForm_advcheckbox) &&
                        $element->getAttribute('class') == $checkboxgroupclass &&
                        !$element->isFrozen()) {
                    $mform->setConstants(array($element->getName() => $newselectvalue));
                }
            }
        }

        $mform->addElement('hidden', $checkboxcontrollerparam, $newselectvalue, array('id' => "id_".$checkboxcontrollerparam));
        $mform->setType($checkboxcontrollerparam, PARAM_INT);
        $mform->setConstants(array($checkboxcontrollerparam => $newselectvalue));

        $PAGE->requires->yui_module('moodle-form-schemacheckboxcontroller', 'M.form.checkboxcontroller',
                array(
                    array('groupid' => $groupid,
                        'checkboxclass' => $checkboxgroupclass,
                        'checkboxcontroller' => $checkboxcontrollerparam,
                        'controllerbutton' => $checkboxcontrollername)
                    )
                );

        require_once("$CFG->libdir/form/submit.php");
        $submitlink = new MoodleQuickForm_submit($checkboxcontrollername, $attributes);
        $mform->addElement($submitlink);
        $mform->registerNoSubmitButton($checkboxcontrollername);
        $mform->setDefault($checkboxcontrollername, $text);
    }

}
/**
 * Confirmation backup user interface stage moodleform.
 *
 * Nothing to override we only need it defined so that moodleform doesn't get confused
 * between stages.
 */
class backup_confirmation_form extends backup_moodleform {

    public function definition_after_data() {
        parent::definition_after_data();
        $this->_form->addRule('setting_root_filename', get_string('errorfilenamerequired', 'backup'), 'required');
        $this->_form->setType('setting_root_filename', PARAM_FILE);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!array_key_exists('setting_root_filename', $errors)) {
            if (trim($data['setting_root_filename']) == '') {
                $errors['setting_root_filename'] = get_string('errorfilenamerequired', 'backup');
            } else if (!preg_match('#\.mbz$#i', $data['setting_root_filename'])) {
                $errors['setting_root_filename'] = get_string('errorfilenamemustbezip', 'backup');
            }
        }

        return $errors;
    }

}
