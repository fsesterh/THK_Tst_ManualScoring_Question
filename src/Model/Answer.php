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
use ilDBConstants;
use ilDBInterface;
use ilObjTest;
use ilRTE;

class Answer
{
    protected ilDBInterface $db;
    protected Question $question;

    public function __construct(
        Question $question,
        private readonly int $activeId,
        private ?bool $scoringCompleted = null,
        private ?float $points = null,
        private ?string $feedback = null,
        private readonly string $login = "",
        private readonly string $userName = "",
        private readonly string $answerHtml = ""
    ) {
        global $DIC;
        $this->db = $DIC->database();
        $this->question = $question;
    }

    public function readScoringCompleted(): bool
    {
        global $DIC;
        $result = $DIC->database()->queryF(
            "SELECT finalized_evaluation FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
            [$this->activeId, $this->question->getId(), $this->getQuestion()->getPass()]
        );
        if ($result->numRows()) {
            $row = $DIC->database()->fetchAssoc($result);
            if (!isset($row["finalized_evaluation"])) {
                return false;
            }
            return (bool) $row["finalized_evaluation"];
        }
        return false;
    }

    private function readFeedback(): string
    {
        $result = $this->db->queryF(
            "SELECT feedback FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
            [$this->activeId, $this->question->getId(), $this->question->getPass()]
        );

        return $this->db->fetchAssoc($result)["feedback"] ?? "";
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function writeFeedback(): bool
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

    private function readPoints(): float
    {
        return assQuestion::_getReachedPoints(
            $this->activeId,
            $this->question->getId(),
            $this->question->getPass()
        );
    }

    public function writePoints(): bool
    {
        assQuestion::_setReachedPoints(
            $this->activeId,
            $this->question->getId(),
            $this->points,
            $this->question->getMaximumPoints(),
            $this->question->getPass(),
            true
        );


        $result = $this->db->queryF(
            "SELECT EXISTS(SELECT 1 FROM tst_test_result "
            . "WHERE question_fi = %s AND active_fi = %s AND pass = %s AND points = %s"
            . ") AS does_exist",
            [
                ilDBConstants::T_INTEGER,
                ilDBConstants::T_INTEGER,
                ilDBConstants::T_INTEGER,
                ilDBConstants::T_FLOAT,
            ],
            [
                $this->question->getId(),
                $this->activeId,
                $this->question->getPass(),
                $this->getPoints(),
            ]
        );

        return (bool) $this->db->fetchAssoc($result)["does_exist"];
    }

    public function getActiveId(): int
    {
        return $this->activeId;
    }

    public function getFeedback(): string
    {
        if ($this->feedback === null) {
            $this->feedback = $this->readFeedback();
        }
        return $this->feedback;
    }

    public function getAnswerHtml(): string
    {
        return $this->answerHtml;
    }

    public function getPoints(): ?float
    {
        if ($this->points === null) {
            $this->points = $this->readPoints();
        }
        return $this->points;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function isScoringCompleted(): bool
    {
        if ($this->scoringCompleted === null) {
            $this->scoringCompleted = $this->readScoringCompleted();
        }
        return $this->scoringCompleted;
    }


    private function saveManualFeedback(
        int $active_id,
        int $question_id,
        int $pass,
        string $feedback,
        bool $finalized = false,
        bool $is_single_feedback = false
    ): bool {
        global $DIC;

        $feedback_old = ilObjTest::getSingleManualFeedback($active_id, $question_id, $pass);

        if ($feedback_old !== []) {
            $finalized_record = (int) $feedback_old["finalized_evaluation"];
            if ($finalized_record === 0 || ($is_single_feedback && $finalized_record === 1)) {
                $DIC->database()->manipulateF(
                    "DELETE FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
                    [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
                    [$active_id, $question_id, $pass]
                );
            }
        }
        $this->insertManualFeedback($active_id, $question_id, $pass, $feedback, $finalized, $feedback_old);
        return true;
    }

    private function insertManualFeedback(
        int $active_id,
        int $question_id,
        int $pass,
        string $feedback,
        bool $finalized,
        ?array $feedback_old
    ): void {
        global $DIC;

        $ilDB = $DIC->database();
        $ilUser = $DIC->user();
        $next_id = $ilDB->nextId("tst_manual_fb");
        $user = $ilUser->getId();
        $finalized_time = time();

        $update_default = [
            "manual_feedback_id" => [ilDBConstants::T_INTEGER, $next_id],
            "active_fi" => [ilDBConstants::T_INTEGER, $active_id],
            "question_fi" => [ilDBConstants::T_INTEGER, $question_id],
            "pass" => [ilDBConstants::T_INTEGER, $pass],
            "feedback" => [ilDBConstants::T_CLOB, ilRTE::_replaceMediaObjectImageSrc($feedback)],
            "tstamp" => [ilDBConstants::T_INTEGER, time()]
        ];

        if ($feedback_old !== null && isset($feedback_old["finalized_evaluation"]) && (int) $feedback_old["finalized_evaluation"] === 1) {
            $user = $feedback_old["finalized_by_usr_id"];
            $finalized_time = $feedback_old["finalized_tstamp"];
        }

        if ($finalized === true) {
            $update_default["finalized_evaluation"] = [ilDBConstants::T_INTEGER, 1];
            $update_default["finalized_by_usr_id"] = [ilDBConstants::T_INTEGER, $user];
            $update_default["finalized_tstamp"] = [ilDBConstants::T_INTEGER, $finalized_time];
        }

        $ilDB->insert("tst_manual_fb", $update_default);
    }
}
