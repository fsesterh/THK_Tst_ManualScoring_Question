<?php declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\TstManualScoringQuestion\Form;

use ilPropertyFormGUI;
use ilTstManualScoringQuestionPlugin;
use ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput\ilHtmlAreaInput;
use ilNonEditableValueGUI;
use ilLanguage;
use ilNumberInputGUI;
use ilHiddenInputGUI;
use Psr\Http\Message\RequestInterface;
use ilTextAreaInputGUI;
use Exception;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Answer;
use ilCheckboxInputGUI;

/**
 * Class ManualScoringForm
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringForm extends ilPropertyFormGUI
{
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ilTstManualScoringQuestionPlugin
     */
    protected $plugin;

    public function __construct(
        ilLanguage $lng,
        Answer $answer
    ) {
        global $DIC;
        $this->request = $DIC->http()->request();
        $this->lng = $lng;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();

        $question = $answer->getQuestion();
        $questionId = $question->getId();
        $activeId = $answer->getActiveId();

        $testRefIdHiddenInput = new ilHiddenInputGUI("tmsq[{$questionId}][testRefId]");
        $testRefIdHiddenInput->setRequired(true);

        $passHiddenInput = new ilHiddenInputGUI("tmsq[{$questionId}][pass]");
        $passHiddenInput->setRequired(true);

        $questionIdHiddenInput = new ilHiddenInputGUI("tmsq[{$questionId}][questionId]");
        $questionIdHiddenInput->setRequired(true);

        $activeIdHiddenInput = new ilHiddenInputGUI("tmsq[{$questionId}][answers][{$activeId}][activeId]");
        $activeIdHiddenInput->setValue($activeId);
        $activeIdHiddenInput->setRequired(true);

        $pointsForAnswerInput = new ilNumberInputGUI(
            $this->lng->txt("tst_change_points_for_question"),
            "tmsq[{$questionId}][answers][{$activeId}][points]"
        );
        $pointsForAnswerInput->setDisabled($answer->isScoringCompleted());
        $pointsForAnswerInput->setMinValue(0.00);
        $pointsForAnswerInput->setMaxValue($question->getMaximumPoints());
        $pointsForAnswerInput->allowDecimals(true);
        $pointsForAnswerInput->setRequired(true);
        $pointsForAnswerInput->setDecimals(2);
        $pointsForAnswerInput->setSize(5);

        $userSolutionHtmlAreaInput = new ilHtmlAreaInput($this->plugin->txt("userSolution"));
        $userSolutionHtmlAreaInput->setValue($answer->getAnswerHtml());
        $userSolutionHtmlAreaInput->setEditable(false);
        $userSolutionHtmlAreaInput->setHtmlClass("tmsq-html-area-input");

        $maximumPointsNonEditInput = new ilNonEditableValueGUI(
            $this->lng->txt("tst_manscoring_input_max_points_for_question")
        );
        $maximumPointsNonEditInput->setRequired(true);
        $maximumPointsNonEditInput->setValue($question->getMaximumPoints());

        $manualFeedPackAreaInput = new ilTextAreaInputGUI(
            $this->lng->txt('set_manual_feedback'),
            "tmsq[{$questionId}][answers][{$activeId}][feedback]"
        );

        if ($answer->isScoringCompleted()) {
            $manualFeedPackAreaInput = new ilHtmlAreaInput(
                $this->lng->txt('set_manual_feedback'),
                "tmsq[{$questionId}][answers][{$activeId}][feedback]"
            );
            $manualFeedPackAreaInput->setDisabled(true);
            $manualFeedPackAreaInput->setHtmlClass("tmsq-html-area-input");
        } else {
            $manualFeedPackAreaInput->setUseRTE(true);
            $manualFeedPackAreaInput->setRteTagSet('standard');
        }



        $scoringCompletedCheckboxInput = new ilCheckboxInputGUI(
            $this->lng->txt("finalized_evaluation"),
            "tmsq[{$questionId}][answers][{$activeId}][scoringCompleted]"
        );

        $this->addItem($testRefIdHiddenInput);
        $this->addItem($passHiddenInput);
        $this->addItem($questionIdHiddenInput);
        $this->addItem($activeIdHiddenInput);
        $this->addItem($userSolutionHtmlAreaInput);
        $this->addItem($pointsForAnswerInput);
        $this->addItem($maximumPointsNonEditInput);
        $this->addItem($manualFeedPackAreaInput);

        if ($this->plugin->isAtLeastIlias6()) {
            $this->addItem($scoringCompletedCheckboxInput);
        }

        parent::__construct();
    }

    /**
     * Fills the form values
     * @param Answer $answer
     * @throws Exception
     */
    public function fillForm(Answer $answer)
    {
        if (!$answer->checkValid(true)) {
            throw new Exception("Field not set in QuestionAnswer object");
        }

        $question = $answer->getQuestion();
        $questionId = $question->getId();
        $activeId = $answer->getActiveId();

        $this->setValuesByArray([
            "tmsq[{$questionId}][testRefId]" => $question->getTestRefId(),
            "tmsq[{$questionId}][pass]" => $question->getPass(),
            "tmsq[{$questionId}][questionId]" => $questionId,
            "tmsq[{$questionId}][answers][{$activeId}][activeId]" => $activeId,
            "tmsq[{$questionId}][answers][{$activeId}][points]" => $answer->getPoints(),
            "tmsq[{$questionId}][answers][{$activeId}][feedback]" => $answer->getFeedback(),
            "tmsq[{$questionId}][answers][{$activeId}][scoringCompleted]" => $answer->isScoringCompleted()
        ], true);
    }
}
