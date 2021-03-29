<?php declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion;

use ILIAS\DI\Container;
use ilGlobalPageTemplate;
use ilTstManualScoringQuestionPlugin;
use ilTemplate;
use ilTemplateException;
use ilObjTest;
use ilLanguage;
use ilTestAccess;
use ilCtrl;
use ILIAS\DI\UIServices;
use ilTstManualScoringQuestionUIHookGUI;
use ilUIPluginRouterGUI;
use assQuestion;
use assTextQuestionGUI;
use ilTestScoringByQuestionsGUI;
use ilDashboardGUI;
use ilUtil;
use ilObjTestGUI;

/**
 * Class TstManualScoringQuestion
 * @package TstManualScoringQuestion
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringQuestion
{
    protected UIServices $ui;
    protected ilCtrl $ctrl;
    protected ilGlobalPageTemplate $mainTpl;
    protected ilTstManualScoringQuestionPlugin $plugin;
    protected ilLanguage $lng;
    protected Container $dic;

    public function __construct(Container $dic = null)
    {
        if ($dic == null) {
            global $DIC;
            $this->dic = $DIC;
        } else {
            $this->dic = $dic;
        }

        $this->mainTpl = $dic->ui()->mainTemplate();
        $this->lng = $dic->language();
        $this->lng->loadLanguageModule("assessment");
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->ui = $dic->ui();
        $this->ctrl = $dic->ctrl();
    }

    /**
     * @param string   $cmd
     * @param string[] $query
     */
    public function performCommand(string $cmd, array $query)
    {
        if (!isset($query["ref_id"])) {
            ilUtil::sendFailure($this->plugin->txt("noRefIdPassedInQuery"));
            $this->ctrl->redirectByClass(ilDashboardGUI::class, "show");
        }
        if (method_exists($this, $cmd)) {
            $this->$cmd($query);
        } else {
            ilUtil::sendFailure($this->plugin->txt("cmdNotFound"), true);
            $this->redirectToManualScoringTab((int) $query["ref_id"]);
        }
    }

    /**
     * Replaces the html for the manual scoring table.
     * @param string $html
     * @param int    $refId
     * @return string
     * @throws ilTemplateException
     */
    public function modify(string $html, int $refId) : string
    {
        $test = new ilObjTest($refId, true);
        $testAccess = new ilTestAccess($test->getRefId(), $test->getTestId());

        if (!$testAccess->checkCorrectionsAccess()) {
            return $html;
        }

        $allQuestions = $test->getAllQuestions();

        $questionOptions = [];
        $pointsTranslated = $this->lng->txt("points");
        foreach ($allQuestions as $questionID => $data) {
            $questionOptions[$questionID] = $data["title"] . " ({$data['points']} {$pointsTranslated}) [ID: {$questionID}]";
        }

        $passOptions = [];
        for ($i = 1; $i < $test->getMaxPassOfTest() + 1; $i++) {
            $passOptions[$i] = (string) $i;
        }

        $selectQuestion = $this->ui
            ->factory()
            ->input()
            ->field()
            ->select($this->lng->txt("question"), $questionOptions)
            ->withValue(array_key_first($questionOptions));

        $selectPass = $this->ui
            ->factory()
            ->input()
            ->field()
            ->select($this->lng->txt("pass"), $passOptions)
            ->withValue("1");

        $filterAction = $this->ctrl->getLinkTargetByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "handleFilter",
            "",
            true
        ) . "&ref_id={$refId}";

        $filter = $this->dic->uiService()->filter()->standard(
            "tmsq_{$refId}_filter",
            $filterAction,
            [
                "selectQuestion" => $selectQuestion,
                "selectPass" => $selectPass,
            ],
            [true, true],
            true,
            true,
        );

        //$filterData = $this->dic->uiService()->filter()->getData($filter);

        $renderedFilter = $this->ui->renderer()->render($filter) /* . "Filter Data: " . print_r($filterData, true) */
        ;

        $this->mainTpl->addCss($this->plugin->cssFolder("tstManualScoringQuestion.css"));
        $tpl = new ilTemplate($this->plugin->templatesFolder("tpl.manualScoringQuestionPanel.html"), true, true);
        $tpl->setVariable("FILTER", $renderedFilter);
        $tpl->setVariable("PANEL_HEADER_TEXT", sprintf($this->lng->txt("manscoring_results_pass"), "1"));
        $tpl->setVariable("SUBMIT_BUTTON_TEXT", $this->lng->txt("save"));
        $tpl->setVariable("FORM_ACTION", $this->ctrl->getLinkTargetByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "saveManualScoring"
        ) . "&ref_id={$refId}");

        foreach ($allQuestions as $question) {
            foreach ($test->getActiveParticipantList() as $participant) {
                if (!$participant->isTestFinished() || $participant->hasUnfinishedPasses()) {
                    continue;
                }

                $questionId = $question["question_id"];

                $answerHtml = $this->getAnswerDetail(
                    $test,
                    (int) $participant->getActiveId(),
                    1,
                    (int) $questionId,
                    $testAccess
                );

                $tpl->setVariable(
                    "QUESTION_HEADER_TEXT",
                    sprintf(
                        $this->lng->txt("tst_manscoring_question_section_header"),
                        $questionOptions[array_key_first($questionOptions)]
                    )
                );
                $tpl->setVariable("ANSWER", $answerHtml);
                $tpl->parseCurrentBlock("test");
            }
        }

        return $tpl->get();
    }

    /**
     * @param array $query
     */
    protected function saveManualScoring(array $query)
    {
        ilUtil::sendSuccess($this->plugin->txt("manualScoringSaved"), true);

        $this->redirectToManualScoringTab($query["ref_id"]);
    }

    protected function handleFilter(array $query)
    {
        switch ($query["cmdFilter"]) {
            case "apply":
                ilUtil::sendSuccess($this->plugin->txt("filterWasApplied"), true);

                $mappedInputs = [];
                foreach ($query as $key => $value) {
                    if (str_starts_with($key, "__filter_status_")) {
                        $mappedInputs[str_replace("__filter_status_", "", $key)] = $value;
                    }
                }

                break;
            case  "reset":
                ilUtil::sendSuccess($this->plugin->txt("filterWasReset"), true);

                break;
        }

        $this->redirectToManualScoringTab($query["ref_id"]);
    }

    /**
     * @param ilObjTest    $test
     * @param int          $activeId
     * @param int          $pass
     * @param int          $questionId
     * @param ilTestAccess $testAccess
     * @return string
     * @throws ilTemplateException
     */
    protected function getAnswerDetail(
        ilObjTest $test,
        int $activeId,
        int $pass,
        int $questionId,
        ilTestAccess $testAccess
    ) {
        if (!$testAccess->checkScoreParticipantsAccessForActiveId($activeId)) {
            return "";
        }

        $data = $test->getCompleteEvaluationData(false);
        $participant = $data->getParticipant($activeId);
        $question_gui = $test->createQuestionGUI('', $questionId);
        $tmp_tpl = new ilTemplate('tpl.il_as_tst_correct_solution_output.html', true, true, 'Modules/Test');
        if ($question_gui instanceof assTextQuestionGUI && $test->getAutosave()) {
            $aresult_output = $question_gui->getAutoSavedSolutionOutput(
                $activeId,
                $pass,
                false,
                false,
                false,
                $test->getShowSolutionFeedback(),
                false,
                true
            );
            $tmp_tpl->setVariable('TEXT_ASOLUTION_OUTPUT', $this->lng->txt('autosavecontent'));
            $tmp_tpl->setVariable('ASOLUTION_OUTPUT', $aresult_output);
        }
        $result_output = $question_gui->getSolutionOutput(
            $activeId,
            $pass,
            false,
            false,
            false,
            $test->getShowSolutionFeedback(),
            false,
            true
        );
        $max_points = $question_gui->object->getMaximumPoints();

        $tmp_tpl->setVariable('TEXT_YOUR_SOLUTION', $this->lng->txt('answers_of') . ' ' . $participant->getName());
        $suggested_solution = assQuestion::_getSuggestedSolutionOutput($questionId);
        if ($test->getShowSolutionSuggested() && strlen($suggested_solution) > 0) {
            $tmp_tpl->setVariable('TEXT_SOLUTION_HINT', $this->lng->txt("solution_hint"));
            $tmp_tpl->setVariable("SOLUTION_HINT", assQuestion::_getSuggestedSolutionOutput($questionId));
        }

        $tmp_tpl->setVariable('TEXT_SOLUTION_OUTPUT', $this->lng->txt('question'));
        $tmp_tpl->setVariable('TEXT_RECEIVED_POINTS', $this->lng->txt('scoring'));
        $add_title = ' [' . $this->lng->txt('question_id_short') . ': ' . $questionId . ']';
        $question_title = $test->getQuestionTitle($question_gui->object->getTitle());
        $lng = $this->lng->txt('points');
        if ($max_points == 1) {
            $lng = $this->lng->txt('point');
        }

        $tmp_tpl->setVariable(
            'QUESTION_TITLE',
            $question_title . ' (' . $max_points . ' ' . $lng . ')' . $add_title
        );
        $tmp_tpl->setVariable('SOLUTION_OUTPUT', $result_output);

        $tmp_tpl->setVariable(
            'RECEIVED_POINTS',
            sprintf(
                $this->lng->txt('part_received_a_of_b_points'),
                $question_gui->object->getReachedPoints($activeId, $pass),
                $max_points
            )
        );

        return $tmp_tpl->get();
    }

    /**
     * Redirects the user to the manual scoring by question sub tab
     * @param int|string $refId
     */
    protected function redirectToManualScoringTab($refId)
    {
        $this->ctrl->setParameterByClass(ilTestScoringByQuestionsGUI::class, "ref_id", (int) $refId);
        $this->ctrl->redirectByClass(
            [ilObjTestGUI::class, ilTestScoringByQuestionsGUI::class],
            "showManScoringByQuestionParticipantsTable"
        );
    }
}
