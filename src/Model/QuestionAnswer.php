<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Model;

use ilTestParticipant;
use assQuestion;
use ilObjTest;

/**
 * Class QuestionAnswer
 * @package TstManualScoringQuestion\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class QuestionAnswer
{
    protected int $questionId;
    protected int $activeId;
    protected int $pass;
    protected string $answerHtml;
    protected ilTestParticipant $participant;
    protected float $points;
    protected int $testRefId;
    protected string $feedback = "";

    /**
     * @return int
     */
    public function getQuestionId() : int
    {
        return $this->questionId;
    }

    /**
     * @param int $questionId
     * @return QuestionAnswer
     */
    public function setQuestionId(int $questionId) : QuestionAnswer
    {
        $this->questionId = $questionId;
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
     * @return QuestionAnswer
     */
    public function setActiveId(int $activeId) : QuestionAnswer
    {
        $this->activeId = $activeId;
        return $this;
    }

    /**
     * @return int
     */
    public function getPass() : int
    {
        return $this->pass;
    }

    /**
     * @param int $pass
     * @return QuestionAnswer
     */
    public function setPass(int $pass) : QuestionAnswer
    {
        $this->pass = $pass;
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
     * @return QuestionAnswer
     */
    public function setAnswerHtml(string $answerHtml) : QuestionAnswer
    {
        $this->answerHtml = $answerHtml;
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
     * @return QuestionAnswer
     */
    public function setParticipant(ilTestParticipant $participant) : QuestionAnswer
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * @return int
     */
    public function getTestRefId() : int
    {
        return $this->testRefId;
    }

    /**
     * @param int $testRefId
     * @return QuestionAnswer
     */
    public function setTestRefId(int $testRefId) : QuestionAnswer
    {
        $this->testRefId = $testRefId;
        return $this;
    }

    public function readReachedPoints() : float
    {
        return assQuestion::_getReachedPoints($this->activeId, $this->questionId, $this->pass);
    }

    public function readMaximumPoints() : float
    {
        return assQuestion::_getMaximumPoints($this->questionId);
    }

    public function readIsObligatory() : bool
    {
        return ilObjTest::isQuestionObligatory($this->questionId);
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
     * @return QuestionAnswer
     */
    public function setPoints(float $points) : QuestionAnswer
    {
        $this->points = $points;
        return $this;
    }



    public function writePoints()
    {
        assQuestion::_setReachedPoints(
            $this->activeId,
            $this->questionId,
            $this->points,
            $this->getMaximumPoints(),
            $this->pass,
            1,
            $this->getIsObligatory()
        );
    }

    /**
     * @param string $feedback
     * @return QuestionAnswer
     */
    public function setFeedback(string $feedback) : QuestionAnswer
    {
        $this->feedback = $feedback;
        return $this;
    }

    /**
     * @return string
     */
    public function getFeedback() : string
    {
        if($this->feedback !== "") {
            return $this->feedback;
        }
        return "";
    }

    /**
     * Reads the feedback from ilias
     * @return string
     */
    public function readFeedback() : string
    {
        if($this->feedback !== "") {
            return $this->feedback;
        }

        $manualFeedback = ilObjTest::getSingleManualFeedback($this->activeId, $this->questionId, $this->pass);
        if ($manualFeedback) {
            $this->setFeedback($manualFeedback["feedback"]);
            return $manualFeedback["feedback"];
        }
        $this->setFeedback("");
        return $this->getFeedback();
    }

    /**
     * Writes the feedback to ilias
     */
    public function writeFeedback()
    {
        if ($this->getFeedback() !== "") {
        }
    }

    public function loadFromPostArray($answerData)
    {
        $points = $answerData["pointsForAnswer"];
        $feedback = $answerData["feedback"];
        $testRefId = $answerData["testRefId"];
        $pass = $answerData["pass"];
        $questionId = $answerData["questionId"];
        $activeId = $answerData["activeId"];

        if (is_numeric($points)) {
            $this->setPoints((float) $points);
        }

        if (is_string($feedback)) {
            $this->setFeedback($feedback);
        }

        if (is_numeric($testRefId)) {
            $this->setTestRefId((int) $testRefId);
        }

        if (is_numeric($pass)) {
            $this->setPass((int) $pass);
        }

        if (is_numeric($questionId)) {
            $this->setQuestionId((int) $questionId);
        }

        if (is_numeric($activeId)) {
            $this->setActiveId((int) $activeId);
        }
    }

    /**
     * Returns if all required fields are set (not null
     * @return bool
     */
    public function checkValid()
    {
        return isset($this->testRefId, $this->activeId, $this->questionId, $this->pass, $this->points);
    }
}
