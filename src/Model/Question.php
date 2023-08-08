<?php

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\TstManualScoringQuestion\Model;

use assQuestion;
use ilObjTest;

/**
 * Class Question
 *
 * @package TstManualScoringQuestion\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class Question
{
    /**
     * @var Answer[]
     */
    protected $answers = [];
    /**
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $pass;
    /**
     * @var int
     */
    protected $testRefId;
    /**
     * @var float
     */
    protected $maximumPoints;
    /**
     * @var bool
     */
    protected $isObligatory;

    public function __construct(int $id = null)
    {
        if ($id !== null) {
            $this->id = $id;
            $this->setMaximumPoints($this->readMaximumPoints());
            $this->setIsObligatory($this->readIsObligatory());
        }
    }

    public function readMaximumPoints(): float
    {
        return assQuestion::_getMaximumPoints($this->id);
    }

    public function readIsObligatory(): bool
    {
        return ilObjTest::isQuestionObligatory($this->id);
    }

    public function loadFromPost($questionData): ?Question
    {
        $answersData = $questionData["answers"];
        $testRefId = $questionData["testRefId"];
        $pass = $questionData["pass"];
        $questionId = $questionData["questionId"];

        if (is_numeric($testRefId)) {
            $this->setTestRefId((int) $testRefId);
        }

        if (is_numeric($pass)) {
            $this->setPass((int) $pass);
        }

        if (is_numeric($questionId)) {
            $this->setId((int) $questionId);
        }

        if (is_numeric($this->getId())) {
            $this->setMaximumPoints($this->readMaximumPoints());
            $this->setIsObligatory($this->readIsObligatory());
        }

        /**
         * @var Answer[] $answers
         */
        $answers = [];

        if (isset($answersData) && is_array($answersData)) {
            foreach ($answersData as $answerData) {
                $answer = new Answer($this);
                $answer->loadFromPost($answerData);
                array_push($answers, $answer);
            }
        }
        $this->setAnswers($answers);

        return $this;
    }

    /**
     * @return Answer[]
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * @param Answer[] $answers
     * @return Question
     */
    public function setAnswers(array $answers): Question
    {
        $this->answers = $answers;
        return $this;
    }

    /**
     * @param Answer $answer
     * @return Question
     */
    public function addAnswer(Answer $answer): Question
    {
        array_push($this->answers, $answer);
        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Question
     */
    public function setId(int $id): Question
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getPass(): int
    {
        return $this->pass;
    }

    /**
     * @param int $pass
     * @return Question
     */
    public function setPass(int $pass): Question
    {
        $this->pass = $pass;
        return $this;
    }

    /**
     * @return int
     */
    public function getTestRefId(): int
    {
        return $this->testRefId;
    }

    /**
     * @param int $testRefId
     * @return Question
     */
    public function setTestRefId(int $testRefId): Question
    {
        $this->testRefId = $testRefId;
        return $this;
    }

    /**
     * @return float
     */
    public function getMaximumPoints(): float
    {
        return $this->maximumPoints;
    }

    /**
     * @param float $maximumPoints
     * @return Question
     */
    public function setMaximumPoints(float $maximumPoints): Question
    {
        $this->maximumPoints = $maximumPoints;
        return $this;
    }

    /**
     * @return bool
     */
    public function isObligatory(): bool
    {
        return $this->isObligatory;
    }

    /**
     * @param bool $isObligatory
     * @return Question
     */
    public function setIsObligatory(bool $isObligatory): Question
    {
        $this->isObligatory = $isObligatory;
        return $this;
    }
}
