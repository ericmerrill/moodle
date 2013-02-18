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
    public $correctfeedback;
    public $correctfeedbackformat;
    public $partiallycorrectfeedback;
    public $partiallycorrectfeedbackformat;
    public $incorrectfeedback;
    public $incorrectfeedbackformat;

    public $choose;

    protected $questions = null;

    protected $questionorder = null;
    protected $answerorder = null;

    public function start_attempt(question_attempt_step $step, $variant) {
        $this->create_attempt();

        $this->save_state_step($step);
    }

    public function create_attempt() {
        global $DB;

        $this->questions = array();

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

        foreach ($fullquestions as $key => $wrappedquestion) {
            $files = $DB->get_records('files', array('component' => 'question', 'filearea' => 'questiontext', 'itemid' => $wrappedquestion->id));
            $this->copy_files($files);

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
        $answerorder = swapshuffle($range);
        $this->set_answerorder($answerorder);
    }

    protected function copy_files($files) {
        global $COURSE;

        $coursecontext = context_course::instance($COURSE->id);

        if (!is_array($files)) {
            return false;
        }

        $fs = get_file_storage();

        foreach ($files as $file) {
            if ($fs->get_file($coursecontext->id, 'qtype_randomsamatch', 'attempt_question', $this->id, '/', $file->filename)) {
                continue;
            }
            
            $new = new stdClass();
            $new->contextid = $coursecontext->id;
            $new->component = "qtype_randomsamatch";
            $new->filearea = "attempt_question";
            $new->itemid = $this->id;
            $new->filepath = '/';
            $new->filename = $file->filename;
            $new->timecreated = time();
            $new->timemodified = time();
            $fs->create_file_from_storedfile($new, $file->id);
        }
    }

    public function save_state_step(question_attempt_step $step) {
        $step->set_qt_var('_choose', $this->choose);
        $step->set_qt_var('_questions', serialize($this->questions));
        $step->set_qt_var('_questionorder', serialize($this->questionorder));
        $step->set_qt_var('_answerorder', serialize($this->answerorder));

    }

    public function apply_attempt_state(question_attempt_step $step) {
        $this->choose = $step->get_qt_var('_choose');
        $this->questions = unserialize($step->get_qt_var('_questions'));
        $this->questionorder = unserialize($step->get_qt_var('_questionorder'));
        $this->answerorder = unserialize($step->get_qt_var('_answerorder'));
    }

    protected function set_answerorder($answerorder) {
        $this->answerorder = array();
        foreach ($answerorder as $key => $value) {
            $this->answerorder[$key + 1] = $value;
        }
    }

    public function get_question_summary() {
        $questions = array();
        foreach ($this->questionorder as $id) {
            $question = $this->questions[$id];
            $questions[] = $this->html_to_text($question->questiontext, $question->questiontextformat);
        }

        $choices = array();
        foreach ($this->answerorder as $id) {
            $question = $this->questions[$id];
            $choices[] = $this->html_to_text($question->answer, $question->answerformat);
        }

        return '{' . implode('; ', $questions) . '} -> {' .
                implode('; ', $choices) . '}';
    }

    public function summarise_response(array $response) {
        $matches = array();

        foreach ($this->questionorder as $key => $qid) {
            if (array_key_exists($this->field($key), $response) && $response[$this->field($key)]) {
                $question = $this->questions[$qid];

                $resquestion = $this->questions[$this->answerorder[$response[$this->field($key)]]];
                $matches[] = $this->html_to_text($question->questiontext,
                        $question->questiontextformat) . ' -> ' .
                        $this->html_to_text($resquestion->answer,
                        $resquestion->answerformat);
            }
        }
        if (empty($matches)) {
            return null;
        }

        return implode('; ', $matches);

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
/* print $component.":".$filearea;die(); */
        /*
if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $qa->get_question()->get_matching_answer(array('answer' => $currentanswer));
            $answerid = reset($args); // itemid is answer id.
            return $options->feedback && $answerid == $answer->id;

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }
*/

    }

    public function get_min_fraction() {
        return 0;
    }


    public function clear_wrong_from_response(array $response) {
        foreach ($this->questionorder as $key => $qid) {
            if (array_key_exists($this->field($key), $response)) {
                $correctres = $this->get_right_choice_for($qid);

                if ($response[$this-field($key)] != $correctres) {
                    $response[$this->field($key)] = 0;
                }
            }
        }
        return $response;
    }

    /**
     * @param int $key choice number
     * @return string the question-type variable name.
     */
    protected function field($key) {
        return 'choice' . $key;
    }

    public function get_expected_data() {
        $expected = array();
        foreach ($this->questionorder as $key => $id) {
            $expected[$this->field($key)] = PARAM_INTEGER;
        }

        return $expected;
    }




    /*public function classify_response(array $response) {
        $selectedchoices = array();
        foreach ($this->questionorder as $key => $qid) {
            if (array_key_exists($this->field($key), $response) && $response[$this->field($key)]) {
                $selectedchoices[$qid] = $this->anwerorder[$response[$this->field($key)]];
            } else {
                $selectedchoices[$qid] = 0;
            }
        }

        $parts = array();
        foreach ($this->questions as $qid => $question) {
            if (empty($selectedchoices[$qid])) {
                $parts[$qid] = question_classified_response::no_response();
                continue;
            }
            $choice = $this->choices[$selectedchoices[$stemid]];
            $parts[$stemid] = new question_classified_response(
                    $selectedchoices[$stemid], $choice,
                    ($selectedchoices[$stemid] == $this->right[$stemid]) / count($this->stems));
        }
        return $parts;
    }*/

    public function get_correct_response() {
        $response = array();
        foreach ($this->questionorder as $key => $questionid) {
            $response[$this->field($key)] = $this->get_right_choice_for($questionid);
        }
        return $response;
    }

    public function get_right_choice_for($questionid) {
        foreach ($this->answerorder as $answerkey => $answerid) {
            if ($questionid == $answerid) {
                return $answerkey;
            }
        }
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->questionorder as $key => $qid) {
            $fieldname = $this->field($key);
            if (!question_utils::arrays_same_at_key($prevresponse, $newresponse, $fieldname)) {
                return false;
            }
        }
        return true;
    }

    public function is_complete_response(array $response) {
        foreach ($this->questionorder as $key => $id) {
            if (!empty($response[$this->field($key)])) {
                return true;
            }
        }
        return false;
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function get_num_parts_right(array $response) {
        $numright = 0;
        foreach ($this->questionorder as $key => $questionid) {
            $fieldname = $this->field($questionid);
            if (!array_key_exists($fieldname, $response)) {
                continue;
            }

            $choice = $response[$fieldname];
            if ($choice && $this->answerorder[$choice] == ($questionid)) {
                $numright += 1;
            }
        }
        return array($numright, count($this->questionorder));
    }

    public function grade_response(array $response) {
        list($right, $total) = $this->get_num_parts_right($response);
        $fraction = $right / $total;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseananswerallparts', 'qtype_randomsamatch');
    }

    public function get_hint($hintnumber, question_attempt $qa) {
        $hint = parent::get_hint($hintnumber, $qa);
        if (is_null($hint)) {
            return $hint;
        }

        return $hint;
    }

    public function get_response(question_attempt $qa) {
        return $qa->get_last_qt_data();
    }

    public function get_sa_candidates($categorylist, $questionsinuse = 0) {
        // TODO Dont select SA that has already been selected ($questionsinuse).
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

    public function get_question($questionindex) {
        if ($questionindex >= count($this->questions)) {
            return false;
        }
        return $this->questions[$questionindex];
    }

    public function get_answers() {
        if (!$this->answerorder) {
            return false;
        }

        $answers = array();
        foreach ($this->answerorder as $key => $i) {
            $question = $this->questions[$i];
            $answers[$key] = $this->html_to_text($question->answer, $question->answerformat);
        }
        return $answers;
    }

}
