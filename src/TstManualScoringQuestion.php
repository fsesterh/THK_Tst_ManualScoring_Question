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
use ilObjAssessmentFolder;
use ilLogger;
use assQuestion;
use ilTestParticipantData;
use ilTestParticipantAccessFilter;
use ilTestEvaluationUserData;
use ReflectionException;
use ReflectionMethod;
use ilTestScoringByQuestionsGUI;
use ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput\ilHtmlAreaInput;

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
     * @var array
     */
    protected $answersAndForms = [];
    /**
     * @var ilLogger
     */
    protected $logger;
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
        $this->logger = $dic->logger()->root();
    }

    protected function readScoringCompleted(int $questionId, int $activeId, int $pass) : bool
    {
        if (ilTstManualScoringQuestionPlugin::getInstance()->isAtLeastIlias6()) {
            $result = $this->dic->database()->queryF(
                "SELECT finalized_evaluation FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
                ['integer', 'integer', 'integer'],
                [$activeId, $questionId, $pass]
            );
            if ($result->numRows()) {
                $row = $this->dic->database()->fetchAssoc($result);
                if (!isset($row["finalized_evaluation"])) {
                    return false;
                }
                return (bool) $row["finalized_evaluation"];
            }
            return false;
        }
        return false;
    }

    /**
     * Returns an array of answer data
     * Code for retrieving data copied from class.ilTestScoringByQuestionsGUI.php
     * @param ilObjTest $test
     * @param int       $pass
     * @param int       $questionId
     * @return array
     */
    protected function getAnswerData(ilObjTest $test, int $pass, int $questionId) : array
    {
        $answersData = [];
        $data = $test->getCompleteEvaluationData(false);
        $participants = $data->getParticipants();

        $participantData = new ilTestParticipantData($this->dic->database(), $this->lng);
        $participantData->setActiveIdsFilter(array_keys($data->getParticipants()));

        $participantData->setParticipantAccessFilter(
            ilTestParticipantAccessFilter::getScoreParticipantsUserFilter($test->getRefId())
        );

        $participantData->load($test->getTestId());

        foreach ($participantData->getActiveIds() as $active_id) {

            /** @var $participant ilTestEvaluationUserData */
            $participant = $participants[$active_id];
            $testResultData = $test->getTestResult($active_id, $pass);
            foreach ($testResultData as $questionData) {
                if (!isset($questionData['qid']) || $questionData['qid'] != $questionId) {
                    continue;
                }

                $user = ilObjUser::_getUserData([$participant->user_id]);
                $answersData[] = [
                    'active_id' => $active_id,
                    'reached_points' => assQuestion::_getReachedPoints($active_id, $questionData['qid'], $pass),
                    'participant' => $participant,
                    'lastname' => $user[0]['lastname'],
                    'firstname' => $user[0]['firstname'],
                    'login' => $participant->getLogin(),
                ];
            }
        }
        return $answersData;
    }

    /**
     * Generates an array of question options to be used for the select field
     * @param ilObjTest $test
     * @return array
     */
    protected function generateQuestionOptions(ilObjTest $test) : array
    {
        $questionOptions = [];
        if (!$test->isRandomTest()) {
            $questions = $test->getTestQuestions();
        } else {
            $questions = $test->getPotentialRandomTestQuestions();
        }

        $enabledManualScoringTypes = ilObjAssessmentFolder::_getManualScoring();

        foreach ($questions as $questionData) {
            if (!in_array($questionData["question_type_fi"], $enabledManualScoringTypes)) {
                continue;
            }

            $questionId = $questionData["question_id"];
            $title = $questionData["title"];
            $points = $questionData["points"];
            $questionOptions[$questionId] = $title . " ({$points} {$this->lng->txt("points")}) [ID: {$questionId}]";
        }
        return $questionOptions;
    }

    /**
     * Returns an array of pass options
     * @param ilObjTest $test
     * @return array
     */
    protected function generatePassOptions(ilObjTest $test) : array
    {
        $passOptions = [];
        for ($i = 0; $i < $test->getMaxPassOfTest(); $i++) {
            $passOptions[$i] = (string) ($i + 1);
        }
        return $passOptions;
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
     * @param int $refId
     * @return string
     * @throws ilTemplateException
     */
    public function modify(int $refId) : string
    {
        $test = new ilObjTest($refId, true);
        $testAccess = new ilTestAccess($test->getRefId(), $test->getTestId());

        if (!$testAccess->checkScoreParticipantsAccess()) {
            ilObjTestGUI::accessViolationRedirect();
        }

        $this->mainTpl->addCss($this->plugin->cssFolder("tstManualScoringQuestion.css"));
        $tpl = new ilTemplate($this->plugin->templatesFolder("tpl.manualScoringQuestionPanel.html"), true, true);

        $questionOptions = $this->generateQuestionOptions($test);

        if (count($questionOptions) == 0) {
            return $this->showNoEntries($tpl);
        }

        $selectedFilters = $this->setupFilter($test->getRefId(), $questionOptions, $this->generatePassOptions($test));
        $selectedQuestionId = $selectedFilters["selectedQuestionId"];
        $selectedPass = $selectedFilters["selectedPass"];
        $selectedScoringCompleted = $selectedFilters["selectedScoringCompleted"];
        $selectedAnswersPerPage = $selectedFilters["selectedAnswersPerPage"];

        $this->logger->debug("TMSQ : Selected filters: pass={$selectedPass} | scoringCompleted=$selectedScoringCompleted | answersPerPage={$selectedAnswersPerPage}");

        $question = new Question($selectedQuestionId);
        $question
            ->setTestRefId($test->getRefId())
            ->setPass($selectedPass);

        //Pagination
        $answersData = $this->getAnswerData($test, $selectedPass, $selectedQuestionId);

        if ($this->plugin->isAtLeastIlias6()) {
            $this->logger->debug("TMSQ : ilias 6 filtering by user scoring state");
            $answersData = array_filter(
                $answersData,
                function (array $answerData) use ($question, $selectedScoringCompleted) {
                    $scoringCompleted = $this->readScoringCompleted(
                        $question->getId(),
                        (int) $answerData["active_id"],
                        $question->getPass()
                    );
                    switch ($selectedScoringCompleted) {
                        case self::ONLY_FINALIZED:
                            return $scoringCompleted;
                        case self::EXCEPT_FINALIZED:
                            return !$scoringCompleted;
                        default:
                            return true;
                    }
                }
            );
        }

        $numberOfAnswersData = count($answersData);
        $paginationData = $this->setupPagination($selectedAnswersPerPage, $numberOfAnswersData);
        $currentPage = $paginationData["currentPage"];
        $tpl->setVariable("PAGINATION_HTML", $paginationData["html"]);

        $paginatedAnswersData = array_slice($answersData, $paginationData["start"], $paginationData["stop"]);

        foreach ($paginatedAnswersData as $answerData) {
            $answer = new Answer($question);
            $answer
                ->setActiveId((int) $answerData["active_id"])
                ->setUserName($answerData["participant"]->getName())
                ->setLogin($answerData["login"])
                ->setAnswerHtml($this->getAnswerDetail(
                    $answerData["participant"],
                    $test,
                    $answer->getActiveId(),
                    $selectedPass,
                    $selectedQuestionId,
                    $testAccess
                ))
                ->setFeedback($answer->readFeedback())
                ->setPoints((float) $answerData["reached_points"])
                ->setScoringCompleted($answer->readScoringCompleted());
            $question->addAnswer($answer);
            $this->logger->debug("TMSQ : Added answer of activeId {$answer->getActiveId()} for questionId {$question->getId()}");
        }

        $this->logger->debug("TMSQ : Answers array sliced by pagination. Number of answers before {$numberOfAnswersData} now " . count($question->getAnswers()));

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

            $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "page", $currentPage);
            $tpl->setVariable(
                "FORM_ACTION",
                $this->ctrl->getFormActionByClass(
                    [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
                    "saveManualScoring"
                )
            );

            if (count($this->answersAndForms) > 0) {
                foreach ($this->answersAndForms as $answerAndForm) {
                    $correctAnswer = null;
                    $form = null;
                    foreach ($question->getAnswers() as $answer) {
                        if ($answer->getActiveId() === $answerAndForm["answer"]->getActiveId()) {
                            $correctAnswer = $answer;
                            $form = $answerAndForm["form"];
                            foreach ($form->getItems() as $item) {
                                if ($item instanceof ilHtmlAreaInput) {
                                    $item->setValue($correctAnswer->getAnswerHtml());
                                    break;
                                }
                            }

                            break;
                        }
                    }
                    $tpl->setCurrentBlock("answer");
                    $tpl->setVariable(
                        "QUESTION_HEADER_TEXT",
                        sprintf(
                            "%s %s (%s)",
                            $this->plugin->txt("answer_of"),
                            $correctAnswer->getUserName(),
                            $correctAnswer->getLogin()
                        )
                    );

                    $formHtml = $form->getHTML();
                    $formHtml = preg_replace('/<form.*"novalidate">/ms', '', $formHtml);
                    $formHtml = preg_replace('/<\/form>/ms', '', $formHtml);

                    $tpl->setVariable("ANSWER_FORM", $formHtml);
                    $tpl->parseCurrentBlock("answer");
                }
            } else {
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
                            "%s %s (%s)",
                            $this->plugin->txt("answer_of"),
                            $answer->getUserName(),
                            $answer->getLogin()
                        )
                    );

                    $formHtml = $form->getHTML();
                    $formHtml = preg_replace('/<form.*"novalidate">/ms', '', $formHtml);
                    $formHtml = preg_replace('/<\/form>/ms', '', $formHtml);

                    $tpl->setVariable("ANSWER_FORM", $formHtml);
                    $tpl->parseCurrentBlock("answer");
                }
            }

            $tpl->parseCurrentBlock("question");
        } else {
            $this->logger->debug("TMSQ : no answers available, show no entries message");
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
     * Shows the tmsq manual scoring on a new page,
     * preventing ilias from rendering the normal view first.
     * @throws ilTemplateException
     * @throws ReflectionException
     */
    protected function showTmsqManualScoring()
    {
        $query = $this->request->getQueryParams();
        $refId = (int) $query["ref_id"];
        $this->drawHeader($refId);
        $this->dic->tabs()->setBackTarget(
            $this->lng->txt("back"),
            $this->getManualScoringByQuestionTarget($refId)
        );

        if ($this->plugin->isAtLeastIlias6()) {
            $this->mainTpl->loadStandardTemplate();
        } else {
            $this->mainTpl->getStandardTemplate();
        }

        $this->mainTpl->setContent($this->modify($refId));

        if ($this->plugin->isAtLeastIlias6()) {
            $this->dic->ui()->mainTemplate()->printToStdOut();
        } else {
            $this->mainTpl->show();
        }
    }

    /**
     * Handles the saving of the manual scoring form
     * @param array $post
     * @throws Exception
     */
    protected function saveManualScoring(array $post)
    {
        if (!isset($post) || count($post) == 0) {
            ilUtil::sendFailure($this->plugin->txt("nothingReceivedInPost"), true);
            $this->plugin->redirectToHome();
        }

        $query = $this->request->getQueryParams();
        if (isset($query["page"])) {
            $currentPage = (int) $query["page"];
        } else {
            $currentPage = -1;
        }

        if (!isset($post["tmsq"]) || !is_array($post["tmsq"])) {
            ilUtil::sendFailure($this->plugin->txt("invalid_post_data"), true);
            $this->plugin->redirectToHome();
        }

        $postData = $post["tmsq"];

        /**
         * @var Question[] $questions
         */
        $questions = [];

        foreach ($postData as $key => $questionData) {
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

            //Check all answer forms
            $formsValid = true;
            $this->answersAndForms = [];
            foreach ($question->getAnswers() as $answer) {
                $form = new TstManualScoringForm($this->lng, $answer);
                $form->fillForm($answer);
                if (!$form->checkInput()) {
                    $formsValid = false;
                }
                $this->answersAndForms[] = ["answer" => $answer, "form" => $form];
            }

            if (!$formsValid) {
                ilUtil::sendFailure($this->lng->txt("form_input_not_valid"), true);
                $this->showTmsqManualScoring();
                return;
            }

            foreach ($question->getAnswers() as $answer) {
                $scoringCompleted = $answer->readScoringCompleted();

                if (!$scoringCompleted && $answer->getPoints() > $question->getMaximumPoints()) {
                    $this->sendInvalidForm($question->getTestRefId());
                }

                if (!$scoringCompleted && !$answer->writePoints()) {
                    ilUtil::sendFailure($this->plugin->txt("saving_points_failed"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId(), $currentPage);
                }

                if (!$answer->writeFeedback()) {
                    ilUtil::sendFailure($this->plugin->txt("saving_feedback_failed"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId(), $currentPage);
                }
            }
        }

        if ($testRefId == -1) {
            ilUtil::sendFailure($this->plugin->txt("unknownError"), true);
            $this->plugin->redirectToHome();
        } else {
            ilUtil::sendSuccess($this->plugin->txt("saving_manualScoring"), true);
            $this->redirectToManualScoringTab($testRefId, $currentPage);
        }
    }

    /**
     * Handles the filtering command
     * @param string $cmd
     * @param array  $query
     * @param array  $post
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
                              ->withPageSize($elementsPerPage);

        $maxPage = $pagination->getNumberOfPages() - 1;
        if ($currentPage >= $maxPage) {
            $currentPage = $maxPage;
        }
        if ($currentPage <= 0) {
            $currentPage = 0;
        }

        $pagination = $pagination->withCurrentPage($currentPage);

        $start = $pagination->getPageSize() * $currentPage;
        $stop = $pagination->getPageSize();

        if ($totalNumberOfElements === 0) {
            $pageLength = 0;
        } else {
            if ($this->plugin->isAtLeastIlias7()) {
                $range = $pagination->getRange();
                $pageLength = $range->getLength();
            } else {
                $pageLength = $pagination->getPageLength();
            }
        }

        $html = '<div class="tmsq-pagination">' .
            $renderer->render($pagination)
            . '<hr class="tmsq-pagination-separator">'
            . sprintf(
                $this->plugin->txt("answersFromTo"),
                $totalNumberOfElements === 0 ? 0 : $start + 1,
                $start + $pageLength
            )
            . '</div>';

        return [
            "html" => $html,
            "start" => $start,
            "currentPage" => $currentPage,
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
            //alternative as array_key_first() is not available in php 7.2
            reset($passOptions);
            $selectPassInput->setValue((string) key($passOptions));
        }

        if (!in_array((int) $selectQuestionInput->getValue(), array_keys($questionOptions))) {
            //alternative as array_key_first() is not available in php 7.2
            reset($questionOptions);
            $selectQuestionInput->setValue((string) key($questionOptions));
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
     * @throws ReflectionException
     */
    protected function drawHeader(int $refId) : void
    {
        $objTestGui = new ilObjTestGUI($refId);

        $reflectionMethod = new ReflectionMethod(ilObjTestGUI::class, 'setTitleAndDescription');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($objTestGui);

        $this->dic['ilLocator']->addRepositoryItems($refId);
        $this->dic["ilLocator"]->addItem(
            $objTestGui->getObject()->getTitle(),
            $this->getManualScoringByQuestionTarget($refId)
        );
        $this->mainTpl->setLocator();
    }

    /**
     * Gets the answer detail html string to be displayed in the form
     * @param ilTestEvaluationUserData $participant
     * @param ilObjTest                $test
     * @param int                      $activeId
     * @param int                      $pass
     * @param int                      $questionId
     * @param ilTestAccess             $testAccess
     * @return string
     * @throws ilTemplateException
     */
    protected function getAnswerDetail(
        ilTestEvaluationUserData $participant,
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

        $tmp_tpl = new ilTemplate('tpl.il_as_tst_correct_solution_output.html', true, true, 'Modules/Test');

        if (
            method_exists($question_gui, "supportsIntermediateSolutionOutput") &&
            method_exists($question_gui, "hasIntermediateSolution") &&
            method_exists($question_gui, "setUseIntermediateSolution") &&
            $question_gui->supportsIntermediateSolutionOutput() &&
            $question_gui->hasIntermediateSolution($activeId, $pass)) {
            $question_gui->setUseIntermediateSolution(true);
            $aresult_output = $question_gui->getSolutionOutput(
                $activeId,
                $pass,
                false,
                false,
                true,
                false,
                false,
                true
            );
            $question_gui->setUseIntermediateSolution(false);

            $tmp_tpl->setVariable('TEXT_ASOLUTION_OUTPUT', $this->lng->txt('autosavecontent'));
            $tmp_tpl->setVariable('ASOLUTION_OUTPUT', $aresult_output);
        }

        $result_output = $question_gui->getSolutionOutput(
            $activeId,
            $pass,
            false,
            false,
            true,
            $test->getShowSolutionFeedback(),
            false,
            true
        );

        $tmp_tpl->setVariable(
            'TEXT_YOUR_SOLUTION',
            $this->lng->txt('answers_of') . ' ' . $participant->getName()
        );

        $tmp_tpl->setVariable(
            'TEXT_SOLUTION_OUTPUT',
            $this->lng->txt('answers_of') . ' ' . $participant->getName()
        );

        $tmp_tpl->setVariable('SOLUTION_OUTPUT', $result_output);

        return $tmp_tpl->get();
    }

    /**
     * Returns the target link to the scoring by question tab
     * @param int $refId
     * @return string
     */
    protected function getManualScoringByQuestionTarget(int $refId) : string
    {
        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", (int) $refId);
        return $this->ctrl->getLinkTargetByClass(
            [ilObjTestGUI::class, ilTestScoringByQuestionsGUI::class],
            "showManScoringByQuestionParticipantsTable"
        );
    }

    /**
     * Redirects the user to the tmsq manual scoring page
     * @param int|string $refId
     */
    protected function redirectToManualScoringTab($refId, int $pageNumber = -1)
    {
        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", (int) $refId);

        if ($pageNumber >= 0) {
            $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "page", $pageNumber);
        }

        $this->ctrl->redirectByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "showTmsqManualScoring"
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
