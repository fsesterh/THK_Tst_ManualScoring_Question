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
use assQuestion;

/**
 * Class ManualScoringForm
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringForm extends ilPropertyFormGUI
{
    protected ilTstManualScoringQuestionPlugin $plugin;

    public function __construct(
        ilLanguage $lng,
        int $testRefId,
        int $questionId,
        int $pass,
        int $activeId,
        string $answerHtml
    ) {
        $this->lng = $lng;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();

        $maximumPoints = (int) assQuestion::_getMaximumPoints($questionId);
        $reachedPoints = (int) assQuestion::_getReachedPoints(
            (int) $activeId,
            $questionId,
            $pass
        );

        $testRefIdHiddenInput = new ilHiddenInputGUI("testRefId");
        $testRefIdHiddenInput->setValue($testRefId);

        $passHiddenInput = new ilHiddenInputGUI("pass");
        $passHiddenInput->setValue($pass);

        $questionIdHiddenInput = new ilHiddenInputGUI("questionId");
        $questionIdHiddenInput->setValue($questionId);

        $activeIdHiddenInput = new ilHiddenInputGUI("participants[{$activeId}][activeId]");
        $activeIdHiddenInput->setValue($activeId);

        $pointsForAnswerInput = new ilNumberInputGUI(
            $this->lng->txt("tst_change_points_for_question"),
            "participants[{$activeId}][pointsForAnswer]"
        );
        $pointsForAnswerInput->setMaxValue($maximumPoints);
        $pointsForAnswerInput->setValue((int) $reachedPoints);

        $userSolutionHtmlAreaInput = new ilHtmlAreaInput($this->plugin->txt("userSolutionForQuestion"));
        $userSolutionHtmlAreaInput->setValue($answerHtml);
        $userSolutionHtmlAreaInput->setEditable(false);
        $userSolutionHtmlAreaInput->setHtmlClass("tmsq-html-area-input");

        $maximumPointsNonEditInput = new ilNonEditableValueGUI(
            $this->lng->txt("tst_manscoring_input_max_points_for_question")
        );
        $maximumPointsNonEditInput->setValue($maximumPoints);

        $this->addItem($testRefIdHiddenInput);
        $this->addItem($passHiddenInput);
        $this->addItem($questionIdHiddenInput);
        $this->addItem($activeIdHiddenInput);
        $this->addItem($userSolutionHtmlAreaInput);
        $this->addItem($pointsForAnswerInput);
        $this->addItem($maximumPointsNonEditInput);
    }
}
