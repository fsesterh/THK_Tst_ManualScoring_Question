<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Model;

use assQuestion;
use ilObjTest;

/**
 * Class Question
 * @package TstManualScoringQuestion\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class Question
{

    /**
     * @var Answer[]
     */
    protected array $answers = [];
    protected int $id;
    protected int $pass;
    protected int $testRefId;
    protected int $maximumPoints;
    protected bool $isObligatory;

    public function __construct(int $id)
    {
        $this->id = $id;
        $this->setMaximumPoints($this->readMaximumPoints());
        $this->setIsObligatory($this->readIsObligatory());
    }

    public function readMaximumPoints() : float
    {
        return assQuestion::_getMaximumPoints($this->id);
    }

    public function readIsObligatory() : bool
    {
        return ilObjTest::isQuestionObligatory($this->id);
    }

    /**
     * @return bool
     */
    public function checkValid() : bool
    {
        return isset($this->id, $this->maximumPoints, $this->pass, $this->testRefId);
    }

    /**
     * @return Answer[]
     */
    public function getAnswers() : array
    {
        return $this->answers;
    }

    /**
     * @param Answer[] $answers
     * @return Question
     */
    public function setAnswers(array $answers) : Question
    {
        $this->answers = $answers;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Question
     */
    public function setId(int $id) : Question
    {
        $this->id = $id;
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
     * @return Question
     */
    public function setPass(int $pass) : Question
    {
        $this->pass = $pass;
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
     * @return Question
     */
    public function setTestRefId(int $testRefId) : Question
    {
        $this->testRefId = $testRefId;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaximumPoints() : int
    {
        return $this->maximumPoints;
    }

    /**
     * @param int $maximumPoints
     * @return Question
     */
    public function setMaximumPoints(int $maximumPoints) : Question
    {
        $this->maximumPoints = $maximumPoints;
        return $this;
    }

    /**
     * @return bool
     */
    public function isObligatory() : bool
    {
        return $this->isObligatory;
    }

    /**
     * @param bool $isObligatory
     * @return Question
     */
    public function setIsObligatory(bool $isObligatory) : Question
    {
        $this->isObligatory = $isObligatory;
        return $this;
    }
}