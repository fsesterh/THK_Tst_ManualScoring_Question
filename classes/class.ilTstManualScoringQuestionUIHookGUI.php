<?php /** @noinspection PhpMissingParamTypeInspection */
declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use Psr\Http\Message\RequestInterface;
use ILIAS\Plugin\TstManualScoringQuestion\TstManualScoringQuestion;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class ilTstManualScoringQuestionUIHookGUI
 * @author            Marvin Beym <mbeym@databay.de>
 * @ilCtrl_isCalledBy ilTstManualScoringQuestionUIHookGUI: ilUIPluginRouterGUI
 */
class ilTstManualScoringQuestionUIHookGUI extends ilUIHookPluginGUI
{
    private const TMSQ_TAB = "tmsq_man_scoring";
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
     * Injects the sub tab for scoring by tmsq
     * @param int $ref_id
     */
    protected function injectSubTab(int $ref_id)
    {
        $this->dic->ctrl()->setParameterByClass(
            ilTstManualScoringQuestionUIHookGUI::class,
            'ref_id',
            $ref_id
        );

        $this->dic->tabs()->addSubTab(
            self::TMSQ_TAB,
            $this->plugin->txt("tmsq_scoring"),
            $this->dic->ctrl()->getLinkTargetByClass(
                [ilUIPluginRouterGUI::class, self::class],
                "showTmsqManualScoring"
            )
        );
    }

    public function modifyGUI($a_comp, $a_part, $a_par = array())
    {
        parent::modifyGUI($a_comp, $a_part, $a_par);
        $query = $this->request->getQueryParams();
        if ($a_part !== "sub_tabs") {
            return;
        }

        if ($this->dic->tabs()->getActiveTab() !== "manscoring") {
            return;
        }
        $this->injectSubTab((int) $query["ref_id"]);
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
        $cmd = $ctrl->getCmd();
        if (!isset($cmd)) {
            ilUtil::sendFailure($this->plugin->txt("missing_get_parameter_cmd"), true);
            $ctrl->redirectToURL("ilias.php");
        }

        if ($user->isAnonymous()) {
            $ctrl->redirectToURL('login.php');
        }

        (new TstManualScoringQuestion($this->dic))->performCommand($cmd, $query);
    }
}
