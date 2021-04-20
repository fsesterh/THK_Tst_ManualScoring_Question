<?php /** @noinspection PhpMissingParamTypeInspection */
declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use TstManualScoringQuestion\TstManualScoringQuestion;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class ilTstManualScoringQuestionUIHookGUI
 * @author            Marvin Beym <mbeym@databay.de>
 * @ilCtrl_isCalledBy ilTstManualScoringQuestionUIHookGUI: ilUIPluginRouterGUI
 */
class ilTstManualScoringQuestionUIHookGUI extends ilUIHookPluginGUI
{
    /**
     * @var ilLanguage
     */
    protected $lng;
    /**
     * @var TstManualScoringQuestion
     */
    protected $tstManualScoringQuestion;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ilTstManualScoringQuestionPlugin
     */
    protected $plugin;
    /**
     * @var Container
     */
    protected $dic;

    /**
     * ilTstManualScoringQuestionUIHookGUI constructor.
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->lng = $this->dic->language();
        $this->lng->loadLanguageModule("assessment");
        $this->request = $this->dic->http()->request();
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array  $a_par
     * @return array|string[]
     * @throws Exception
     */
    public function getHTML($a_comp, $a_part, $a_par = array()) : array
    {
        $html = $a_par["html"];
        $tplId = $a_par["tpl_id"];

        if (!$html || $tplId !== "Services/Table/tpl.table2.html" || $a_part !== "template_get" || !str_contains($html, $this->lng->txt("tst_man_scoring_by_qst"))) {
            return $this->uiHookResponse();
        }

        $query = $this->request->getQueryParams();
        if (($query["cmd"] !== "post" && $query["fallbackCmd"] !== "showManScoringByQuestionParticipantsTable") &&
        $query["cmd"] !== "showManScoringByQuestionParticipantsTable") {
            return $this->uiHookResponse();
        }

        $this->tstManualScoringQuestion = new TstManualScoringQuestion($this->dic);

        return $this->uiHookResponse(self::REPLACE, $this->tstManualScoringQuestion->modify($html, (int) $query["ref_id"]));
    }

    /**
     * Returns the array used to replace the html content
     * @param string $mode
     * @param string $html
     * @return string[]
     */
    protected function uiHookResponse(string $mode = self::KEEP, string $html = "") : array
    {
        return ['mode' => $mode, 'html' => $html];
    }

    /**
     * Checks if the received command can be executed and redirects the command into the structure presentation class
     * for further processing
     * @throws Exception
     */
    public function executeCommand()
    {
        $request = $this->dic->http()->request();
        $user = $this->dic->user();
        $ctrl = $this->dic->ctrl();
        $query = $request->getQueryParams();
        $cmd = $query["cmd"];
        if (!isset($cmd)) {
            ilUtil::sendFailure($this->plugin->txt("missing_get_parameter_cmd"), true);
            $ctrl->redirectByClass(ilDashboardGUI::class, "show");
        }

        if ($user->isAnonymous()) {
            $ctrl->redirectToURL('login.php');
        }

        (new TstManualScoringQuestion($this->dic))->performCommand($cmd, $query);
    }
}
