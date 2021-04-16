<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Model;

use ilTestParticipant;
use assQuestion;
use ilObjTest;

/**
 * Class Answer
 * @package TstManualScoringQuestion\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class Answer
{
    protected ilTestParticipant $participant;
    protected int $activeId;
    protected string $feedback = "";
    protected string $answerHtml;
    protected float $points;
    protected Question $question;

    public function __construct(Question $question)
    {
        $this->question = $question;
    }

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
     * @param ilObjTest $test
     * @param bool      $finalized
     * @return bool
     */
    public function writeFeedback(ilObjTest $test, bool $finalized = false) : bool
    {
        return $test->saveManualFeedback($this->activeId, $this->question->getId(), $this->question->getPass(), $this->feedback, $finalized);
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
        $answerFieldsValid = isset($this->activeId, $this->points, $this->feedback);
        if ($checkQuestionObject) {
            $questionFieldsValid = $this->question->checkValid();
            return $answerFieldsValid && $questionFieldsValid;
        }
        return $answerFieldsValid;
    }

    /**
     * @param array $answerData
     */
    public function loadFromPost(array $answerData)
    {
        $points = $answerData["points"];
        $feedback = $answerData["feedback"];
        $activeId = $answerData["activeId"];

        if (is_numeric($points)) {
            $this->setPoints((float) $points);
        }

        if (is_string($feedback)) {
            $this->setFeedback($feedback);
        }

        if (is_numeric($activeId)) {
            $this->setActiveId((int) $activeId);
        }
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
}
