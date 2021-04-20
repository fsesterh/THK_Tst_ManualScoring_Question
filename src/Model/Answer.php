<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Model;

use ilTestParticipant;
use assQuestion;
use ilObjTest;
use ilRTE;
use ilObjAssessmentFolder;
use ilObjTestAccess;

/**
 * Class Answer
 * @package TstManualScoringQuestion\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class Answer
{
    /**
     * @var ilTestParticipant
     */
    protected $participant;
    /**
     * @var int
     */
    protected $activeId;
    /**
     * @var string
     */
    protected $feedback = "";
    /**
     * @var string
     */
    protected $answerHtml;
    /**
     * @var float
     */
    protected $points;
    /**
     * @var Question
     */
    protected $question;
    /**
     * @var bool
     */
    protected $scoringCompleted;

    public function __construct(Question $question)
    {
        $this->question = $question;
    }

    /**
     * Reads if the scoring for the answer is completed
     * @return bool
     */
    public function readScoringCompleted() : bool
    {
        $manualFeedback = ilObjTest::getSingleManualFeedback(
            $this->activeId,
            $this->question->getId(),
            $this->question->getPass()
        );
        if (!$manualFeedback || !isset($manualFeedback["finalized_evaluation"])) {
            return false;
        }
        return (bool) $manualFeedback["finalized_evaluation"];
    }

    /**
     * reads the reached points for the answer from ilias
     * @return float
     */
    public function readReachedPoints() : float
    {
        return assQuestion::_getReachedPoints($this->activeId, $this->question->getId(), $this->question->getPass());
    }

    /**
     * Reads the feedback from ilias
     * @return string
     */
    public function readFeedback() : string
    {
        $manualFeedback = ilObjTest::getSingleManualFeedback(
            $this->activeId,
            $this->question->getId(),
            $this->question->getPass()
        );
        if ($manualFeedback) {
            return $manualFeedback["feedback"];
        }
        return "";
    }

    /**
     * Writes the feedback to ilias
     * Returns true on success
     * @return bool
     */
    public function writeFeedback() : bool
    {
        return $this->saveManualFeedback(
            $this->activeId,
            $this->question->getId(),
            $this->question->getPass(),
            $this->readScoringCompleted() ? $this->readFeedback() : $this->feedback,
            $this->isScoringCompleted(),
            true
        );
    }

    /**
     * Writes the points to ilias
     * Returns true on success
     * @return bool
     */
    public function writePoints() : bool
    {
        return assQuestion::_setReachedPoints(
            $this->activeId,
            $this->question->getId(),
            $this->points,
            $this->question->getMaximumPoints(),
            $this->question->getPass(),
            1,
            $this->question->readIsObligatory()
        );
    }

    /**
     * Checks if the fields are set
     */
    public function checkValid(bool $checkQuestionObject = false) : bool
    {
        $answerFieldsValid = isset($this->activeId, $this->scoringCompleted);

        $pointsFieldValid = isset($this->points);
        $feedbackFieldValid = isset($this->feedback);
        if($this->readScoringCompleted()) {
            $pointsFieldValid = true;
            $feedbackFieldValid = true;
        }

        $answerFieldsValid = $answerFieldsValid && $pointsFieldValid && $feedbackFieldValid;

        if ($checkQuestionObject) {
            $questionFieldsValid = $this->question->checkValid();
            return $answerFieldsValid && $questionFieldsValid;
        }
        return $answerFieldsValid;
    }

    /**
     * @param array $answerData
     * @return Answer
     */
    public function loadFromPost(array $answerData) : Answer
    {
        $points = $answerData["points"];
        $feedback = $answerData["feedback"];
        $activeId = $answerData["activeId"];
        $scoringCompleted = $answerData["scoringCompleted"];

        if (is_numeric($points)) {
            $this->setPoints((float) $points);
        }

        if (is_string($feedback)) {
            $this->setFeedback($feedback);
        }

        if (is_numeric($activeId)) {
            $this->setActiveId((int) $activeId);
        }

        $this->setScoringCompleted((bool) $scoringCompleted);

        return $this;
    }

    /**
     * @return ilTestParticipant
     */
    public function getParticipant() : ilTestParticipant
    {
        return $this->participant;
    }

    /**
     * @param ilTestParticipant $participant
     * @return Answer
     */
    public function setParticipant(ilTestParticipant $participant) : Answer
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * @return int
     */
    public function getActiveId() : int
    {
        return $this->activeId;
    }

    /**
     * @param int $activeId
     * @return Answer
     */
    public function setActiveId(int $activeId) : Answer
    {
        $this->activeId = $activeId;
        return $this;
    }

    /**
     * @return string
     */
    public function getFeedback() : string
    {
        return $this->feedback;
    }

    /**
     * @param string $feedback
     * @return Answer
     */
    public function setFeedback(string $feedback) : Answer
    {
        $this->feedback = $feedback;
        return $this;
    }

    /**
     * @return string
     */
    public function getAnswerHtml() : string
    {
        return $this->answerHtml;
    }

    /**
     * @param string $answerHtml
     * @return Answer
     */
    public function setAnswerHtml(string $answerHtml) : Answer
    {
        $this->answerHtml = $answerHtml;
        return $this;
    }

    /**
     * @return float
     */
    public function getPoints() : float
    {
        return $this->points;
    }

    /**
     * @param float $points
     * @return Answer
     */
    public function setPoints(float $points) : Answer
    {
        $this->points = $points;
        return $this;
    }

    /**
     * @return Question
     */
    public function getQuestion() : Question
    {
        return $this->question;
    }

    /**
     * @return bool
     */
    public function isScoringCompleted() : bool
    {
        return $this->scoringCompleted;
    }

    /**
     * @param bool $scoringCompleted
     * @return Answer
     */
    public function setScoringCompleted(bool $scoringCompleted) : Answer
    {
        $this->scoringCompleted = $scoringCompleted;
        return $this;
    }

    /**
     * Saves the manual feedback for a question in a test
     * @param integer $active_id   Active ID of the user
     * @param integer $question_id Question ID
     * @param integer $pass        Pass number
     * @param string  $feedback    The feedback text
     * @param boolean $finalized   In Feedback is final
     * @param boolean $is_single_feedback
     * @return boolean TRUE if the operation succeeds, FALSE otherwise
     * @access public
     */
    private function saveManualFeedback(
        int $active_id,
        int $question_id,
        int $pass,
        string $feedback,
        bool $finalized = false,
        bool $is_single_feedback = false
    ) : bool {
        global $DIC;

        $feedback_old = ilObjTest::getSingleManualFeedback($active_id, $question_id, $pass);

        $finalized_record = (int) $feedback_old['finalized_evaluation'];
        if ($finalized_record === 0 || ($is_single_feedback && $finalized_record === 1)) {
            $DIC->database()->manipulateF(
                "DELETE FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
                array('integer', 'integer', 'integer'),
                array($active_id, $question_id, $pass)
            );

            $this->insertManualFeedback($active_id, $question_id, $pass, $feedback, $finalized, $feedback_old);

            if (ilObjAssessmentFolder::_enabledAssessmentLogging()) {
                $this->logManualFeedback($active_id, $question_id, $feedback);
            }
            return true;
        }

        return false;
    }

    /**
     * Creates a log for the manual feedback
     * @param integer $active_id   Active ID of the user
     * @param integer $question_id Question ID
     * @param string  $feedback    The feedback text
     */
    private function logManualFeedback(int $active_id, int $question_id, string $feedback)
    {
        global $DIC;

        $ilUser = $DIC->user();
        $lng = $DIC->language();
        $username = ilObjTestAccess::_getParticipantData($active_id);

        $test = new ilObjTest($this->question->getId(), true);
        $test->logAction(
            sprintf(
                $lng->txtlng('assessment', 'log_manual_feedback', ilObjAssessmentFolder::_getLogLanguage()),
                $ilUser->getFullname() . ' (' . $ilUser->getLogin() . ')',
                $username,
                assQuestion::_getQuestionTitle($question_id),
                $feedback
            )
        );
    }

    /**
     * Inserts a manual feedback into the DB
     * @param integer $active_id    Active ID of the user
     * @param integer $question_id  Question ID
     * @param integer $pass         Pass number
     * @param string  $feedback     The feedback text
     * @param array   $feedback_old The feedback before update
     * @param boolean $finalized    In Feedback is final
     */
    private function insertManualFeedback(
        int $active_id,
        int $question_id,
        int $pass,
        string $feedback,
        bool $finalized,
        array $feedback_old
    ) {
        global $DIC;

        $ilDB = $DIC->database();
        $ilUser = $DIC->user();
        $next_id = $ilDB->nextId('tst_manual_fb');
        $user = $ilUser->getId();
        $finalized_time = time();

        $update_default = [
            'manual_feedback_id' => ['integer', $next_id],
            'active_fi' => ['integer', $active_id],
            'question_fi' => ['integer', $question_id],
            'pass' => ['integer', $pass],
            'feedback' => ['clob', ilRTE::_replaceMediaObjectImageSrc($feedback, 0)],
            'tstamp' => ['integer', time()]
        ];

        if ($feedback_old['finalized_evaluation'] == 1) {
            $user = $feedback_old['finalized_by_usr_id'];
            $finalized_time = $feedback_old['finalized_tstamp'];
        }

        if ($finalized === true) {
            $update_default['finalized_evaluation'] = ['integer', 1];
            $update_default['finalized_by_usr_id'] = ['integer', $user];
            $update_default['finalized_tstamp'] = ['integer', $finalized_time];
        }

        $ilDB->insert('tst_manual_fb', $update_default);
    }
}
