<?php declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\TstManualScoringQuestion;

use ILIAS\DI\Container;
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
use ilUtil;
use ilObjTestGUI;
use ilToolbarGUI;
use ilSelectInputGUI;
use ilSubmitButton;
use Psr\Http\Message\RequestInterface;
use ILIAS\Plugin\TstManualScoringQuestion\Form\TstManualScoringForm;
use ilAccessHandler;
use ilObjUser;
use Exception;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Question;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Answer;
use ilTestParticipant;
use ilObjAssessmentFolder;

/**
 * Class TstManualScoringQuestion
 * @package TstManualScoringQuestion
 * @author  Marvin Beym <mbeym@databay.de>
 */
class TstManualScoringQuestion
{
    public const ALL_USERS = 0;
    public const ONLY_FINALIZED = 1;
    public const EXCEPT_FINALIZED = 2;

    /**
     * @var ilObjUser
     */
    protected $user;
    /**
     * @var ilAccessHandler
     */
    protected $access;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ilToolbarGUI
     */
    protected $toolbar;
    /**
     * @var UIServices
     */
    protected $ui;
    /**
     * @var ilCtrl
     */
    protected $ctrl;
    /**
     * @var ilTemplate
     */
    protected $mainTpl;
    /**
     * @var ilTstManualScoringQuestionPlugin
     */
    protected $plugin;
    /**
     * @var ilLanguage
     */
    protected $lng;
    /**
     * @var Container
     */
    protected $dic;

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
     * @throws Exception
     */
    public function performCommand(string $cmd, array $query)
    {
        if (!isset($query["ref_id"])) {
            ilUtil::sendFailure($this->plugin->txt("missing_get_parameter_refId"), true);
            $this->plugin->redirectToHome();
        }

        switch (true) {
            case ($cmd === "applyFilter"):
            case ($cmd === "resetFilter"):
                $post = $this->request->getParsedBody();
                $this->handleFilter($cmd, $query, $post);
                break;

            case method_exists($this, $cmd):
                $this->$cmd($this->request->getParsedBody());
                break;

            default:
                ilUtil::sendFailure($this->plugin->txt("cmdNotSupported"), true);
                $this->redirectToManualScoringTab((int) $query["ref_id"]);
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

        $this->mainTpl->addCss($this->plugin->cssFolder("tstManualScoringQuestion.css"));
        $tpl = new ilTemplate($this->plugin->templatesFolder("tpl.manualScoringQuestionPanel.html"), true, true);

        $allowedQuestionTypes = ilObjAssessmentFolder::_getManualScoring();
        $allQuestions = array_filter($test->getAllQuestions(), function ($question) use ($allowedQuestionTypes) {
            return in_array($question["question_type_fi"], $allowedQuestionTypes);
        });

        if (count($allQuestions) == 0) {
            return $this->showNoEntries($tpl);
        }

        $questionOptions = [];
        $pointsTranslated = $this->lng->txt("points");
        foreach ($allQuestions as $questionID => $data) {
            $questionOptions[$questionID] = $data["title"] . " ({$data['points']} {$pointsTranslated}) [ID: {$questionID}]";
        }

        $passOptions = [];
        for ($i = 0; $i < $test->getMaxPassOfTest(); $i++) {
            $passOptions[$i] = (string) ($i + 1);
        }

        $selectedFilters = $this->setupFilter($test->getRefId(), $questionOptions, $passOptions);

        $selectedQuestionId = $selectedFilters["selectedQuestionId"];
        $selectedPass = $selectedFilters["selectedPass"];
        $selectedScoringCompleted = $selectedFilters["selectedScoringCompleted"];
        $selectedAnswersPerPage = $selectedFilters["selectedAnswersPerPage"];

        $questionId = (int) $allQuestions[$selectedQuestionId]["question_id"];
        $question = new Question($questionId);
        $question
            ->setTestRefId($test->getRefId())
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

        //Sort by active_id
        usort($participants, function ($a, $b) {
            return $a->getActiveId() >= $b->getActiveId();
        });

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
                ->setPoints($answer->readReachedPoints())
                ->setScoringCompleted($answer->readScoringCompleted());
            array_push($answers, $answer);
        }

        //Pagination
        $numberOfAnswers = count($answers);
        $paginationData = $this->setupPagination($selectedAnswersPerPage, $numberOfAnswers);

        $tpl->setVariable("PAGINATION_HTML", $paginationData["html"]);

        $paginatedAnswers = array_slice($answers, $paginationData["start"], $paginationData["stop"]);

        if ($this->plugin->isAtLeastIlias6()) {
            $finalAnswerArr = array_filter(
                $paginatedAnswers,
                function (Answer $answer) use ($selectedScoringCompleted) {
                    switch ($selectedScoringCompleted) {
                        case self::ONLY_FINALIZED:
                            return $answer->isScoringCompleted();
                        case self::EXCEPT_FINALIZED:
                            return !$answer->isScoringCompleted();
                        default:
                            return true;
                    }
                }
            );
        } else {
            $finalAnswerArr = $paginatedAnswers;
        }

        $question->setAnswers($finalAnswerArr);

        if (count($question->getAnswers()) > 0) {
            $tpl->setCurrentBlock("question");
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
            $tpl->setVariable("SUBMIT_CMD", 'saveManualScoring');

            $this->ctrl->setParameterByClass(
                ilTstManualScoringQuestionUIHookGUI::class,
                'ref_id',
                $test->getRefId()
            );
            $tpl->setVariable(
                "FORM_ACTION",
                $this->ctrl->getFormActionByClass(
                    [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
                    "saveManualScoring"
                )
            );

            foreach ($question->getAnswers() as $answer) {
                $form = new TstManualScoringForm(
                    $this->lng,
                    $answer
                );

                $form->fillForm($answer);

                $tpl->setCurrentBlock("answer");
                $tpl->setVariable(
                    "QUESTION_HEADER_TEXT",
                    sprintf(
                        "%s %s %s (%s)",
                        $this->plugin->txt("answer_of"),
                        $answer->getParticipant()->getFirstname(),
                        $answer->getParticipant()->getLastname(),
                        $answer->getParticipant()->getLogin()
                    )
                );

                $formHtml = $form->getHTML();
                $formHtml = preg_replace('/<form.*"novalidate">/ms', '', $formHtml);
                $formHtml = preg_replace('/<\/form>/ms', '', $formHtml);

                $tpl->setVariable("ANSWER_FORM", $formHtml);
                $tpl->parseCurrentBlock("answer");
            }

            $tpl->parseCurrentBlock("question");
        } else {
            return $this->showNoEntries($tpl);
        }

        return $tpl->get();
    }

    protected function showNoEntries($tpl) : string
    {
        $tpl->setVariable("NO_ENTRIES", $this->plugin->txt("noEntries"));
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
            $this->plugin->redirectToHome();
        }

        $post = array_filter($post, function ($key) {
            return !in_array($key, ["myCounter", "cmd"]);
        }, ARRAY_FILTER_USE_KEY);

        /**
         * @var Question[] $questions
         */
        $questions = [];

        foreach ($post as $key => $questionData) {
            $question = new Question();
            $question->loadFromPost($questionData);
            array_push($questions, $question);
        }

        $testRefId = -1;

        foreach ($questions as $question) {
            $testRefId = $question->getTestRefId();
            $test = new ilObjTest($testRefId, true);
            $testAccess = new ilTestAccess($test->getRefId(), $test->getTestId());

            if (!$testRefId) {
                ilUtil::sendFailure($this->plugin->txt("unknownError"), true);
                $this->plugin->redirectToHome();
            }

            if (!$testAccess->checkScoreParticipantsAccess()) {
                ilObjTestGUI::accessViolationRedirect();
            }

            foreach ($question->getAnswers() as $answer) {
                if (!$answer->checkValid(true)) {
                    $this->sendInvalidForm($testRefId);
                }

                if (!$answer->readScoringCompleted() && $answer->getPoints() > $question->getMaximumPoints()) {
                    $this->sendInvalidForm($question->getTestRefId());
                }

                if (!$answer->readScoringCompleted() && !$answer->writePoints()) {
                    ilUtil::sendFailure($this->plugin->txt("saving_points_failed"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId());
                }

                if (!$answer->writeFeedback()) {
                    ilUtil::sendFailure($this->plugin->txt("saving_feedback_failed"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId());
                }
            }
        }

        if ($testRefId == -1) {
            ilUtil::sendFailure($this->plugin->txt("unknownError"), true);
            $this->plugin->redirectToHome();
        } else {
            ilUtil::sendSuccess($this->plugin->txt("saving_manualScoring"), true);
            $this->redirectToManualScoringTab($testRefId);
        }
    }

    /**
     * Handles the filtering command
     * @param string $cmd
     * @param array $query
     * @param array $post
     */
    protected function handleFilter(string $cmd, array $query, array $post)
    {
        $filterCommand = $cmd;

        $selectQuestionInput = new ilSelectInputGUI($this->lng->txt("question"), "question");
        $selectQuestionInput->setParent($this->plugin);

        $selectPassInput = new ilSelectInputGUI($this->lng->txt("pass"), "pass");
        $selectPassInput->setParent($this->plugin);

        $selectAnswersPerPageInput = new ilSelectInputGUI($this->plugin->txt("answersPerPage"), "answersPerPage");
        $selectAnswersPerPageInput->setParent($this->plugin);

        $selectScoringCompletedInput = new ilSelectInputGUI(
            $this->lng->txt("finalized_evaluation"),
            "scoringCompleted"
        );
        $selectScoringCompletedInput->setParent($this->plugin);

        switch ($filterCommand) {
            case "applyFilter":
                if (!isset($post["question"])) {
                    ilUtil::sendFailure($this->plugin->txt("filter_missing_question"), true);
                    $this->redirectToManualScoringTab($query["ref_id"]);
                }
                if (!isset($post["pass"])) {
                    ilUtil::sendFailure($this->plugin->txt("filter_missing_pass"), true);
                    $this->redirectToManualScoringTab($query["ref_id"]);
                }

                if (!isset($post["answersPerPage"])) {
                    ilUtil::sendFailure($this->plugin->txt("filter_missing_answersPerPage"), true);
                    $this->redirectToManualScoringTab($query["ref_id"]);
                }

                if ($this->plugin->isAtLeastIlias6()) {
                    if (!isset($post["scoringCompleted"])) {
                        ilUtil::sendFailure($this->plugin->txt("filter_missing_scoringCompleted"), true);
                        $this->redirectToManualScoringTab($query["ref_id"]);
                    }
                    $selectScoringCompletedInput->setValue($post["scoringCompleted"]);
                    $selectScoringCompletedInput->writeToSession();
                }

                $selectQuestionInput->setValue($post["question"]);
                $selectPassInput->setValue($post["pass"]);
                $selectAnswersPerPageInput->setValue($post["answersPerPage"]);

                $selectQuestionInput->writeToSession();
                $selectPassInput->writeToSession();
                $selectAnswersPerPageInput->writeToSession();

                ilUtil::sendSuccess($this->plugin->txt("filter_applied"), true);
                $this->redirectToManualScoringTab($query["ref_id"]);
                break;
            case "resetFilter":
                $selectQuestionInput->clearFromSession();
                $selectPassInput->clearFromSession();
                $selectScoringCompletedInput->clearFromSession();
                $selectAnswersPerPageInput->clearFromSession();

                ilUtil::sendSuccess($this->plugin->txt("filter_reset"), true);
                $this->redirectToManualScoringTab($query["ref_id"]);
                break;
            default:
                ilUtil::sendFailure($this->plugin->txt("filter_invalid_command"), true);
                $this->redirectToManualScoringTab($query["ref_id"]);
                break;
        }
        $this->redirectToManualScoringTab($query["ref_id"]);
    }

    /**
     * Creates the pagination html string
     * Returns an array with the 'html' and 'currentPage' fields
     * @param int $elementsPerPage
     * @param int $totalNumberOfElements
     * @return array
     */
    protected function setupPagination(int $elementsPerPage, int $totalNumberOfElements) : array
    {
        $factory = $this->dic->ui()->factory();
        $renderer = $this->dic->ui()->renderer();
        $url = $this->request->getRequestTarget();

        $parameterName = 'page';
        $query = $this->request->getQueryParams();
        if (isset($query[$parameterName])) {
            $currentPage = (int) $query[$parameterName];
        } else {
            $currentPage = 0;
        }

        $pagination = $factory->viewControl()->pagination()
                              ->withTargetURL($url, $parameterName)
                              ->withTotalEntries($totalNumberOfElements)
                              ->withPageSize($elementsPerPage)
                              ->withCurrentPage($currentPage);

        $start = $pagination->getOffset();
        $stop = $start + $pagination->getPageLength();

        $translation = "";
        if (($totalNumberOfElements) > 1) {
            $translation = sprintf($this->plugin->txt("answersFromTo"), $start + 1, $stop);
        }

        $html = '<div class="tmsq-pagination">' .
            $renderer->render($pagination)
            . '<hr class="tmsq-pagination-separator">'
            . $translation
            . '</div>';

        return [
            "html" => $html,
            "start" => $start,
            "stop" => $stop
        ];
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
        $answersPerPageOptions = range(1, 10);
        $answersPerPageOptions = array_combine($answersPerPageOptions, $answersPerPageOptions);

        //Filter options
        $selectQuestionInput = new ilSelectInputGUI($this->lng->txt("question"), "question");
        $selectQuestionInput->setParent($this->plugin);
        $selectQuestionInput->setOptions($questionOptions);
        $selectQuestionInput->readFromSession();

        $selectPassInput = new ilSelectInputGUI($this->lng->txt("pass"), "pass");
        $selectPassInput->setParent($this->plugin);
        $selectPassInput->setOptions($passOptions);
        $selectPassInput->readFromSession();

        $selectAnswersPerPageInput = new ilSelectInputGUI($this->plugin->txt("answersPerPage"), "answersPerPage");
        $selectAnswersPerPageInput->setParent($this->plugin);
        $selectAnswersPerPageInput->setOptions($answersPerPageOptions);
        $selectAnswersPerPageInput->readFromSession();

        if ($selectAnswersPerPageInput->getValue() == null) {
            $selectAnswersPerPageInput->setValue(10);
        }

        $selectScoringCompletedInput = new ilSelectInputGUI(
            $this->lng->txt("finalized_evaluation"),
            "scoringCompleted"
        );

        if ($this->plugin->isAtLeastIlias6()) {
            $selectScoringCompletedInput->setParent($this->plugin);
            $selectScoringCompletedInput->setOptions([
                self::ALL_USERS => $this->lng->txt('all_users'),
                self::ONLY_FINALIZED => $this->lng->txt('evaluated_users'),
                self::EXCEPT_FINALIZED => $this->lng->txt('not_evaluated_users'),
            ]);
            $selectScoringCompletedInput->readFromSession();
        }

        //Prevent invalid values
        if (!in_array((int) $selectPassInput->getValue(), array_keys($passOptions))) {
            $selectPassInput->setValue((string) array_key_first($passOptions));
        }

        if (!in_array((int) $selectQuestionInput->getValue(), array_keys($questionOptions))) {
            $selectQuestionInput->setValue((string) array_key_first($questionOptions));
        }

        if (!in_array((int) $selectAnswersPerPageInput->getValue(), $answersPerPageOptions)) {
            $selectAnswersPerPageInput->setValue(10);
        }

        //Filter buttons
        $applyFilterButton = ilSubmitButton::getInstance();

        $applyFilterButton->setCaption($this->lng->txt("apply_filter"), false);
        $applyFilterButton->setCommand('applyFilter');

        $resetFilterButton = ilSubmitButton::getInstance();
        $resetFilterButton->setCaption($this->lng->txt("reset_filter"), false);
        $resetFilterButton->setCommand('resetFilter');

        $this->ctrl->setParameterByClass(
            ilTstManualScoringQuestionUIHookGUI::class,
            'ref_id',
            $testRefId
        );
        $filterAction = $this->ctrl->getFormActionByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "applyFilter"
        );

        $this->toolbar->setFormAction($filterAction);
        $this->toolbar->addInputItem($selectQuestionInput, true);
        $this->toolbar->addInputItem($selectPassInput, true);
        $this->toolbar->addInputItem($selectAnswersPerPageInput, true);

        if ($this->plugin->isAtLeastIlias6()) {
            $this->toolbar->addInputItem($selectScoringCompletedInput, true);
        }

        $this->toolbar->addButtonInstance($applyFilterButton);
        $this->toolbar->addButtonInstance($resetFilterButton);

        $returnArr = [
            "selectedQuestionId" => (int) $selectQuestionInput->getValue(),
            "selectedPass" => (int) $selectPassInput->getValue(),
            "selectedAnswersPerPage" => (int) $selectAnswersPerPageInput->getValue(),
        ];

        if ($this->plugin->isAtLeastIlias6()) {
            $returnArr["selectedScoringCompleted"] = (int) $selectScoringCompletedInput->getValue();
        }

        return $returnArr;
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
