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
use ILIAS\Plugin\TstManualScoringQuestion\Enum\ScoringCompletedOptions;
use ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput\HtmlAreaInput;
use ILIAS\Plugin\TstManualScoringQuestion\Form\TstManualScoringForm;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Answer;
use ILIAS\Plugin\TstManualScoringQuestion\Model\Question;
use ILIAS\Plugin\TstManualScoringQuestion\Utils\UiUtil;
use ILIAS\Test\Scoring\Manual\ConsecutiveScoringGUI;
use ILIAS\UI\Component\Input\Container\Filter\Standard;
use ILIAS\UI\Factory;
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
use ilUIPluginRouterGUI;
use Psr\Http\Message\RequestInterface;
use ReflectionException;
use ReflectionMethod;

class TstManualScoringQuestion
{
    protected array $answers_and_forms = [];
    protected ilLogger $logger;
    protected ilObjUser $user;
    protected ilAccessHandler $access;
    protected RequestInterface $request;
    protected ilToolbarGUI $toolbar;
    protected UIServices $ui;
    protected ilCtrlInterface $ctrl;
    protected ilGlobalTemplateInterface $main_tpl;
    protected ilTstManualScoringQuestionPlugin $plugin;
    protected ilLanguage $lng;
    protected Container $dic;
    private readonly UiUtil $ui_util;
    protected Renderer $ui_renderer;
    protected ilUIFilterService $ui_filter_service;
    protected \ILIAS\UI\Component\Input\Field\Factory $ui_field_factory;
    private readonly Factory $ui_factory;
    private readonly WrapperFactory $http_wrapper;
    private readonly \ILIAS\Refinery\Factory $refinery;

    public function __construct(?Container $dic = null)
    {
        global $DIC;
        $this->dic = $dic ?? $DIC;

        $this->main_tpl = $this->dic->ui()->mainTemplate();
        $this->toolbar = $this->dic->toolbar();
        $this->lng = $this->dic->language();
        $this->lng->loadLanguageModule("assessment");
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->ui = $this->dic->ui();
        $this->ctrl = $this->dic->ctrl();
        $this->request = $this->dic->http()->request();
        $this->http_wrapper = $this->dic->http()->wrapper();
        $this->refinery = $this->dic->refinery();
        $this->access = $this->dic->access();
        $this->user = $this->dic->user();
        $this->logger = $this->dic->logger()->root();
        $this->ui_util = new UiUtil($this->dic);

        $this->ui_factory = $this->dic->ui()->factory();
        $this->ui_renderer = $this->dic->ui()->renderer();
        $this->ui_filter_service = $this->dic->uiService()->filter();
        $this->ui_field_factory = $this->ui_factory->input()->field();
    }

    protected function readScoringCompleted(int $question_id, int $active_id, int $pass): bool
    {
        $result = $this->dic->database()->queryF(
            "SELECT finalized_evaluation FROM tst_manual_fb WHERE active_fi = %s AND question_fi = %s AND pass = %s",
            [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER],
            [$active_id, $question_id, $pass]
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
    protected function getAnswerData(ilObjTest $test, int $pass, int $question_id): array
    {
        $answers_data = [];
        $data = $test->getCompleteEvaluationData();
        $participants = $data->getParticipants();

        $participant_data = new ilTestParticipantData($this->dic->database(), $this->lng);
        $participant_data->setActiveIdsFilter(array_keys($data->getParticipants()));

        $participant_access_filter = new ilTestParticipantAccessFilterFactory($this->dic->access());
        $participant_data->setParticipantAccessFilter(
            $participant_access_filter->getScoreParticipantsUserFilter($test->getRefId())
        );

        $participant_data->load($test->getTestId());

        foreach ($participant_data->getActiveIds() as $active_id) {
            /** @var $participant ilTestEvaluationUserData */
            $participant = $participants[$active_id];

            $test_result_data = $test->getTestResult($active_id, $pass);
            foreach ($test_result_data as $key => $question_data) {
                if (!isset($question_data["qid"]) || (int) $question_data["qid"] !== $question_id) {
                    continue;
                }

                $user = ilObjUser::_getUserData([$participant->getUserID()]);
                $answers_data[] = [
                    "active_id" => $active_id,
                    "reached_points" => assQuestion::_getReachedPoints($active_id, $question_id, $pass),
                    "participant" => $participant,
                    "lastname" => $user[0]["lastname"],
                    "firstname" => $user[0]["firstname"],
                    "login" => $participant->getLogin(),
                ];
            }
        }
        return $answers_data;
    }

    /**
     * @return array<int, string>
     */
    protected function generateQuestionOptions(ilObjTest $test): array
    {
        $question_options = [];
        if (!$test->isRandomTest()) {
            $questions = $test->getTestQuestions();
        } else {
            $questions = $test->getPotentialRandomTestQuestions();
        }

        foreach ($questions as $question_data) {
            $question_id = $question_data["question_id"];
            $title = $question_data["title"];
            $points = $question_data["points"];
            $question_options[$question_id] = $title . " ($points {$this->lng->txt("points")}) [ID: $question_id]";
        }
        return $question_options;
    }

    /**
     * @return array<int, string>
     */
    protected function generatePassOptions(ilObjTest $test): array
    {
        $pass_options = [];
        for ($i = 0; $i < $test->getMaxPassOfTest(); $i++) {
            $pass_options[$i] = (string) ($i + 1);
        }
        return $pass_options;
    }

    /**
     * @throws Exception
     */
    public function performCommand(string $cmd): void
    {
        $ref_id = $this->http_wrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$ref_id) {
            $this->ui_util->sendFailure(sprintf($this->plugin->txt("general.plugin.requiredParameterMissing#"), "ref_id"), true);
            $this->plugin->redirectToHome();
        }

        switch (true) {
            case method_exists($this, $cmd):
                $this->$cmd($this->request->getParsedBody());
                break;

            default:
                $this->ui_util->sendFailure(sprintf($this->plugin->txt("general.cmd.notFound"), $cmd), true);
                $this->redirectToManualScoringTab($ref_id);
        }
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     * @throws ilCtrlException
     */
    public function modify(int $ref_id): string
    {
        $test = new ilObjTest($ref_id, true);
        $test_access = new ilTestAccess($test->getRefId());

        if (!$test_access->checkScoreParticipantsAccess()) {
            $this->plugin->accessViolationRedirect();
        }

        $this->main_tpl->addCss($this->plugin->assetsFile(PluginAsset::CSS, "tstManualScoringQuestion.css"));
        $tpl = new ilTemplate(
            $this->plugin->assetsFile(PluginAsset::TEMPLATES, "tpl.manualScoringQuestionPanel.html", false),
            true,
            true
        );

        $question_options = $this->generateQuestionOptions($test);

        $pass_options = $this->generatePassOptions($test);
        if ($question_options === [] || $pass_options === []) {
            return $this->showNoEntries($test, $tpl);
        }

        $filter = $this->setupFilter($test->getRefId(), $question_options, $pass_options);

        $filter_data = $this->ui_filter_service->getData($filter) ?? [
            "question" => array_key_first($question_options),
            "pass" => array_key_first($pass_options),
            "scoring_completed" => ScoringCompletedOptions::ALL_USERS->value,
            "answers_per_page" => 10
        ];

        $selected_question_id = (int) ($filter_data["question"] !== "" ? $filter_data["question"] : array_key_first($question_options));
        $selected_pass = (int) ($filter_data["pass"] !== "" ? $filter_data["pass"] : array_key_first($pass_options));
        $selected_scoring_completed = ScoringCompletedOptions::tryFrom((int) $filter_data["scoring_completed"]) ?? ScoringCompletedOptions::ALL_USERS;
        $selected_answers_per_page = (int) ($filter_data["answers_per_page"] !== "" ? $filter_data["answers_per_page"] : 10);

        $question = new Question(
            $selected_question_id,
            $test->getRefId(),
            $selected_pass
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
         *  }> $answers_data
         */
        $answers_data = $this->getAnswerData($test, $selected_pass, $selected_question_id);

        $answers_data = array_filter(
            $answers_data,
            function (array $answer_data) use ($question, $selected_scoring_completed) {
                $scoring_completed = $this->readScoringCompleted(
                    $question->getId(),
                    (int) $answer_data["active_id"],
                    $question->getPass()
                );
                return match ($selected_scoring_completed) {
                    ScoringCompletedOptions::ONLY_FINALIZED => $scoring_completed,
                    ScoringCompletedOptions::EXCEPT_FINALIZED => !$scoring_completed,
                    default => true,
                };
            }
        );

        $number_of_answers_data = count($answers_data);
        $pagination_data = $this->setupPagination($selected_answers_per_page, $number_of_answers_data);
        $current_page = $pagination_data["current_page"];
        $tpl->setVariable("PAGINATION_HTML", $pagination_data["html"]);

        /**
         * @var array{
         *      active_id: int,
         *      reached_points: float,
         *      participant: ilTestEvaluationUserData,
         *      lastname: string,
         *      firstname: string,
         *      login: string
         *  } $answer_data
         */

        foreach (array_slice($answers_data, $pagination_data["start"], $pagination_data["stop"]) as $answer_data) {
            $active_id = (int) $answer_data["active_id"];
            $answer = new Answer(
                $question,
                $active_id,
                null,
                (float) $answer_data["reached_points"],
                null,
                $answer_data["login"],
                $answer_data["participant"]->getName(),
                $this->getAnswerDetail(
                    $answer_data["participant"],
                    $test,
                    $active_id,
                    $selected_pass,
                    $selected_question_id,
                    $test_access
                )
            );

            $question->addAnswer($answer);
            $this->logger->debug("TMSQ : Added answer of activeId {$answer->getActiveId()} for questionId {$question->getId()}");
        }

        $this->logger->debug("TMSQ : Answers array sliced by pagination. Number of answers before $number_of_answers_data now " . count($question->getAnswers()));

        if (count($question->getAnswers()) > 0) {
            $tpl->setCurrentBlock("question");
            $tpl->setVariable(
                "PANEL_HEADER_TEXT",
                sprintf(
                    $this->lng->txt("manscoring_results_pass"),
                    ($selected_pass + 1)
                ) . " | " . sprintf(
                    $this->lng->txt("tst_manscoring_question_section_header"),
                    $question_options[$selected_question_id]
                )
            );

            $tpl->setVariable("SUBMIT_BUTTON_TEXT", $this->lng->txt("save"));
            $tpl->setVariable("SUBMIT_CMD", "saveManualScoring");

            $this->ctrl->setParameterByClass(
                ilTstManualScoringQuestionUIHookGUI::class,
                "ref_id",
                $test->getRefId()
            );

            $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "page", $current_page);
            $tpl->setVariable(
                "FORM_ACTION",
                $this->ctrl->getFormActionByClass(
                    [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
                    "saveManualScoring"
                )
            );

            if (count($this->answers_and_forms) > 0) {
                foreach ($this->answers_and_forms as $answer_and_form) {
                    $correct_answer = null;
                    $form = null;
                    foreach ($question->getAnswers() as $answer) {
                        if ($answer->getActiveId() === $answer_and_form["answer"]->getActiveId()) {
                            $correct_answer = $answer;
                            $form = $answer_and_form["form"];
                            foreach ($form->getItems() as $item) {
                                if ($item instanceof HtmlAreaInput) {
                                    $item->setValue($correct_answer->getAnswerHtml());
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
                            $this->plugin->txt("manual_scoring.answer_of"),
                            $correct_answer->getUserName(),
                            $test->getAnonymity() ? $this->lng->txt("anonymous") : $correct_answer->getLogin()
                        )
                    );

                    $form_html = $form->getHTML();
                    $form_html = preg_replace('/<form.*"novalidate">/ms', "", (string) $form_html);
                    $form_html = preg_replace('/<\/form>/ms', "", $form_html);

                    $tpl->setVariable("ANSWER_FORM", $form_html);
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

                    $form_html = $form->getHTML();
                    $form_html = preg_replace('/<form.*"novalidate">/ms', "", $form_html);
                    $form_html = preg_replace('/<\/form>/ms', "", $form_html);

                    $tpl->setVariable("ANSWER_FORM", $form_html);
                    $tpl->parseCurrentBlock("answer");
                }
            }

            $tpl->parseCurrentBlock("question");
        } else {
            $this->logger->debug("TMSQ : no answers available, show no entries message");
            return $this->showNoEntries($test, $tpl);
        }

        return $this->ui_renderer->render($filter) . $tpl->get();
    }

    /**
     * @throws ilCtrlException
     * @throws ilTemplateException
     */
    protected function showNoEntries(ilObjTest $test, ilTemplate $tpl): string
    {
        $tpl->setVariable("NO_ENTRIES", $this->plugin->txt("manual_scoring.no_entries"));
        $filter = $this->setupFilter(
            $test->getRefId(),
            $this->generateQuestionOptions($test),
            $this->generatePassOptions($test)
        );
        return $this->ui_renderer->render($filter) . $tpl->get();
    }

    /**
     * @throws ilTemplateException
     * @throws ReflectionException
     * @throws ilCtrlException|ilSystemStyleException
     */
    protected function showTmsqManualScoring(): void
    {
        $ref_id = $this->http_wrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $this->drawHeader($ref_id);
        $this->dic->tabs()->setBackTarget(
            $this->lng->txt("back"),
            $this->getManualScoringByQuestionTarget($ref_id)
        );

        $this->main_tpl->loadStandardTemplate();

        $this->main_tpl->setContent($this->modify($ref_id));

        $this->dic->ui()->mainTemplate()->printToStdOut();
    }

    /**
     * @throws Exception
     */
    protected function saveManualScoring(): void
    {
        $page = $this->http_wrapper->query()->retrieve(
            "page",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $ref_id = $this->http_wrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $current_page = $page ?? -1;

        $tmsq = $this->http_wrapper->post()->retrieve(
            "tmsq",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->listOf(
                    $this->refinery->kindlyTo()->recordOf([
                        "answers" => $this->refinery->kindlyTo()->listOf(
                            $this->refinery->custom()->transformation(function (array $answer_data): array {
                                foreach (["points", "feedback", "scoring_completed", "active_id"] as $key) {
                                    if (isset($answer_data[$key])) {
                                        $value = $answer_data[$key];
                                        switch ($key) {
                                            case "points":
                                                $value = (float) $value;
                                                break;
                                            case "scoring_completed":
                                                $value = (bool) $value;
                                                break;
                                            case "active_id":
                                                $value = (int) $value;
                                                break;
                                        }

                                        $answer_data[$key] = $value;
                                    }
                                }
                                return $answer_data;
                            })
                        ),
                        "pass" => $this->refinery->kindlyTo()->int(),
                        "question_id" => $this->refinery->kindlyTo()->int()
                    ])
                ),
                $this->refinery->always(null)
            ])
        );

        if (!$tmsq || !$ref_id) {
            $this->ui_util->sendFailure($this->plugin->txt("invalid_post_data"), true);
            $this->plugin->redirectToHome();
        }

        $test = new ilObjTest($ref_id, true);
        $test_access = new ilTestAccess($test->getRefId());

        /**
         * @var Question[] $questions
         */
        $questions = [];

        $active_ids = $test->getActiveParticipantList()->getAllActiveIds();

        /**
         * @var array{
         *     pass: int,
         *     question_id: int,
         *     answers: list<array{
         *         points: float,
         *         feedpack: string,
         *         scoring_completed: bool,
         *         active_id: int
         *     }
         * } $question_data
         */
        foreach ($tmsq as $question_data) {
            $question = new Question(
                (int) $question_data["question_id"],
                $ref_id,
                (int) $question_data["pass"]
            );

            $answers_data = $question_data["answers"];

            if (isset($answers_data) && is_array($answers_data)) {
                foreach ($answers_data as $answer_data) {
                    $active_id = (int) $answer_data["active_id"];
                    if (!in_array($active_id, $active_ids, true)) {
                        continue;
                    }

                    $answer = new Answer(
                        $question,
                        $active_id,
                        (bool) ($answer_data["scoring_completed"] ?? false),
                        isset($answer_data["points"]) && is_numeric($answer_data["points"])
                            ? (float) $answer_data["points"]
                            : null,
                        isset($answer_data["feedback"]) && is_string($answer_data["feedback"])
                            ? $answer_data["feedback"]
                            : null
                    );

                    $question->addAnswer($answer);
                }
            }

            $questions[] = $question;
        }

        foreach ($questions as $question) {
            if (!$test_access->checkScoreParticipantsAccess()) {
                $this->plugin->accessViolationRedirect();
            }

            //Check all answer forms
            $forms_valid = true;
            $this->answers_and_forms = [];
            foreach ($question->getAnswers() as $answer) {
                $form = new TstManualScoringForm($this->lng, $answer);
                $form->fillForm($answer);
                if (!$form->checkInput()) {
                    $forms_valid = false;
                }
                $this->answers_and_forms[] = ["answer" => $answer, "form" => $form];
            }

            if (!$forms_valid) {
                $this->ui_util->sendFailure($this->lng->txt("form_input_not_valid"), true);
                $this->showTmsqManualScoring();
                return;
            }

            foreach ($question->getAnswers() as $answer) {
                $scoring_completed = $answer->readScoringCompleted();

                if (!$scoring_completed && $answer->getPoints() > $question->getMaximumPoints()) {
                    $this->sendInvalidForm($question->getTestRefId());
                }

                if (!$scoring_completed && !$answer->writePoints()) {
                    $this->ui_util->sendFailure($this->plugin->txt("manual_scoring.save.failure"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId(), $current_page);
                }

                if (!$answer->writeFeedback()) {
                    $this->ui_util->sendFailure($this->plugin->txt("manual_scoring.save.feedback.failure"), true);
                    $this->redirectToManualScoringTab($question->getTestRefId(), $current_page);
                }
            }
        }

        $this->ui_util->sendSuccess($this->plugin->txt("manual_scoring.save.success"), true);
        $this->redirectToManualScoringTab($ref_id, $current_page);
    }

    protected function setupPagination(int $elements_per_page, int $total_number_of_elements): array
    {
        $factory = $this->dic->ui()->factory();
        $renderer = $this->dic->ui()->renderer();
        $url = $this->request->getRequestTarget();

        $parameter_name = "page";

        $page = $this->http_wrapper->query()->retrieve(
            "page",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        $current_page = $page ?? 0;

        $pagination = $factory->viewControl()->pagination()
            ->withTargetURL($url, $parameter_name)
            ->withTotalEntries($total_number_of_elements)
            ->withPageSize($elements_per_page);

        $max_page = $pagination->getNumberOfPages() - 1;
        if ($current_page >= $max_page) {
            $current_page = $max_page;
        }
        if ($current_page <= 0) {
            $current_page = 0;
        }

        $pagination = $pagination->withCurrentPage($current_page);

        $start = $pagination->getPageSize() * $current_page;
        $stop = $pagination->getPageSize();

        if ($total_number_of_elements === 0) {
            $page_length = 0;
        } else {
            $range = $pagination->getRange();
            $page_length = $range?->getLength() ?? 5;
        }

        $html = "<div class='tmsq-pagination'>" .
            $renderer->render($pagination)
            . "<hr class='tmsq-pagination-separator'>"
            . sprintf(
                $this->plugin->txt("manual_scoring.answers_from_to"),
                $total_number_of_elements === 0 ? 0 : $start + 1,
                $start + $page_length
            )
            . "</div>";

        return [
            "html" => $html,
            "start" => $start,
            "current_page" => $current_page,
            "stop" => $stop
        ];
    }

    /**
     * @throws ilCtrlException
     */
    protected function setupFilter(int $test_ref_id, array $question_options, array $pass_options): Standard
    {
        $answers_per_page_options = range(1, 10);
        $answers_per_page_options = array_combine($answers_per_page_options, $answers_per_page_options);

        $select_question_input = $this->ui_field_factory->select($this->lng->txt("question"), $question_options);
        $select_pass_input = $this->ui_field_factory->select($this->lng->txt("pass"), $pass_options);
        $select_answers_per_page_input = $this->ui_field_factory->select(
            $this->plugin->txt("manual_scoring.filter.answers_per_page"),
            $answers_per_page_options
        );

        $scoring_completed_options = [];
        foreach (ScoringCompletedOptions::cases() as $case) {
            $scoring_completed_options[$case->value] = $this->lng->txt($case->getTranslationKey());
        }
        $select_scoring_completed_input = $this->ui_field_factory->select(
            $this->lng->txt("finalized_evaluation"),
            $scoring_completed_options
        );

        if (
            $select_question_input->getValue() === []
            || !in_array((int) $select_question_input->getValue(), array_keys($question_options), true)
        ) {
            $select_question_input = $select_question_input->withValue(array_key_first($question_options));
        }

        if ($select_pass_input->getValue() === null || !in_array(
            (int) $select_pass_input->getValue(),
            array_keys($pass_options),
            true
        )) {
            $select_pass_input = $select_pass_input->withValue(array_key_first($pass_options));
        }

        if (
            $select_answers_per_page_input->getValue() === null
            || !in_array((int) $select_answers_per_page_input->getValue(), $answers_per_page_options, true)
        ) {
            $select_answers_per_page_input = $select_answers_per_page_input->withValue(10);
        }

        if (
            $select_scoring_completed_input->getValue() === null
            || !in_array(
                (int) $select_scoring_completed_input->getValue(),
                array_keys($scoring_completed_options),
                true
            )
        ) {
            $select_scoring_completed_input->withValue(ScoringCompletedOptions::ALL_USERS->value);
        }

        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", $test_ref_id);
        $filter_base_action = $this->ctrl->getLinkTargetByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "showTmsqManualScoring",
        );

        $filter_inputs = [
            "question" => $select_question_input,
            "pass" => $select_pass_input,
            "answers_per_page" => $select_answers_per_page_input,
            "scoring_completed" => $select_scoring_completed_input
        ];

        return $this->ui_filter_service->standard(
            "tstFilter",
            $filter_base_action,
            $filter_inputs,
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
    protected function drawHeader(int $ref_id): void
    {
        $obj_test_gui = new ilObjTestGUI();

        $reflection_method = new ReflectionMethod(ilObjTestGUI::class, "setTitleAndDescription");
        $reflection_method->invoke($obj_test_gui);

        $this->dic["ilLocator"]->addRepositoryItems($ref_id);
        $this->dic["ilLocator"]->addItem(
            $obj_test_gui->getObject()?->getTitle(),
            $this->getManualScoringByQuestionTarget($ref_id)
        );
        $this->main_tpl->setLocator();
    }

    /**
     * @throws ilTemplateException
     */
    protected function getAnswerDetail(
        ilTestEvaluationUserData $participant,
        ilObjTest $test,
        int $active_id,
        int $pass,
        int $question_id,
        ilTestAccess $test_access
    ): string {
        if (!$test_access->checkScoreParticipantsAccessForActiveId($active_id, $test->getTestId())) {
            $this->plugin->accessViolationRedirect();
        }

        $question_gui = $test->createQuestionGUI("", $question_id);

        if (!$question_gui) {
            return "";
        }

        $tmp_tpl = new ilTemplate("tpl.il_as_tst_correct_solution_output.html", true, true, "components/ILIAS/Test");

        if (
            method_exists($question_gui, "supportsIntermediateSolutionOutput") &&
            method_exists($question_gui, "hasIntermediateSolution") &&
            method_exists($question_gui, "setUseIntermediateSolution") &&
            $question_gui->supportsIntermediateSolutionOutput() &&
            $question_gui->hasIntermediateSolution($active_id, $pass)) {
            $question_gui->setUseIntermediateSolution(true);
            $aresult_output = $question_gui->getSolutionOutput(
                $active_id,
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
            $active_id,
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
                $question_gui->getObject()->getReachedPoints($active_id, $pass),
                $question_gui->getObject()->getMaximumPoints()
            )
        );

        $tmp_tpl->setVariable("SOLUTION_OUTPUT", $result_output);

        return $tmp_tpl->get();
    }

    /**
     * @throws ilCtrlException
     */
    protected function getManualScoringByQuestionTarget(int $ref_id): string
    {
        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", (int) $ref_id);
        return $this->ctrl->getLinkTargetByClass([ilObjTestGUI::class, ConsecutiveScoringGUI::class]);
    }

    /**
     * @param int|string $ref_id
     * @throws ilCtrlException
     */
    protected function redirectToManualScoringTab(int $ref_id, int $page_number = -1): void
    {
        $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", (int) $ref_id);

        if ($page_number >= 0) {
            $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "page", $page_number);
        }

        $this->ctrl->redirectByClass(
            [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
            "showTmsqManualScoring"
        );
    }

    /**
     * @throws ilCtrlException
     */
    protected function sendInvalidForm(int $ref_id): void
    {
        $this->ui_util->sendFailure($this->lng->txt("form_input_not_valid"), true);
        $this->redirectToManualScoringTab($ref_id);
    }
}
