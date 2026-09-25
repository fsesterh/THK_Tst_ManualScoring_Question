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

namespace ILIAS\Plugin\TstManualScoringQuestion\Form;

use Exception;
use ilCheckboxInputGUI;
use ilHiddenInputGUI;
use ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput\HtmlAreaInput;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Answer;
use ilLanguage;
use ilNonEditableValueGUI;
use ilNumberInputGUI;
use ilPropertyFormGUI;
use ilTextAreaInputGUI;
use ilTstManualScoringQuestionPlugin;
use ilUtil;

class TstManualScoringForm extends ilPropertyFormGUI
{
    protected ilTstManualScoringQuestionPlugin $plugin;

    public function __construct(ilLanguage $lng, Answer $answer)
    {
        $this->lng = $lng;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();

        $question = $answer->getQuestion();
        $question_id = $question->getId();
        $active_id = $answer->getActiveId();

        $pass_hidden_input = new ilHiddenInputGUI("tmsq[$question_id][pass]");
        $pass_hidden_input->setRequired(true);

        $question_id_hidden_input = new ilHiddenInputGUI("tmsq[$question_id][question_id]");
        $question_id_hidden_input->setRequired(true);

        $active_id_hidden_input = new ilHiddenInputGUI("tmsq[$question_id][answers][$active_id][active_id]");
        $active_id_hidden_input->setValue((string) $active_id);
        $active_id_hidden_input->setRequired(true);

        $points_for_answer_input = new ilNumberInputGUI(
            $this->lng->txt("tst_change_points_for_question"),
            "tmsq[$question_id][answers][$active_id][points]"
        );
        $points_for_answer_input->setDisabled($answer->isScoringCompleted());
        $points_for_answer_input->setMinValue(0.00);
        $points_for_answer_input->setMaxValue($question->getMaximumPoints());
        $points_for_answer_input->allowDecimals(true);
        $points_for_answer_input->setRequired(true);
        $points_for_answer_input->setDecimals(2);
        $points_for_answer_input->setSize(5);

        $user_solution_html_area_input = new HtmlAreaInput($this->plugin->txt("manual_scoring.user_solution"));
        $user_solution_html_area_input->setValue($answer->getAnswerHtml());
        $user_solution_html_area_input->setEditable(false);
        $user_solution_html_area_input->setHtmlClass("tmsq-html-area-input");

        $maximum_points_non_edit_input = new ilNonEditableValueGUI(
            $this->lng->txt("tst_manscoring_input_max_points_for_question")
        );
        $maximum_points_non_edit_input->setRequired(true);
        $maximum_points_non_edit_input->setValue($question->getMaximumPoints());

        $manual_feed_pack_area_input = new ilTextAreaInputGUI(
            $this->lng->txt("set_manual_feedback"),
            "tmsq[$question_id][answers][$active_id][feedback]"
        );

        if ($answer->isScoringCompleted()) {
            $manual_feed_pack_area_input = new HtmlAreaInput(
                $this->lng->txt("set_manual_feedback"),
                "tmsq[$question_id][answers][$active_id][feedback]"
            );
            $manual_feed_pack_area_input->setDisabled(true);
            $manual_feed_pack_area_input->setHtmlClass("tmsq-html-area-input");
        } else {
            $manual_feed_pack_area_input->setUseRTE(true);
            $manual_feed_pack_area_input->setRteTagSet("standard");
        }

        $scoring_completed_checkbox_input = new ilCheckboxInputGUI(
            $this->lng->txt("finalized_evaluation"),
            "tmsq[$question_id][answers][$active_id][scoring_completed]"
        );

        $this->addItem($pass_hidden_input);
        $this->addItem($question_id_hidden_input);
        $this->addItem($active_id_hidden_input);
        $this->addItem($user_solution_html_area_input);
        $this->addItem($points_for_answer_input);
        $this->addItem($maximum_points_non_edit_input);
        $this->addItem($manual_feed_pack_area_input);
        $this->addItem($scoring_completed_checkbox_input);

        parent::__construct();
    }

    public function checkInput(): bool
    {
        $lng = $this->lng;
        /**
         * @var ilNumberInputGUI $item
         */

        $valid = true;
        foreach ($this->getItems() as $item) {
            //Check required
            if ($item->getRequired() && trim((string) $item->getValue()) === "") {
                $item->setAlert($lng->txt("msg_input_is_required"));
                $valid = false;
            }

            switch (true) {
                case $item instanceof ilTextAreaInputGUI:
                    if ($item->usePurifier() && $item->getPurifier()) {
                        $item->setValue(ilUtil::stripOnlySlashes($item->getValue()));
                        $item->setValue($item->getPurifier()->purify($item->getValue()));
                    } else {
                        $allowed = $item->getRteTagString();
                        $value = ($item->getUseRte() || !$item->getUseTagsForRteOnly())
                            ? ilUtil::stripSlashes($item->getValue(), true, $allowed)
                            : $item->stripSlashesAddSpaceFallback($item->getValue());
                        $item->setValue($value);
                    }

                    $item->setValue(ilTextAreaInputGUI::removeProhibitedCharacters($item->getValue()));

                    if ($item->isCharLimited()) {
                        //avoid whitespace surprises. #20630, #20674
                        $ascii_whitespaces = chr(194) . chr(160);
                        $ascii_breaklines = chr(13) . chr(10);

                        $to_replace = [$ascii_whitespaces, $ascii_breaklines, "&lt;", "&gt;", "&amp;"];
                        $replace_to = [" ", "", "_", "_", "_"];

                        #20630 mbstring extension is mandatory for 5.4
                        $chars_entered = mb_strlen(strip_tags(str_replace(
                            $to_replace,
                            $replace_to,
                            $item->getValue()
                        )));

                        if ($item->getMaxNumOfChars() && ($chars_entered > $item->getMaxNumOfChars())) {
                            $item->setAlert($lng->txt("msg_input_char_limit_max"));

                            $valid = false;
                        } elseif ($item->getMinNumOfChars() && ($chars_entered < $item->getMinNumOfChars())) {
                            $item->setAlert($lng->txt("msg_input_char_limit_min"));

                            $valid = false;
                        }
                    }

                    $valid = $valid ? $item->checkSubItemsInput() : false;
                    break;
                case $item instanceof ilNumberInputGUI:
                    if (!is_numeric($item->getValue())) {
                        $item->setMinValue($item->getMinValue(), true);
                        $item->setMaxValue($item->getMaxLength(), true);
                        $item->setAlert($lng->txt("form_msg_numeric_value_required"));
                        $valid = false;
                    }

                    if ($item->minvalueShouldBeGreater()) {
                        if ($item->getMinValue() !== false && $item->getValue() <= $item->getMinValue()) {
                            $item->setMinValue($item->getMinValue(), true);
                            $item->setAlert($lng->txt("form_msg_value_too_low"));
                            $valid = false;
                        }
                    } elseif ($item->getMinValue() !== false && $item->getValue() < $item->getMinValue()) {
                        $item->setMinValue($item->getMinValue(), true);
                        $item->setAlert($lng->txt("form_msg_value_too_low"));
                        $valid = false;
                    }

                    if ($item->maxvalueShouldBeLess()) {
                        if ($item->getMaxValue() !== false && $item->getValue() >= $item->getMaxValue()) {
                            $item->setMaxValue($item->getMaxValue(), true);
                            $item->setAlert($lng->txt("form_msg_value_too_high"));
                            $valid = false;
                        }
                    } elseif ($item->getMaxValue() !== false && $item->getValue() > $item->getMaxValue()) {
                        $item->setMaxValue($item->getMaxValue(), true);
                        $item->setAlert($lng->txt("form_msg_value_too_high"));
                        $valid = false;
                    }

                    $valid = $valid ? $item->checkSubItemsInput() : false;
                    break;
                default:
                    $valid = $valid ? $item->checkInput() : false;
                    break;
            }
        }

        return $valid;
    }

    /**
     * @throws Exception
     */
    public function fillForm(Answer $answer): void
    {
        $question = $answer->getQuestion();
        $question_id = $question->getId();
        $active_id = $answer->getActiveId();

        $this->setValuesByArray([
            "tmsq[$question_id][pass]" => $question->getPass(),
            "tmsq[$question_id][question_id]" => $question_id,
            "tmsq[$question_id][answers][$active_id][active_id]" => $active_id,
            "tmsq[$question_id][answers][$active_id][points]" => $answer->getPoints(),
            "tmsq[$question_id][answers][$active_id][feedback]" => $answer->getFeedback(),
            "tmsq[$question_id][answers][$active_id][scoring_completed]" => $answer->isScoringCompleted()
        ], true);
    }
}
