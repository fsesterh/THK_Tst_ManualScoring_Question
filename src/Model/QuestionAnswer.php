<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Model;

use ilTestParticipant;
use assQuestion;

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
    protected int $testRefId;
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

    public function getReachedPoints() : float
    {
        return assQuestion::_getReachedPoints($this->activeId, $this->questionId, $this->pass);
    }

    public function getMaximumPoints() : int
    {
        return assQuestion::_getMaximumPoints($this->questionId);
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
}