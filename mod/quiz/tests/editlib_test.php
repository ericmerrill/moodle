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
 * Unit tests for (some of) mod/quiz/editlib.php.
 *
 * @package    mod_quiz
 * @category   phpunit
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/editlib.php');


/**
 * Unit tests for (some of) mod/quiz/editlib.php.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_editlib_testcase extends advanced_testcase {
    public function test_quiz_question_tostring() {
        $question = new stdClass();
        $question->qtype = 'multichoice';
        $question->name = 'The question name';
        $question->questiontext = '<p>What sort of <b>inequality</b> is x &lt; y<img alt="?" src="..."></p>';
        $question->questiontextformat = FORMAT_HTML;

        $summary = quiz_question_tostring($question);
        $this->assertEquals('<span class="questionname">The question name</span>' .
                '<span class="questiontext">What sort of INEQUALITY is x &lt; y[?]</span>', $summary);
    }

    /**
     * Create a quiz with questions and walk through a quiz attempt.
     */
    public function test_quiz_remove_slot() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(array('course'=>$SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $questions = array();
        $questions[] = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $questions[] = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        $questions[] = $questiongenerator->create_question('essay', null, array('category' => $cat->id));
        $questions[] = false;
        $questions[] = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $questions[] = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add the questions
        foreach($questions as $question) {
            if ($question === false) {
                quiz_add_random_questions($quiz, 0, $cat->id, 1, false);
            } else {
                quiz_add_quiz_question($question->id, $quiz);
            }
        }

        $this->check_slots($quiz, $questions, 3);

        // Check that the random question is deleted from question bank.
        $removeslot = $DB->get_record('quiz_slots', array('quizid' => $quiz->id, 'slot' => 4));
        $removequestion = $DB->get_record('question', array('id' => $removeslot->questionid));

        // Check that the random one is random.
        $this->assertEquals('random', $removequestion->qtype);

        $count = $DB->count_records('question', array('id' => $removeslot->questionid));
        $this->assertEquals(1, $count);

        quiz_remove_slot($quiz, 4);
        array_splice($questions, 3, 1);

        // Check the the random question was removed.
        $count = $DB->count_records('question', array('id' => $removeslot->questionid));
        $this->assertEquals(0, $count);

        $this->check_slots($quiz, $questions);

        // Check that a non-random question is not deleted from the question bank.
        $removeslot = $DB->get_record('quiz_slots', array('quizid' => $quiz->id, 'slot' => 2));
        $removequestion = $DB->get_record('question', array('id' => $removeslot->questionid));

        // Check that the random one is random.
        $this->assertNotEquals('random', $removequestion->qtype);

        $count = $DB->count_records('question', array('id' => $removeslot->questionid));
        $this->assertEquals(1, $count);

        quiz_remove_slot($quiz, 2);
        array_splice($questions, 1, 1);

        // Check the the random question was removed.
        $count = $DB->count_records('question', array('id' => $removeslot->questionid));
        $this->assertEquals(1, $count);

        $this->check_slots($quiz, $questions);

        quiz_remove_slot($quiz, 1);
        array_splice($questions, 1, 1);
        quiz_remove_slot($quiz, 1);
        array_splice($questions, 1, 1);
        $questions = array_values($questions);
    }

    /**
     * Check the slots of a quiz.
     *
     * @param object $quiz The quiz to work with
     * @param array $questions Array of questions to check against
     * 
     */
    protected function check_slots($quiz, $questions) {
        global $DB;

        $sql = "SELECT qs.*, q.qtype AS qtype
                       FROM {quiz_slots} AS qs
                       JOIN {question} AS q ON qs.questionid = q.id
                      WHERE qs.quizid = ?
                   ORDER BY qs.slot";
        $slots = $DB->get_records_sql($sql, array($quiz->id));
        $this->assertEquals(count($questions), count($slots));

        // Check that the layout looks right.
        $i = 1;
        foreach ($slots as $slot) {
            $this->assertEquals($i, $slot->slot);
            if ($questions[$i-1] !== false) {
                $this->assertEquals($questions[$i-1]->id, $slot->questionid);
            } else {
                $this->assertEquals('random', $slot->qtype);
            }

            $i++;
        }
    }
}
