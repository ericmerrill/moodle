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

class qtype_randomsamatch_question extends question_graded_automatically {
    /*const LAYOUT_DROPDOWN = 0;
    const LAYOUT_VERTICAL = 1;
    const LAYOUT_HORIZONTAL = 2;

    public $answers;

    public $shuffleanswers;
    public $answernumbering;
    public $layout = self::LAYOUT_VERTICAL;

    public $correctfeedback;
    public $correctfeedbackformat;
    public $partiallycorrectfeedback;
    public $partiallycorrectfeedbackformat;
    public $incorrectfeedback;
    public $incorrectfeedbackformat;*/

    public $choose;

    protected $questions = null;

    protected $questionorder = null;
    protected $answerorder = null;

    public function start_attempt(question_attempt_step $step, $variant) {
        //$step->set_qt_var('_test', 'test');
        $this->create_attempt();
        
        $this->save_state_step($step);
        /*foreach ($saquestions as $key => $wrappedquestion) {
            
        }*/

        print_r($this);
        /*$this->order = array_keys($this->answers);
        if ($this->shuffleanswers) {
            shuffle($this->order);
        }
        $step->set_qt_var('_order', implode(',', $this->order));*/
    }

    public function create_attempt() {
        $this->questions = array();

        /*if ($this->options->subcats) {
            // recurse into subcategories
            $categorylist = question_categorylist($this->category);
        } else {
            $categorylist = array($this->category);
        }*/

        $categorylist = array($this->category);

        $saquestions = $this->get_sa_candidates($categorylist);

        $count  = count($saquestions);
        $wanted = $this->choose;

        if ($count < $wanted) {
            $this->questiontext = "Insufficient selection options are
                available for this question, therefore it is not available in  this
                quiz. Please inform your teacher.";
            // Treat this as a description from this point on
            $this->qtype = 'description';
            return true;
        }

        $saquestions = draw_rand_array($saquestions, $this->choose);

        $questionids = array();
        foreach ($saquestions as $key => $wrappedquestion) {
            $questionids[] = $wrappedquestion->id;
        }

        $fullquestions = question_load_questions($questionids);

        //for ($i = 0; $i < $this->choose; $i++) {
        foreach ($fullquestions as $key => $wrappedquestion) {
            $question = new stdClass();
            $question->name = $wrappedquestion->name;
            $question->questiontext = $wrappedquestion->questiontext;
            $question->questiontextformat = $wrappedquestion->questiontextformat;
            $question->stamp = $wrappedquestion->stamp;
            $question->version = $wrappedquestion->version;
            $question->timecreated = $wrappedquestion->timecreated;
            $question->timemodified = $wrappedquestion->timemodified;
            $question->createdby = $wrappedquestion->createdby;

            $foundcorrect = false;
            $correctanswer = false;
            foreach ($wrappedquestion->options->answers as $answer) {
                if ($foundcorrect || $answer->fraction != 1.0) {
                    unset($wrappedquestion->options->answers[$answer->id]);
                } else if (!$foundcorrect) {
                    $correctanswer = $answer;
                    $foundcorrect = true;
                }
            }

            if ($correctanswer) {
                $question->answer = $correctanswer->answer;
                $question->answerformat = $correctanswer->answerformat;
                $question->answerfeedback = $correctanswer->feedback;
                $question->answerfeedbackformat = $correctanswer->feedbackformat;
            } else {
                // TODO error
            }

            $this->questions[] = $question;
        }

        $range = range(0, ($this->choose-1));
        $this->questionorder = swapshuffle($range);
        $this->answerorder = swapshuffle($range);
    }

    public function save_state_step(question_attempt_step $step) {

    }

    public function load_state_step() {

    }

    public function apply_attempt_state(question_attempt_step $step) {
        /*$this->order = explode(',', $step->get_qt_var('_order'));*/
    }

    public function get_question_summary() {
        /*$question = $this->html_to_text($this->questiontext, $this->questiontextformat);
        $choices = array();
        foreach ($this->order as $ansid) {
            $choices[] = $this->html_to_text($this->answers[$ansid]->answer,
                    $this->answers[$ansid]->answerformat);
        }
        return $question . ': ' . implode('; ', $choices);*/
    }

    public function get_order(question_attempt $qa) {
        $this->init_order($qa);
        return $this->order;
    }

    protected function init_order(question_attempt $qa) {
        if (is_null($this->order)) {
            $this->order = explode(',', $qa->get_step(0)->get_qt_var('_order'));
        }
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        /*if ($component == 'question' && in_array($filearea,
                array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'))) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea);

        } else if ($component == 'question' && $filearea == 'answer') {
            $answerid = reset($args); // itemid is answer id.
            return  in_array($answerid, $this->order);

        } else if ($component == 'question' && $filearea == 'answerfeedback') {
            $answerid = reset($args); // itemid is answer id.
            $response = $this->get_response($qa);
            $isselected = false;
            foreach ($this->order as $value => $ansid) {
                if ($ansid == $answerid) {
                    $isselected = $this->is_choice_selected($response, $value);
                    break;
                }
            }
            // $options->suppresschoicefeedback is a hack specific to the
            // oumultiresponse question type. It would be good to refactor to
            // avoid refering to it here.
            return $options->feedback && empty($options->suppresschoicefeedback) &&
                    $isselected;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }*/
    }

    public function make_html_inline($html) {
        /*$html = preg_replace('~\s*<p>\s*~', '', $html);
        $html = preg_replace('~\s*</p>\s*~', '<br />', $html);
        $html = preg_replace('~<br />$~', '', $html);
        return $html;*/
    }


    /*public function get_renderer(moodle_page $page) {
        return $page->get_renderer('qtype_randomsamatch');
    }*/

    public function get_min_fraction() {
        return 0;
    }


    public function clear_wrong_from_response(array $response) {
        /*foreach ($this->order as $key => $ans) {
            if (array_key_exists($this->field($key), $response) &&
                    question_state::graded_state_for_fraction(
                    $this->answers[$ans]->fraction)->is_incorrect()) {
                $response[$this->field($key)] = 0;
            }
        }*/
        return $response;
    }

    public function get_num_parts_right(array $response) {
        /*$numright = 0;
        foreach ($this->order as $key => $ans) {
            $fieldname = $this->field($key);
            if (!array_key_exists($fieldname, $response) || !$response[$fieldname]) {
                continue;
            }

            if (!question_state::graded_state_for_fraction(
                    $this->answers[$ans]->fraction)->is_incorrect()) {
                $numright += 1;
            }
        }
        return array($numright, count($this->order));*/
    }

    /**
     * @param int $key choice number
     * @return string the question-type variable name.
     */
    protected function field($key) {
        return 'choice' . $key;
    }

    public function get_expected_data() {
        /*$expected = array();
        foreach ($this->order as $key => $notused) {
            $expected[$this->field($key)] = PARAM_BOOL;
        }
        return $expected;*/
    }

    public function summarise_response(array $response) {
        /*$selectedchoices = array();
        foreach ($this->order as $key => $ans) {
            $fieldname = $this->field($key);
            if (array_key_exists($fieldname, $response) && $response[$fieldname]) {
                $selectedchoices[] = $this->html_to_text($this->answers[$ans]->answer,
                        $this->answers[$ans]->answerformat);
            }
        }
        if (empty($selectedchoices)) {
            return null;
        }
        return implode('; ', $selectedchoices);*/
    }

    public function classify_response(array $response) {
        /*$selectedchoices = array();
        foreach ($this->order as $key => $ansid) {
            $fieldname = $this->field($key);
            if (array_key_exists($fieldname, $response) && $response[$fieldname]) {
                $selectedchoices[$ansid] = 1;
            }
        }
        $choices = array();
        foreach ($this->answers as $ansid => $ans) {
            if (isset($selectedchoices[$ansid])) {
                $choices[$ansid] = new question_classified_response($ansid,
                        $this->html_to_text($ans->answer, $ans->answerformat), $ans->fraction);
            }
        }
        return $choices;*/
    }

    public function get_correct_response() {
        /*$response = array();
        foreach ($this->order as $key => $ans) {
            if (!question_state::graded_state_for_fraction(
                    $this->answers[$ans]->fraction)->is_incorrect()) {
                $response[$this->field($key)] = 1;
            }
        }
        return $response;*/
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        /*foreach ($this->order as $key => $notused) {
            $fieldname = $this->field($key);
            if (!question_utils::arrays_same_at_key($prevresponse, $newresponse, $fieldname)) {
                return false;
            }
        }
        return true;*/
    }

    public function is_complete_response(array $response) {
        /*foreach ($this->order as $key => $notused) {
            if (!empty($response[$this->field($key)])) {
                return true;
            }
        }
        return false;*/
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    /**
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return int the number of choices that were selected. in this response.
     */
    public function get_num_selected_choices(array $response) {
        /*$numselected = 0;
        foreach ($response as $key => $value) {
            if (!empty($value)) {
                $numselected += 1;
            }
        }
        return $numselected;*/
    }

    /**
     * @return int the number of choices that are correct.
     */
    public function get_num_correct_choices() {
        /*$numcorrect = 0;
        foreach ($this->answers as $ans) {
            if (!question_state::graded_state_for_fraction($ans->fraction)->is_incorrect()) {
                $numcorrect += 1;
            }
        }
        return $numcorrect;*/
    }

    public function grade_response(array $response) {
        /*$fraction = 0;
        foreach ($this->order as $key => $ansid) {
            if (!empty($response[$this->field($key)])) {
                $fraction += $this->answers[$ansid]->fraction;
            }
        }
        $fraction = min(max(0, $fraction), 1.0);
        return array($fraction, question_state::graded_state_for_fraction($fraction));*/
    }

    public function get_validation_error(array $response) {
        /*if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseselectatleastoneanswer', 'qtype_multichoice');*/
    }

    /**
     * Disable those hint settings that we don't want when the student has selected
     * more choices than the number of right choices. This avoids giving the game away.
     * @param question_hint_with_parts $hint a hint.
     */
    protected function disable_hint_settings_when_too_many_selected(
            question_hint_with_parts $hint) {
        /*$hint->clearwrong = false;*/
    }

    public function get_hint($hintnumber, question_attempt $qa) {
        /*$hint = parent::get_hint($hintnumber, $qa);
        if (is_null($hint)) {
            return $hint;
        }

        if ($this->get_num_selected_choices($qa->get_last_qt_data()) >
                $this->get_num_correct_choices()) {
            $hint = clone($hint);
            $this->disable_hint_settings_when_too_many_selected($hint);
        }
        return $hint;*/
    }

    public function get_response(question_attempt $qa) {
        /*return $qa->get_last_qt_data();*/
    }

    public function is_choice_selected($response, $value) {
        /*return !empty($response['choice' . $value]);*/
    }


    public function get_sa_candidates($categorylist, $questionsinuse = 0) {
        global $DB;
        list ($usql, $params) = $DB->get_in_or_equal($categorylist);
        list ($ques_usql, $ques_params) = $DB->get_in_or_equal(explode(',', $questionsinuse),
                SQL_PARAMS_QM, null, false);
        $params = array_merge($params, $ques_params);
        return $DB->get_records_select('question',
         "qtype = 'shortanswer' " .
         "AND category $usql " .
         "AND parent = '0' " .
         "AND hidden = '0'" .
         "AND id $ques_usql", $params);
    }

    public function get_question_order() {
        return $this->questionorder;
    }

    public function get_answer_order() {
        return $this->answerorder;
    }

    public function get_question(int $questionindex) {
        if ($questionindex >= count($this->questions)) {
            return false;
        }
        return $this->questions[$questionindex];
    }
}
