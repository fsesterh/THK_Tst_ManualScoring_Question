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
use ilFormPropertyGUI;
use Psr\Http\Message\RequestInterface;
use ilTextAreaInputGUI;
use TstManualScoringQuestion\Form\Input\RichTextInput\ilRichTextInput;

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
        //$testRefIdHiddenInput->setValue($questionAnswer->getTestRefId());

        $passHiddenInput = new ilHiddenInputGUI("{$activeId}[pass]");
        //$passHiddenInput->setValue($questionAnswer->getPass());
        $passHiddenInput->setRequired(true);

        $questionIdHiddenInput = new ilHiddenInputGUI("{$activeId}[questionId]");
        //$questionIdHiddenInput->setValue($questionAnswer->getQuestionId());
        $questionIdHiddenInput->setRequired(true);

        $activeIdHiddenInput = new ilHiddenInputGUI("{$activeId}[activeId]");
        $activeIdHiddenInput->setValue($activeId);
        $activeIdHiddenInput->setRequired(true);

        $pointsForAnswerInput = new ilNumberInputGUI(
            $this->lng->txt("tst_change_points_for_question"),
            "{$activeId}[pointsForAnswer]"
        );
        $pointsForAnswerInput->setMinValue(0.00);
        $pointsForAnswerInput->setMaxValue((float) $maximumPoints);
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

        $manualFeedPackAreaInput = new ilTextAreaInputGUI($this->lng->txt('set_manual_feedback'), "{$activeId}[feedback]");
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
    }

    /**
     * Fills the form values
     * @param int    $activeId
     * @param int    $pass
     * @param float  $pointsForAnswer
     * @param int    $questionId
     * @param int    $testRefId
     * @param string $feedback
     */
    public function fillForm(int $activeId, int $pass, float $pointsForAnswer, int $questionId, int $testRefId, string $feedback = "")
    {
        $this->setValuesByArray([
            "{$activeId}[testRefId]" => $testRefId,
            "{$activeId}[pass]" => $pass,
            "{$activeId}[questionId]" => $questionId,
            "{$activeId}[activeId]" => $activeId,
            "{$activeId}[pointsForAnswer]" => $pointsForAnswer,
            "{$activeId}[feedback]" => $feedback
        ], true);
    }
}
