<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Plugin\TstManualScoringQuestion\Model;

use assQuestion;

class Question
{
    /**
     * @var Answer[]
     */
    protected array $answers = [];
    protected ?float $maximumPoints = null;

    public function __construct(
        private readonly int $id,
        private readonly int $testRefId,
        private readonly int $pass,
    ) {
    }

    private function readMaximumPoints(): float
    {
        return assQuestion::instantiateQuestion($this->id)->getMaximumPoints();
    }

    /**
     * @return Answer[]
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function addAnswer(Answer $answer): Question
    {
        $this->answers[] = $answer;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPass(): int
    {
        return $this->pass;
    }


    public function getTestRefId(): int
    {
        return $this->testRefId;
    }

    public function getMaximumPoints(): float
    {
        if ($this->maximumPoints === null) {
            $this->maximumPoints = $this->readMaximumPoints();
        }
        return $this->maximumPoints;
    }
}
