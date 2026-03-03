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

namespace ILIAS\Plugin\TstManualScoringQuestion;

use assQuestion;
use Exception;
use ilAccessHandler;
use ilCtrlException;
use ilCtrlInterface;
use ilDBConstants;
use ilGlobalTemplateInterface;
use ILIAS\DI\Container;
use ILIAS\DI\UIServices;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\TstManualScoringQuestion\Enum\PluginAsset;
use ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput\HtmlAreaInput;
use ILIAS\Plugin\TstManualScoringQuestion\Form\TstManualScoringForm;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Answer;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Question;
use ILIAS\Plugin\TstManualScoringQuestion\Utils\UiUtil;
use ILIAS\Test\Scoring\Manual\TestScoringByQuestionGUI;
use ILIAS\UI\Component\Input\Container\Filter\Standard;
use ILIAS\UI\Component\Input\Field\Select;
use ILIAS\UI\Factory;
use ILIAS\UI\Implementation\Component\Input\Field\FormInput;
use ILIAS\UI\Renderer;
use ilLanguage;
use ilLogger;
use ilObjTest;
use ilObjTestGUI;
use ilObjUser;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use ilTestAccess;
use ilTestEvaluationUserData;
use ilTestParticipantAccessFilterFactory;
use ilTestParticipantData;
use ilToolbarGUI;
use ilTstManualScoringQuestionPlugin;
use ilTstManualScoringQuestionUIHookGUI;
use ilUIFilterService;
use ilUIFilterServiceSessionGateway;
use ilUIPluginRouterGUI;
use Psr\Http\Message\RequestInterface;
use ReflectionException;
use ReflectionMethod;

class TstManualScoringQuestion
{
    public const ALL_USERS = 0;
    public const ONLY_FINALIZED = 1;
    public const EXCEPT_FINALIZED = 2;
    protected array $answersAndForms = [];
    protected ilLogger $logger;
    protected ilObjUser $user;
    protected ilAccessHandler $access;
    protected RequestInterface $request;
    protected ilToolbarGUI $toolbar;
    protected UIServices $ui;
    protected ilCtrlInterface $ctrl;
    protected ilGlobalTemplateInterface $mainTpl;
    protected ilTstManualScoringQuestionPlugin $plugin;
    protected ilLanguage $lng;
    protected Container $dic;
    private UiUtil $uiUtil;
    protected Renderer $uiRenderer;
    protected ilUIFilterService $uiFilterService;
    protected \ILIAS\UI\Component\Input\Field\Factory $uiFieldFactory;
    private Factory $uiFactory;
    private WrapperFactory $httpWrapper;
    private \ILIAS\Refinery\Factory $refinery;

    public function __construct(Container $dic = null)
    {
        if ($dic === null) {
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
        $this->request = $this->dic->http()->request();
        $this->httpWrapper = $this->dic->http()->wrapper();
        $this->refinery = $this->dic->refinery();
        $this->access = $dic->access();
        $this->user = $dic->user();
        $this->logger = $dic->logger()->root();
        $this->uiUtil = new UiUtil($this->dic);

        $this->uiFactory = $this->dic->ui()->factory();
        $this->uiRenderer = $this->dic->ui()->renderer();
        $this->uiFilterService = $this->dic->uiService()->filter();
        $this->uiFieldFactory = $this->uiFactory->input()->field();
    }

    protected function readScoringCompleted(int $questionId, int $activeId, int $pass): bool
    {
        $result = $this->dic->database()->queryF(
            "SELECT finalized_evaluation FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
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

    /**
     * @return list<array{
     *     active_id: int,
     *     reached_points: float,
     *     participant: ilTestEvaluationUserData,
     *     lastname: string,
     *     firstname: string,
     *     login: string
     * }>
     */
    protected function getAnswerData(ilObjTest $test, int $pass, int $questionId): array
    {
        $answersData = [];
        $data = $test->getCompleteEvaluationData();
        $participants = $data->getParticipants();

        $participantData = new ilTestParticipantData($this->dic->database(), $this->lng);
        $participantData->setActiveIdsFilter(array_keys($data->getParticipants()));

        $participantAccessFilter = new ilTestParticipantAccessFilterFactory($this->dic->access());
        $participantData->setParticipantAccessFilter(
            $participantAccessFilter->getScoreParticipantsUserFilter($test->getRefId())
        );

        $participantData->load($test->getTestId());

        foreach ($participantData->getActiveIds() as $active_id) {
            /** @var $participant ilTestEvaluationUserData */
            $participant = $participants[$active_id];

            $testResultData = $test->getTestResult($active_id, $pass);
            foreach ($testResultData as $key => $questionData) {
                if (!isset($questionData["qid"]) || (int) $questionData["qid"] !== $questionId) {
                    continue;
                }

                $user = ilObjUser::_getUserData([$participant->getUserID()]);
                $answersData[] = [
                    "active_id" => $active_id,
                    "reached_points" => assQuestion::_getReachedPoints($active_id, $questionId, $pass),
                    "participant" => $participant,
                    "lastname" => $user[0]["lastname"],
                    "firstname" => $user[0]["firstname"],
                    "login" => $participant->getLogin(),
                ];
            }
        }
        return $answersData;
    }

    /**
     * @return array<int, string>
     */
    protected function generateQuestionOptions(ilObjTest $test): array
    {
        $questionOptions = [];
        if (!$test->isRandomTest()) {
            $questions = $test->getTestQuestions();
        } else {
            $questions = $test->getPotentialRandomTestQuestions();
        }

        foreach ($questions as $questionData) {
            $questionId = $questionData["question_id"];
            $title = $questionData["title"];
            $points = $questionData["points"];
            $questionOptions[$questionId] = $title . " ($points {$this->lng->txt("points")}) [ID: $questionId]";
        }
        return $questionOptions;
    }

    /**
     * @return array<int, string>
     */
    protected function generatePassOptions(ilObjTest $test): array
    {
        $passOptions = [];
        for ($i = 0; $i < $test->getMaxPassOfTest(); $i++) {
            $passOptions[$i] = (string) ($i + 1);
        }
        return $passOptions;
    }

    /**
     * @throws Exception
     */
    public function performCommand(string $cmd): void
    {
        $refId = $this->httpWrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$refId) {
            $this->uiUtil->sendFailure($this->plugin->txt("missing_get_parameter_refId"), true);
            $this->plugin->redirectToHome();
        }

        switch (true) {
            case method_exists($this, $cmd):
                $this->$cmd($this->request->getParsedBody());
                break;

            default:
                $this->uiUtil->sendFailure($this->plugin->txt("cmdNotSupported"), true);
                $this->redirectToManualScoringTab($refId);
        }
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     * @throws ilCtrlException
     */
    public function modify(int $refId): string
    {
        $test = new ilObjTest($refId, true);
        $testAccess = new ilTestAccess($test->getRefId());

        if (!$testAccess->checkScoreParticipantsAccess()) {
            $this->plugin->accessViolationRedirect();
        }

        $this->mainTpl->addCss($this->plugin->assetsFile(PluginAsset::CSS, "tstManualScoringQuestion.css"));
        $tpl = new ilTemplate(
            $this->plugin->assetsFile(PluginAsset::TEMPLATES, "tpl.manualScoringQuestionPanel.html", false),
            true,
            true
        );

        $questionOptions = $this->generateQuestionOptions($test);

        $passOptions = $this->generatePassOptions($test);
        if ($questionOptions === [] || $passOptions === []) {
            return $this->showNoEntries($test, $tpl);
        }

        $filter = $this->setupFilter($test->getRefId(), $questionOptions, $passOptions);

        $filterData = $this->uiFilterService->getData($filter) ?? [
            "question" => array_key_first($questionOptions),
            "pass" => array_key_first($passOptions),
            "scoringCompleted" => self::ALL_USERS,
            "answersPerPage" => 10
        ];

        $selectedQuestionId = (int) ($filterData["question"] !== "" ? $filterData["question"] : array_key_first($questionOptions));
        $selectedPass = (int) ($filterData["pass"] !== "" ? $filterData["pass"] : array_key_first($passOptions));
        $selectedScoringCompleted = (int) ($filterData["scoringCompleted"] !== "" ? $filterData["scoringCompleted"] : self::ALL_USERS);
        $selectedAnswersPerPage = (int) ($filterData["answersPerPage"] !== "" ? $filterData["answersPerPage"] : 10);


        $question = new Question(
            $selectedQuestionId,
            $test->getRefId(),
            $selectedPass
        );

        //Pagination
        /**
         * @var list<array{
         *      active_id: int,
         *      reached_points: float,
         *      participant: ilTestEvaluationUserData,
         *      lastname: string,
         *      firstname: string,
         *      login: string
         *  }> $answersData
         */
        $answersData = $this->getAnswerData($test, $selectedPass, $selectedQuestionId);

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

        $numberOfAnswersData = count($answersData);
        $paginationData = $this->setupPagination($selectedAnswersPerPage, $numberOfAnswersData);
        $currentPage = $paginationData["currentPage"];
        $tpl->setVariable("PAGINATION_HTML", $paginationData["html"]);

        /**
         * @var array{
         *      active_id: int,
         *      reached_points: float,
         *      participant: ilTestEvaluationUserData,
         *      lastname: string,
         *      firstname: string,
         *      login: string
         *  } $answerData
         */

        foreach (array_slice($answersData, $paginationData["start"], $paginationData["stop"]) as $answerData) {
            $activeId = (int) $answerData["active_id"];
            $answer = new Answer(
                $question,
                $activeId,
                null,
                (float) $answerData["reached_points"],
                null,
                $answerData["login"],
                $answerData["participant"]->getName(),
                $this->getAnswerDetail(
                    $answerData["participant"],
                    $test,
                    $activeId,
                    $selectedPass,
                    $selectedQuestionId,
                    $testAccess
                )
            );

            $question->addAnswer($answer);
            $this->logger->debug("TMSQ : Added answer of activeId {$answer->getActiveId()} for questionId {$question->getId()}");
        }

        $this->logger->debug("TMSQ : Answers array sliced by pagination. Number of answers before $numberOfAnswersData now " . count($question->getAnswers()));

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
            $tpl->setVariable("SUBMIT_CMD", "saveManualScoring");

            $this->ctrl->setParameterByClass(
                ilTstManualScoringQuestionUIHookGUI::class,
                "ref_id",
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
                                if ($item instanceof HtmlAreaInput) {
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
                            $test->getAnonymity() ? $this->lng->txt("anonymous") : $correctAnswer->getLogin()
                        )
                    );

                    $formHtml = $form->getHTML();
                    $formHtml = preg_replace('/<form.*"novalidate">/ms', "", $formHtml);
                    $formHtml = preg_replace('/<\/form>/ms', "", $formHtml);

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
                            $test->getAnonymity() ? $this->lng->txt("anonymous") : $answer->getLogin()
                        )
                    );

                    $formHtml = $form->getHTML();
                    $formHtml = preg_replace('/<form.*"novalidate">/ms', "", $formHtml);
                    $formHtml = preg_replace('/<\/form>/ms', "", $formHtml);

                    $tpl->setVariable("ANSWER_FORM", $formHtml);
                    $tpl->parseCurrentBlock("answer");
                }
            }

            $tpl->parseCurrentBlock("question");
        } else {
            $this->logger->debug("TMSQ : no answers available, show no entries message");
            return $this->showNoEntries($test, $tpl);
        }

        return $this->uiRenderer->render($filter) . $tpl->get();
    }

    /**
     * @throws ilCtrlException
     * @throws ilTemplateException
     */
    protected function showNoEntries(ilObjTest $test, ilTemplate $tpl): string
    {
        $tpl->setVariable("NO_ENTRIES", $this->plugin->txt("noEntries"));
        $filter = $this->setupFilter(
            $test->getRefId(),
            $this->generateQuestionOptions($test),
            $this->generatePassOptions($test)
        );
        return $this->uiRenderer->render($filter) . $tpl->get();
    }

    /**
     * @throws ilTemplateException
     * @throws ReflectionException
     * @throws ilCtrlException|ilSystemStyleException
     */
    protected function showTmsqManualScoring(): void
    {
        $refId = $this->httpWrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $this->drawHeader($refId);
        $this->dic->tabs()->setBackTarget(
            $this->lng->txt("back"),
            $this->getManualScoringByQuestionTarget($refId)
        );

        $this->mainTpl->loadStandardTemplate();

        $this->mainTpl->setContent($this->modify($refId));

        $this->dic->ui()->mainTemplate()->printToStdOut();
    }

    /**
     * @throws Exception
     */
    protected function saveManualScoring(): void
    {
        $page = $this->httpWrapper->query()->retrieve(
            "page",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $currentPage = $page ?? -1;

        $tmsq = $this->httpWrapper->post()->retrieve(
            "tmsq",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->listOf(
                    $this->refinery->kindlyTo()->recordOf([
                        "answers" => $this->refinery->byTrying([
                            //When checking scoringCompleted
                            $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->recordOf([
                                "points" => $this->refinery->kindlyTo()->float(),
                                "feedback" => $this->refinery->kindlyTo()->string(),
                                "scoringCompleted" => $this->refinery->kindlyTo()->bool(),
                                "activeId" => $this->refinery->kindlyTo()->int()
                            ])),
                            $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->recordOf([
                                "points" => $this->refinery->kindlyTo()->float(),
                                "feedback" => $this->refinery->kindlyTo()->string(),
                                "activeId" => $this->refinery->kindlyTo()->int()
                            ])),
                            //When unchecking scoring completed checkbox
                            $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->recordOf([
                                "activeId" => $this->refinery->kindlyTo()->int()
                            ]))
                        ]),
                        "testRefId" => $this->refinery->kindlyTo()->int(),
                        "pass" => $this->refinery->kindlyTo()->int(),
                        "questionId" => $this->refinery->kindlyTo()->int()
                    ])
                ),
                $this->refinery->always(null)
            ])
        );

        if (!$tmsq) {
            $this->uiUtil->sendFailure($this->plugin->txt("invalid_post_data"), true);
            $this->plugin->redirectToHome();
        }

        /**
         * @var Question[] $questions
         */
        $questions = [];

        /**
         * @var array{
         *     testRefId: int,
         *     pass: int,
         *     questionId: int,
         *     answers: list<array{
         *         points: float,
         *         feedpack: string,
         *         scoringCompleted: bool,
         *         activeId: int
         *     }
         * } $questionData
         */
        foreach ($tmsq as $questionData) {
            $question = new Question(
                (int) $questionData["questionId"],
                (int) $questionData["testRefId"],
                (int) $questionData["pass"]
            );

            $answersData = $questionData["answers"];

            if (isset($answersData) && is_array($answersData)) {
                foreach ($answersData as $answerData) {
                    $answer = new Answer(
                        $question,
                        (int) $answerData["activeId"],
                        (bool) ($answerData["scoringCompleted"] ?? false),
                        isset($answerData["points"]) && is_numeric($answerData["points"])
                            ? (float) $answerData["points"]
                            : null,
                        isset($answerData["feedback"]) && is_string($answerData["feedback"])
                            ? $answerData["feedback"]
                            : null
                    );

                    $question->addAnswer($answer);
                }
            }

            $questions[] = $question;
        }

        $testRefId = -1;

        foreach ($questions as $question) {
            $testRefId = $question->getTestRefId();
            $test = new ilObjTest($testRefId, true);
            $testAccess = new ilTestAccess($test->getRefId());

            if (!$testRefId) {
                $this->uiUtil->sendFailure($this->plugin->txt("unknownError"), true);
                $this->plugin->redirectToHome();
            }

            if (!$testAccess->checkScoreParticipantsAccess()) {
                $this->plugin->accessViolationRedirect();
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
                $this->uiUtil->sendFailure($this->lng->txt("form_input_not_valid"), true);
                $this->showTmsqManualScoring();
                return;
            }

            foreach ($question->getAnswers() as $answer) {
                $scoringCompleted = $answer->isScoringCompleted();

                if (!$scoringCompleted && $answer->getPoints() > $question->getMaximumPoints()) {
                    $this->sendInvalidForm($question->getTestRefId());
                }

                if (!$scoringCompleted && !$answer->writePoints()) {
                    $this->uiUtil->sendFailure($this->plugin->txt("saving_points_failed"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId(), $currentPage);
                }

                if (!$answer->writeFeedback()) {
                    $this->uiUtil->sendFailure($this->plugin->txt("saving_feedback_failed"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId(), $currentPage);
                }
            }
        }

        if ($testRefId === -1) {
            $this->uiUtil->sendFailure($this->plugin->txt("unknownError"), true);
            $this->plugin->redirectToHome();
        } else {
            $this->uiUtil->sendSuccess($this->plugin->txt("saving_manualScoring"), true);
            $this->redirectToManualScoringTab($testRefId, $currentPage);
        }
    }

    protected function setupPagination(int $elementsPerPage, int $totalNumberOfElements): array
    {
        $factory = $this->dic->ui()->factory();
        $renderer = $this->dic->ui()->renderer();
        $url = $this->request->getRequestTarget();

        $parameterName = "page";

        $page = $this->httpWrapper->query()->retrieve(
            "page",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $currentPage = $page ?? 0;

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
            $range = $pagination->getRange();
            $pageLength = $range->getLength();
        }

        $html = "<div class='tmsq-pagination'>" .
            $renderer->render($pagination)
            . "<hr class='tmsq-pagination-separator'>"
            . sprintf(
                $this->plugin->txt("answersFromTo"),
                $totalNumberOfElements === 0 ? 0 : $start + 1,
                $start + $pageLength
            )
            . "</div>";

        return [
            "html" => $html,
            "start" => $start,
            "currentPage" => $currentPage,
            "stop" => $stop
        ];
    }

    /**
     * @throws ilCtrlException
     */
    protected function setupFilter(int $testRefId, array $questionOptions, array $passOptions): Standard
    {
        $answersPerPageOptions = range(1, 10);
        $answersPerPageOptions = array_combine($answersPerPageOptions, $answersPerPageOptions);

        $selectQuestionInput = $this->uiFieldFactory->select($this->lng->txt("question"), $questionOptions);
        $selectPassInput = $this->uiFieldFactory->select($this->lng->txt("pass"), $passOptions);
        $selectAnswersPerPageInput = $this->uiFieldFactory->select(
            $this->plugin->txt("answersPerPage"),
            $answersPerPageOptions
        );

        $scoringCompletedOptions = [
            self::ALL_USERS => $this->lng->txt("all_users"),
            self::ONLY_FINALIZED => $this->lng->txt("evaluated_users"),
            self::EXCEPT_FINALIZED => $this->lng->txt("not_evaluated_users"),
        ];
        $selectScoringCompletedInput = $this->uiFieldFactory->select(
            $this->lng->txt("finalized_evaluation"),
            $scoringCompletedOptions
        );

        if (
            $selectQuestionInput->getValue() === []
            || !in_array((int) $selectQuestionInput->getValue(), array_keys($questionOptions), true)
        ) {
            $selectQuestionInput = $selectQuestionInput->withValue(array_key_first($questionOptions));
        }

        if ($selectPassInput->getValue() === null || !in_array(
            (int) $selectPassInput->getValue(),
            array_keys($passOptions),
            true
        )) {
            $selectPassInput = $selectPassInput->withValue(array_key_first($passOptions));
        }

        if (
            $selectAnswersPerPageInput->getValue() === null
            || !in_array((int) $selectAnswersPerPageInput->getValue(), $answersPerPageOptions, true)
        ) {
            $selectAnswersPerPageInput = $selectAnswersPerPageInput->withValue(10);
        }

        if (
            $selectScoringCompletedInput->getValue() === null
            || !in_array(
                (int) $selectScoringCompletedInput->getValue(),
                array_keys($scoringCompletedOptions),
                true
            )
        ) {
            $selectScoringCompletedInput->withValue(self::ALL_USERS);
        }


        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", $testRefId);
        $filterBaseAction = $this->ctrl->getLinkTargetByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "showTmsqManualScoring",
        );

        $filterInputs = [
            "question" => $selectQuestionInput,
            "pass" => $selectPassInput,
            "answersPerPage" => $selectAnswersPerPageInput,
            "scoringCompleted" => $selectScoringCompletedInput
        ];


        $this->fixIlias8FilterOptionError($filterInputs);

        return $this->uiFilterService->standard(
            "tstFilter",
            $filterBaseAction,
            $filterInputs,
            [
                true,
                true,
                true,
                true
            ],
        );
    }

    /**
     * @throws ReflectionException
     * @throws ilCtrlException
     */
    protected function drawHeader(int $refId): void
    {
        $objTestGui = new ilObjTestGUI();

        $reflectionMethod = new ReflectionMethod(ilObjTestGUI::class, "setTitleAndDescription");
        $reflectionMethod->invoke($objTestGui);

        $this->dic["ilLocator"]->addRepositoryItems($refId);
        $this->dic["ilLocator"]->addItem(
            $objTestGui->getObject()->getTitle(),
            $this->getManualScoringByQuestionTarget($refId)
        );
        $this->mainTpl->setLocator();
    }

    /**
     * @throws ilTemplateException
     */
    protected function getAnswerDetail(
        ilTestEvaluationUserData $participant,
        ilObjTest $test,
        int $activeId,
        int $pass,
        int $questionId,
        ilTestAccess $testAccess
    ): string {
        if (!$testAccess->checkScoreParticipantsAccessForActiveId($activeId, $test->getTestId())) {
            $this->plugin->accessViolationRedirect();
        }

        $question_gui = $test->createQuestionGUI("", $questionId);

        if (!$question_gui) {
            return "";
        }

        $tmp_tpl = new ilTemplate("tpl.il_as_tst_correct_solution_output.html", true, true, "components/ILIAS/Test");

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

            $tmp_tpl->setVariable("TEXT_ASOLUTION_OUTPUT", $this->lng->txt("autosavecontent"));
            $tmp_tpl->setVariable("ASOLUTION_OUTPUT", $aresult_output);
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
            "TEXT_YOUR_SOLUTION",
            $this->lng->txt("answers_of") . " " . $participant->getName()
        );

        $tmp_tpl->setVariable(
            "TEXT_SOLUTION_OUTPUT",
            $this->lng->txt("answers_of") . " " . $participant->getName()
        );

        $tmp_tpl->setVariable("TEXT_RECEIVED_POINTS", $this->lng->txt("scoring"));

        $tmp_tpl->setVariable("SOLUTION_OUTPUT", $result_output);

        $tmp_tpl->setVariable(
            "RECEIVED_POINTS",
            sprintf(
                $this->lng->txt("part_received_a_of_b_points"),
                $question_gui->getObject()->getReachedPoints($activeId, $pass),
                $question_gui->getObject()->getMaximumPoints()
            )
        );

        $tmp_tpl->setVariable("SOLUTION_OUTPUT", $result_output);

        return $tmp_tpl->get();
    }

    /**
     * @throws ilCtrlException
     */
    protected function getManualScoringByQuestionTarget(int $refId): string
    {
        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", (int) $refId);
        return $this->ctrl->getLinkTargetByClass(
            [ilObjTestGUI::class, TestScoringByQuestionGUI::class],
            "showManScoringByQuestionParticipantsTable"
        );
    }

    /**
     * @param int|string $refId
     * @throws ilCtrlException
     */
    protected function redirectToManualScoringTab(int $refId, int $pageNumber = -1): void
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
     * @throws ilCtrlException
     */
    protected function sendInvalidForm(int $refId): void
    {
        $this->uiUtil->sendFailure($this->lng->txt("form_input_not_valid"), true);
        $this->redirectToManualScoringTab($refId);
    }

    /**
     * Fixes an issue in ilias causing an exception when a filter option is no longer available but still stored in
     * session https://mantis.ilias.de/view.php?id=37741
     *
     * @param FormInput[] $filterInputs
     */
    private function fixIlias8FilterOptionError(array $filterInputs): void
    {
        $filterServiceSessionGateway = new ilUIFilterServiceSessionGateway();

        foreach ($filterInputs as $inputId => $input) {
            if (!$input instanceof Select) {
                continue;
            }
            $value = $filterServiceSessionGateway->getValue("tstFilter", $inputId);
            $optionFound = false;

            if ($value !== null) {
                foreach ($input->getOptions() as $key => $optionValue) {
                    $key = (string) $key;
                    if ($value === $key) {
                        $optionFound = true;
                        break;
                    }
                }
            }

            if (!$optionFound) {
                $filterServiceSessionGateway->writeValue("tstFilter", $inputId, $input->getValue());
            }
        }
    }
}
