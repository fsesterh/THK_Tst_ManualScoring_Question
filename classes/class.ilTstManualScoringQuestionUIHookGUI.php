<?php /** @noinspection PhpMissingParamTypeInspection */
declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class ilTstManualScoringQuestionUIHookGUI
 * @author            Marvin Beym <mbeym@databay.de>
 * @ilCtrl_isCalledBy ilTstManualScoringQuestionUIHookGUI: ilUIPluginRouterGUI
 */
class ilTstManualScoringQuestionUIHookGUI extends ilUIHookPluginGUI
{
    private ilTstManualScoringQuestionPlugin $plugin;
    public Container $dic;

    /**
     * ilTstManualScoringQuestionUIHookGUI constructor.
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
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

        if (!$html || !$tplId) {
            return $this->uiHookResponse();
        }

        return $this->uiHookResponse();
    }

    /**
     * Returns the array used to replace the html content
     * @param string $mode
     * @param string $html
     * @return string[]
     */
    protected function uiHookResponse(string $mode = ilUIHookPluginGUI::KEEP, string $html = "") : array
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
            ilUtil::sendFailure($this->plugin->txt("cmdNotFound"), true);
            $ctrl->redirectByClass(ilDashboardGUI::class, "show");
        }

        if ($user->isAnonymous()) {
            $ctrl->redirectToURL('login.php');
        }

        if (method_exists($this, $cmd)) {
            $this->$cmd($query);
        }

    }
}
