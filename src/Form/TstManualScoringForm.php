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

/**
 * Class ManualScoringForm
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringForm extends ilPropertyFormGUI
{
    protected ilTstManualScoringQuestionPlugin $plugin;

    public function __construct(
        ilLanguage $lng,
        array $question,
        string $answerHtml,
        int $activeId,
        int $pass,
        int $testRefId,
        int $currentPointsForAnswer,
        int $maximumPointsForAnswer
    ) {
        $this->lng = $lng;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();

        $questionId = $question["question_id"];

        $activeIdHiddenInput = new ilHiddenInputGUI("questions[{$questionId}][activeId]");
        $activeIdHiddenInput->setValue($activeId);

        $passHiddenInput = new ilHiddenInputGUI("pass");
        $passHiddenInput->setValue($pass);

        $questionIdHiddenInput = new ilHiddenInputGUI("questions[{$questionId}][questionId]");
        $questionIdHiddenInput->setValue($questionId);

        $testRefIdHiddenInput = new ilHiddenInputGUI("testRefId");
        $testRefIdHiddenInput->setValue($testRefId);

        $userSolutionHtmlAreaInput = new ilHtmlAreaInput($this->plugin->txt("userSolutionForQuestion"));
        $userSolutionHtmlAreaInput->setValue($answerHtml);
        $userSolutionHtmlAreaInput->setEditable(false);
        $userSolutionHtmlAreaInput->setHtmlClass("tmsq-html-area-input");

        $pointsForAnswerInput = new ilNumberInputGUI(
            $this->lng->txt("tst_change_points_for_question"),
            "questions[{$questionId}][pointsForAnswer]"
        );
        $pointsForAnswerInput->setMaxValue($maximumPointsForAnswer);
        $pointsForAnswerInput->setValue((int) $currentPointsForAnswer);

        $maximumPointsNonEditInput = new ilNonEditableValueGUI(
            $this->lng->txt("tst_manscoring_input_max_points_for_question")
        );
        $maximumPointsNonEditInput->setValue($maximumPointsForAnswer);

        $this->addItem($questionIdHiddenInput);
        $this->addItem($activeIdHiddenInput);
        $this->addItem($passHiddenInput);
        $this->addItem($testRefIdHiddenInput);
        $this->addItem($userSolutionHtmlAreaInput);
        $this->addItem($pointsForAnswerInput);
        $this->addItem($maximumPointsNonEditInput);
    }
}
