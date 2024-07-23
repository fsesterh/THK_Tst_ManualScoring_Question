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
use ilDBInterface;
use ilObjAssessmentFolder;
use ilObjTest;
use ilObjTestAccess;
use ilRTE;

class Answer
{
    protected ilDBInterface $db;
    protected string $userName;
    protected string $login;
    protected int $activeId;
    protected string $feedback = "";
    protected string $answerHtml = "";
    protected ?float $points;
    protected Question $question;
    protected bool $scoringCompleted;

    public function __construct(Question $question)
    {
        global $DIC;
        $this->db = $DIC->database();
        $this->question = $question;
    }

    public function readScoringCompleted(): bool
    {
        global $DIC;
        $result = $DIC->database()->queryF(
            "SELECT finalized_evaluation FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            ['integer', 'integer', 'integer'],
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

    public function readFeedback(): string
    {
        $result = $this->db->queryF(
            "SELECT feedback FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            ['integer', 'integer', 'integer'],
            [$this->activeId, $this->question->getId(), $this->question->getPass()]
        );

        return $this->db->fetchAssoc($result)["feedback"] ?? "";
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): Answer
    {
        $this->userName = $userName;
        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): Answer
    {
        $this->login = $login;
        return $this;
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

    protected function readPoints(): float
    {
        return assQuestion::_getReachedPoints(
            $this->activeId,
            $this->question->getId(),
            $this->question->getPass()
        );
    }

    public function writePoints(): bool
    {
        return assQuestion::_setReachedPoints(
            $this->activeId,
            $this->question->getId(),
            $this->points,
            $this->question->getMaximumPoints(),
            $this->question->getPass(),
            true,
            $this->question->readIsObligatory()
        );
    }

    public function loadFromPost(array $answerData): Answer
    {
        $this->setActiveId((int) $answerData["activeId"]);

        if (!isset($answerData["points"])) {
            $this->setPoints($this->readPoints());
        } elseif (is_numeric($answerData["points"])) {
            $this->setPoints((float) $answerData["points"]);
        }

        if (!isset($answerData["feedback"])) {
            $this->setFeedback($this->readFeedback());
        } elseif (is_string($answerData["feedback"])) {
            $this->setFeedback($answerData["feedback"]);
        }

        $scoringCompleted = (bool) ($answerData["scoringCompleted"] ?? false);

        $this->setScoringCompleted($scoringCompleted);

        return $this;
    }

    public function getActiveId(): int
    {
        return $this->activeId;
    }

    public function setActiveId(int $activeId): Answer
    {
        $this->activeId = $activeId;
        return $this;
    }

    public function getFeedback(): string
    {
        return $this->feedback;
    }

    public function setFeedback(string $feedback): Answer
    {
        $this->feedback = $feedback;
        return $this;
    }

    public function getAnswerHtml(): string
    {
        return $this->answerHtml;
    }

    public function setAnswerHtml(string $answerHtml): Answer
    {
        $this->answerHtml = $answerHtml;
        return $this;
    }

    public function getPoints(): ?float
    {
        return $this->points;
    }

    public function setPoints(float $points): Answer
    {
        $this->points = $points;
        return $this;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function isScoringCompleted(): bool
    {
        return $this->scoringCompleted;
    }

    public function setScoringCompleted(bool $scoringCompleted): Answer
    {
        $this->scoringCompleted = $scoringCompleted;
        return $this;
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
            $finalized_record = (int) $feedback_old['finalized_evaluation'];
            if ($finalized_record === 0 || ($is_single_feedback && $finalized_record === 1)) {
                $DIC->database()->manipulateF(
                    "DELETE FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
                    ['integer', 'integer', 'integer'],
                    [$active_id, $question_id, $pass]
                );
            }
        }
        $this->insertManualFeedback($active_id, $question_id, $pass, $feedback, $finalized, $feedback_old);

        if (ilObjAssessmentFolder::_enabledAssessmentLogging()) {
            $this->logManualFeedback($active_id, $question_id, $feedback);
        }
        return true;
    }

    private function logManualFeedback(int $active_id, int $question_id, string $feedback): void
    {
        global $DIC;

        $ilUser = $DIC->user();
        $lng = $DIC->language();
        $username = ilObjTestAccess::_getParticipantData($active_id);

        $test = new ilObjTest($this->question->getTestRefId(), true);
        $question = assQuestion::instantiateQuestion($question_id);
        $test->logAction(
            sprintf(
                $lng->txtlng('assessment', 'log_manual_feedback', ilObjAssessmentFolder::_getLogLanguage()),
                $ilUser->getFullname() . ' (' . $ilUser->getLogin() . ')',
                $username,
                $question->getTitle(),
                $feedback
            )
        );
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

        if ($feedback_old !== null && isset($feedback_old['finalized_evaluation']) && (int) $feedback_old['finalized_evaluation'] === 1) {
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
