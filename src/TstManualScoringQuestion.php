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
use ilTestScoringByQuestionsGUI;
use ilDashboardGUI;
use ilUtil;
use ilObjTestGUI;
use ilToolbarGUI;
use ilSelectInputGUI;
use ilSubmitButton;
use Psr\Http\Message\RequestInterface;
use TstManualScoringQuestion\Form\TstManualScoringForm;
use ilAccessHandler;
use ilObjUser;
use TstManualScoringQuestion\Model\QuestionAnswer;
use Exception;
use TstManualScoringQuestion\Model\Question;
use TstManualScoringQuestion\Model\Answer;
use ilTestParticipant;

/**
 * Class TstManualScoringQuestion
 * @package TstManualScoringQuestion
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringQuestion
{
    protected ilObjUser $user;
    protected ilAccessHandler $access;
    protected RequestInterface $request;
    protected ilToolbarGUI $toolbar;
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
        $this->toolbar = $dic->toolbar();
        $this->lng = $dic->language();
        $this->lng->loadLanguageModule("assessment");
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->ui = $dic->ui();
        $this->ctrl = $dic->ctrl();
        $this->request = $dic->http()->request();
        $this->access = $dic->access();
        $this->user = $dic->user();
    }

    /**
     * @param string   $cmd
     * @param string[] $query
     */
    public function performCommand(string $cmd, array $query)
    {
        if (!isset($query["ref_id"])) {
            ilUtil::sendFailure($this->plugin->txt("noRefIdPassedInQuery"), true);
            $this->ctrl->redirectByClass(ilDashboardGUI::class, "show");
        }
        if (!method_exists($this, $cmd)) {
            ilUtil::sendFailure($this->plugin->txt("cmdNotFound"), true);
            $this->redirectToManualScoringTab((int) $query["ref_id"]);
        }

        switch ($cmd) {
            case "handleFilter":
                $post = $this->request->getParsedBody();
                $this->$cmd($query, $post);
                break;
            case "saveManualScoring":
                $this->$cmd($this->request->getParsedBody());
                break;
            default:
                $this->$cmd();
                break;
        }
    }

    /**
     * Replaces the html for the manual scoring table.
     * @param string $html
     * @param int    $refId
     * @return string
     * @throws ilTemplateException
     * @throws Exception
     */
    public function modify(string $html, int $refId) : string
    {
        $test = new ilObjTest($refId, true);
        $testAccess = new ilTestAccess($test->getRefId(), $test->getTestId());

        if (!$testAccess->checkScoreParticipantsAccess()) {
            ilObjTestGUI::accessViolationRedirect();
        }

        $allQuestions = $test->getAllQuestions();

        $questionOptions = [];
        $pointsTranslated = $this->lng->txt("points");
        foreach ($allQuestions as $questionID => $data) {
            $questionOptions[$questionID] = $data["title"] . " ({$data['points']} {$pointsTranslated}) [ID: {$questionID}]";
        }

        $passOptions = [];
        for ($i = 0; $i < $test->getMaxPassOfTest(); $i++) {
            $passOptions[$i] = (string) ($i + 1);
        }

        $selectedFilters = $this->setupFilter($refId, $questionOptions, $passOptions);

        $selectedQuestionId = $selectedFilters["selectedQuestionId"];
        $selectedPass = $selectedFilters["selectedPass"];

        $this->mainTpl->addCss($this->plugin->cssFolder("tstManualScoringQuestion.css"));
        $tpl = new ilTemplate($this->plugin->templatesFolder("tpl.manualScoringQuestionPanel.html"), true, true);
        $tpl->setVariable(
            "PANEL_HEADER_TEXT",
            sprintf(
                $this->lng->txt("manscoring_results_pass"),
                ($selectedPass + 1)
            ) . " | " . sprintf(
                $this->lng->txt("tst_manscoring_question_section_header"),
                $questionOptions[$selectedQuestionId]
            )
        );

        $tpl->setVariable("SUBMIT_BUTTON_TEXT", $this->lng->txt("save"));
        $tpl->setVariable(
            "FORM_ACTION",
            $this->ctrl->getLinkTargetByClass(
                [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
                "saveManualScoring"
            ) . "&ref_id={$refId}"
        );

        $questionId = (int) $allQuestions[$selectedQuestionId]["question_id"];
        $question = new Question($questionId);
        $question
            ->setTestRefId($refId)
            ->setPass($selectedPass);

        /**
         * @var Answer[] $answers
         */
        $answers = [];

        /**
         * @var ilTestParticipant[] $participants
         */
        $participants = [];
        foreach ($test->getActiveParticipantList() as $participant) {
            array_push($participants, $participant);
        }

        usort($participants, fn ($a, $b) => $a->getActiveId() >= $b->getActiveId());

        foreach ($participants as $participant) {
            if (!$participant->isTestFinished() || $participant->hasUnfinishedPasses()) {
                continue;
            }
            $answer = new Answer($question);
            $answer
                ->setActiveId((int) $participant->getActiveId())
                ->setParticipant($participant)
                ->setAnswerHtml($this->getAnswerDetail(
                    $test,
                    $answer->getActiveId(),
                    $selectedPass,
                    $questionId,
                    $testAccess
                ))
                ->setFeedback($answer->readFeedback())
                ->setPoints($answer->readReachedPoints());
            array_push($answers, $answer);
        }
        $question->setAnswers($answers);

        foreach ($question->getAnswers() as $answer) {
            $form = new TstManualScoringForm(
                $this->lng,
                $answer
            );

            $form->fillForm($answer);

            $tpl->setCurrentBlock("questionAnswer");
            $tpl->setVariable(
                "QUESTION_HEADER_TEXT",
                sprintf(
                    "%s %s %s (%s)",
                    $this->lng->txt("answer_of"),
                    $answer->getParticipant()->getFirstname(),
                    $answer->getParticipant()->getLastname(),
                    $answer->getParticipant()->getLogin(),
                )
            );

            $formHtml = $form->getHTML();
            $formHtml = preg_replace('/<form.*"novalidate">/ms', '', $formHtml);
            $formHtml = preg_replace('/<\/form>/ms', '', $formHtml);

            $tpl->setVariable("MANUAL_SCORING_FORM", $formHtml);
            $tpl->parseCurrentBlock("questionAnswer");
        }
        return $tpl->get();
    }

    /**
     * Handles the saving of the manual scoring form
     * @param array $post
     */
    protected function saveManualScoring(array $post)
    {
        if (!isset($post) || count($post) == 0) {
            ilUtil::sendFailure($this->plugin->txt("nothingReceivedInPost"), true);
            $this->ctrl->redirectByClass(ilDashboardGUI::class, "show");
        }

        $post = array_filter($post, function ($key) {
            return !in_array($key, ["myCounter"]);
        }, ARRAY_FILTER_USE_KEY);

        /**
         * @var QuestionAnswer[] $questionAnswers
         */
        $questionAnswers = [];

        /**
         * @var Question[] $questions
         */
        $questions = [];

        foreach ($post as $questionData) {
            $question = new Question();
            $question->loadFromPost($questionData);
            array_push($questions, $question);
        }

        $testRefId = -1;

        foreach ($questions as $question) {
            $testRefId = $question->getTestRefId();
            foreach ($question->getAnswers() as $answer) {
                if (!$answer->checkValid(true)) {
                    if ($testRefId) {
                        $this->sendInvalidForm($testRefId);
                    } else {
                        ilUtil::sendFailure($this->plugin->txt("unknownError"), true);
                        $this->ctrl->redirectByClass(ilDashboardGUI::class, "show");
                    }
                }

                $test = new ilObjTest($testRefId, true);
                $testAccess = new ilTestAccess($test->getRefId(), $test->getTestId());
                if (!$testAccess->checkScoreParticipantsAccess()) {
                    ilObjTestGUI::accessViolationRedirect();
                }

                if ($answer->getPoints() > $question->getMaximumPoints()) {
                    $this->sendInvalidForm($question->getTestRefId());
                }

                if ($answer->getPoints() !== $answer->readReachedPoints()) {
                    if(!$answer->writePoints()) {
                        ilUtil::sendFailure($this->plugin->txt("saving_points_failed"), true);
                        $this->redirectToManualScoringTab($question->getTestRefId());
                    }
                }

                if ($answer->getFeedback() != $answer->readFeedback()) {
                    if (!$answer->writeFeedback($test)) {
                        ilUtil::sendFailure($this->plugin->txt("saving_feedback_failed"), true);
                        $this->redirectToManualScoringTab($question->getTestRefId());
                    }
                }
            }
        }


        if ($testRefId == -1) {
            ilUtil::sendFailure($this->plugin->txt("unknownError"), true);
            $this->ctrl->redirectByClass(ilDashboardGUI::class, "show");
        } else {
            ilUtil::sendSuccess($this->plugin->txt("manualScoringSaved"), true);
            $this->redirectToManualScoringTab($testRefId);
        }
    }

    /**
     * Handles the filtering command
     * @param array $query
     * @param array $post
     */
    protected function handleFilter(array $query, array $post)
    {
        $filterCommand = array_key_first($post["cmd"]);

        $selectQuestionInput = new ilSelectInputGUI($this->lng->txt("question"), "question");
        $selectPassInput = new ilSelectInputGUI($this->lng->txt("pass"), "pass");
        $selectQuestionInput->setParent($this->plugin);
        $selectPassInput->setParent($this->plugin);

        switch ($filterCommand) {
            case "applyFilter":
                if (!isset($post["question"])) {
                    ilUtil::sendFailure($this->plugin->txt("questionMissingInHandleFilter"), true);
                    $this->redirectToManualScoringTab($query["ref_id"]);
                }
                if (!isset($post["pass"])) {
                    ilUtil::sendFailure($this->plugin->txt("passMissingInHandleFilter"), true);
                    $this->redirectToManualScoringTab($query["ref_id"]);
                }

                $question = $post["question"];
                $pass = $post["pass"];

                $selectQuestionInput->setValue($question);
                $selectPassInput->setValue($pass);

                $selectQuestionInput->writeToSession();
                $selectPassInput->writeToSession();
                ilUtil::sendSuccess($this->plugin->txt("filterWasApplied"), true);
                $this->redirectToManualScoringTab($query["ref_id"]);
                break;
            case "resetFilter":
                $selectQuestionInput->clearFromSession();
                $selectPassInput->clearFromSession();
                ilUtil::sendSuccess($this->plugin->txt("filterWasReset"), true);
                $this->redirectToManualScoringTab($query["ref_id"]);
                break;
            default:
                ilUtil::sendFailure($this->plugin->txt("invalidFilterCommand"), true);
                $this->redirectToManualScoringTab($query["ref_id"]);
                break;
        }
        $this->redirectToManualScoringTab($query["ref_id"]);
    }

    /**
     * Setups the filter toolbar
     * @param int   $testRefId
     * @param array $questionOptions
     * @param array $passOptions
     * @return array
     */
    protected function setupFilter(int $testRefId, array $questionOptions, array $passOptions) : array
    {
        //Filter options
        $selectQuestionInput = new ilSelectInputGUI($this->lng->txt("question"), "question");
        $selectQuestionInput->setParent($this->plugin);
        $selectQuestionInput->setOptions($questionOptions);
        $selectQuestionInput->readFromSession();

        $selectPassInput = new ilSelectInputGUI($this->lng->txt("pass"), "pass");
        $selectPassInput->setParent($this->plugin);
        $selectPassInput->setOptions($passOptions);
        $selectPassInput->readFromSession();

        //Prevent invalid values
        if (!in_array((int) $selectPassInput->getValue(), array_keys($passOptions))) {
            $selectPassInput->setValue((string) array_key_first($passOptions));
        }

        if (!in_array((int) $selectQuestionInput->getValue(), array_keys($questionOptions))) {
            $selectQuestionInput->setValue((string) array_key_first($questionOptions));
        }

        //Filter buttons
        $applyFilterButton = ilSubmitButton::getInstance();

        $applyFilterButton->setCaption($this->lng->txt("apply_filter"));
        $applyFilterButton->setCommand('applyFilter');

        $resetFilterButton = ilSubmitButton::getInstance();
        $resetFilterButton->setCaption($this->lng->txt("reset_filter"));
        $resetFilterButton->setCommand('resetFilter');

        $filterAction = $this->ctrl->getLinkTargetByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "handleFilter",
            "",
            true
        ) . "&ref_id={$testRefId}";

        $this->toolbar->setFormAction($filterAction);
        $this->toolbar->addInputItem($selectQuestionInput, true);
        $this->toolbar->addInputItem($selectPassInput, true);
        $this->toolbar->addButtonInstance($applyFilterButton);
        $this->toolbar->addButtonInstance($resetFilterButton);

        return [
            "selectedQuestionId" => (int) $selectQuestionInput->getValue(),
            "selectedPass" => (int) $selectPassInput->getValue(),
        ];
    }

    /**
     * Gets the answer detail html string to be displayed in the form
     * @param ilObjTest    $test
     * @param int          $activeId
     * @param int          $pass
     * @param int          $questionId
     * @param ilTestAccess $testAccess
     * @return string
     */
    protected function getAnswerDetail(
        ilObjTest $test,
        int $activeId,
        int $pass,
        int $questionId,
        ilTestAccess $testAccess
    ) : string {
        if (!$testAccess->checkScoreParticipantsAccessForActiveId($activeId)) {
            ilObjTestGUI::accessViolationRedirect();
        }

        $question_gui = $test->createQuestionGUI('', $questionId);

        return $question_gui->getSolutionOutput(
            $activeId,
            $pass,
            false,
            false,
            false,
            $test->getShowSolutionFeedback(),
            false,
            true
        );
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

    /**
     * Sends an invalid form message and redirects to the manual scoring tab of the test (refId)
     * @param $refId
     */
    protected function sendInvalidForm($refId)
    {
        ilUtil::sendFailure($this->lng->txt("form_input_not_valid"), true);
        $this->redirectToManualScoringTab($refId);
    }
}
