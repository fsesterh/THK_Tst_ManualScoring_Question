<?php declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Form;

use ilPropertyFormGUI;
use ilTstManualScoringQuestionPlugin;
use TstManualScoringQuestion\Form\Input\HtmlAreaInput\ilHtmlAreaInput;
use ilNonEditableValueGUI;
use ilLanguage;
use ilNumberInputGUI;
use ilHiddenInputGUI;
use Psr\Http\Message\RequestInterface;
use ilTextAreaInputGUI;
use TstManualScoringQuestion\Model\QuestionAnswer;
use Exception;

/**
 * Class ManualScoringForm
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringForm extends ilPropertyFormGUI
{
    protected RequestInterface $request;
    protected int $activeId;
    protected ilTstManualScoringQuestionPlugin $plugin;

    public function __construct(
        ilLanguage $lng,
        int $activeId,
        float $maximumPoints,
        string $answerText = ""
    ) {
        global $DIC;
        $this->request = $DIC->http()->request();
        $this->lng = $lng;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->activeId = $activeId;

        $testRefIdHiddenInput = new ilHiddenInputGUI("{$activeId}[testRefId]");
        $testRefIdHiddenInput->setRequired(true);

        $passHiddenInput = new ilHiddenInputGUI("{$activeId}[pass]");
        $passHiddenInput->setRequired(true);

        $questionIdHiddenInput = new ilHiddenInputGUI("{$activeId}[questionId]");
        $questionIdHiddenInput->setRequired(true);

        $activeIdHiddenInput = new ilHiddenInputGUI("{$activeId}[activeId]");
        $activeIdHiddenInput->setValue($activeId);
        $activeIdHiddenInput->setRequired(true);

        $pointsForAnswerInput = new ilNumberInputGUI(
            $this->lng->txt("tst_change_points_for_question"),
            "{$activeId}[pointsForAnswer]"
        );
        $pointsForAnswerInput->setMinValue(0.00);
        $pointsForAnswerInput->setMaxValue($maximumPoints);
        $pointsForAnswerInput->allowDecimals(true);
        $pointsForAnswerInput->setRequired(true);
        $pointsForAnswerInput->setDecimals(2);

        $userSolutionHtmlAreaInput = new ilHtmlAreaInput($this->plugin->txt("userSolutionForQuestion"));
        $userSolutionHtmlAreaInput->setValue($answerText);
        $userSolutionHtmlAreaInput->setEditable(false);
        $userSolutionHtmlAreaInput->setHtmlClass("tmsq-html-area-input");

        $maximumPointsNonEditInput = new ilNonEditableValueGUI(
            $this->lng->txt("tst_manscoring_input_max_points_for_question")
        );
        $maximumPointsNonEditInput->setRequired(true);
        $maximumPointsNonEditInput->setValue($maximumPoints);

        $manualFeedPackAreaInput = new ilTextAreaInputGUI(
            $this->lng->txt('set_manual_feedback'),
            "{$activeId}[feedback]"
        );
        $manualFeedPackAreaInput->setUseRTE(true);
        $manualFeedPackAreaInput->setRteTagSet('standard');

        $this->addItem($testRefIdHiddenInput);
        $this->addItem($passHiddenInput);
        $this->addItem($questionIdHiddenInput);
        $this->addItem($activeIdHiddenInput);
        $this->addItem($userSolutionHtmlAreaInput);
        $this->addItem($pointsForAnswerInput);
        $this->addItem($maximumPointsNonEditInput);
        $this->addItem($manualFeedPackAreaInput);

        parent::__construct();
    }

    /**
     * Fills the form values
     * @param QuestionAnswer $questionAnswer
     * @throws Exception
     */
    public function fillForm(QuestionAnswer $questionAnswer)
    {
        if (!$questionAnswer->checkValid()) {
            throw new Exception("Field not set in QuestionAnswer object");
        }
        $activeId = $questionAnswer->getActiveId();

        $this->setValuesByArray([
            "{$activeId}[testRefId]" => $questionAnswer->getTestRefId(),
            "{$activeId}[pass]" => $questionAnswer->getPass(),
            "{$activeId}[questionId]" => $questionAnswer->getQuestionId(),
            "{$activeId}[activeId]" => $activeId,
            "{$activeId}[pointsForAnswer]" => $questionAnswer->getPoints(),
            "{$activeId}[feedback]" => $questionAnswer->getFeedback()
        ], true);
    }
}
