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
 * Multiple choice question renderer classes.
 *
 * @package    qtype
 * @subpackage multichoice
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Base class for generating the bits of output common to multiple choice
 * single and multiple questions.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_randomsamatch_renderer extends qtype_with_combined_feedback_renderer {
    /*protected function get_input_type() {
        return 'checkbox';
    }

    protected function get_input_name(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('choice' . $value);
    }

    protected function get_input_value($value) {
        return 1;
    }

    protected function get_input_id(question_attempt $qa, $value) {
        return $this->get_input_name($qa, $value);
    }

    protected function is_right(question_answer $ans) {
        if ($ans->fraction > 0) {
            return 1;
        } else {
            return 0;
        }
    }

    protected function prompt() {
        return get_string('selectmulti', 'qtype_multichoice');
    }*/

    public function correct_response(question_attempt $qa) {

        $question = $qa->get_question();
        $questionorder = $question->get_question_order();

        $answers = $question->get_answers();
        $right = array();
        foreach ($questionorder as $key => $questionid) {
            $subq = $question->get_question($questionid);
            $right[] = $question->format_text($subq->questiontext,
                    $subq->questiontextformat, $qa,
                    'qtype_ransomsamatch', 'subquestion', $questionid) . ' – ' .
                    $answers[$question->get_right_choice_for($questionid)];
        }

        if (!empty($right)) {
            return get_string('correctansweris', 'qtype_match', implode(', ', $right));
        }
        /*$question = $qa->get_question();

        $right = array();
        foreach ($question->answers as $ansid => $ans) {
            if ($ans->fraction > 0) {
                $right[] = $question->format_text($ans->answer, $ans->answerformat,
                        $qa, 'question', 'answer', $ansid);
            }
        }

        if (!empty($right)) {
                return get_string('correctansweris', 'qtype_multichoice',
                        implode(', ', $right));
        }
        return '';*/
    }


    /**
     * Whether a choice should be considered right, wrong or partially right.
     * @param question_answer $ans representing one of the choices.
     * @return fload 1.0, 0.0 or something in between, respectively.
     */


    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $questionorder = $question->get_question_order();
        $response = $qa->get_last_qt_data();

        $choices = $question->get_answers();

        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::start_tag('table', array('class' => 'answer'));
        $result .= html_writer::start_tag('tbody');

        $parity = 0;
        foreach ($questionorder as $key => $subquestionid) {
            $subquestion = $question->get_question($subquestionid);


            $result .= html_writer::start_tag('tr', array('class' => 'r' . $parity));
            $fieldname = 'choice' . $key;

            $result .= html_writer::tag('td', $question->format_text(
                    $subquestion->questiontext, $subquestion->questiontextformat,
                    $qa, 'qtype_match', 'subquestion', $subquestionid),
                    array('class' => 'text'));

            $classes = 'control';
            $feedbackimage = '';

            if (array_key_exists($fieldname, $response)) {
                $selected = $response[$fieldname];
            } else {
                $selected = 0;
            }

            $fraction = (int) ($selected && $selected == $question->get_right_choice_for($subquestionid));

            if ($options->correctness && $selected) {
                $classes .= ' ' . $this->feedback_class($fraction);
                $feedbackimage = $this->feedback_image($fraction);
            }

            $result .= html_writer::tag('td',
                    html_writer::select($choices, $qa->get_qt_field_name('choice' . $key), $selected,
                            array('0' => 'choose'), array('disabled' => $options->readonly)) .
                    ' ' . $feedbackimage, array('class' => $classes));

            $result .= html_writer::end_tag('tr');
            $parity = 1 - $parity;
        }
        $result .= html_writer::end_tag('tbody');
        $result .= html_writer::end_tag('table');

        $result .= html_writer::end_tag('div'); // ablock

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($response),
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    protected function number_html($qnum) {
        return $qnum . '. ';
    }

    /**
     * @param int $num The number, starting at 0.
     * @param string $style The style to render the number in. One of the
     * options returned by {@link qtype_multichoice:;get_numbering_styles()}.
     * @return string the number $num in the requested style.
     */
    protected function number_in_style($num, $style) {
        switch($style) {
            case 'abc':
                $number = chr(ord('a') + $num);
                break;
            case 'ABCD':
                $number = chr(ord('A') + $num);
                break;
            case '123':
                $number = $num + 1;
                break;
            case 'iii':
                $number = question_utils::int_to_roman($num + 1);
                break;
            case 'IIII':
                $number = strtoupper(question_utils::int_to_roman($num + 1));
                break;
            case 'none':
                return '';
            default:
                return 'ERR';
        }
        return $this->number_html($number);
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }
}
