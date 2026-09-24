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

use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\TstManualScoringQuestion\TstManualScoringQuestion;
use ILIAS\Plugin\TstManualScoringQuestion\Utils\UiUtil;
use ILIAS\Refinery\Factory;

/**
 * @ilCtrl_isCalledBy ilTstManualScoringQuestionUIHookGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilTstManualScoringQuestionUIHookGUI: ilAssSpecFeedbackPageGUI
 */
class ilTstManualScoringQuestionUIHookGUI extends ilUIHookPluginGUI
{
    private const string TMSQ_TAB = "tmsq_man_scoring";

    protected ilLanguage $lng;
    protected ilTstManualScoringQuestionPlugin $plugin;
    protected Container $dic;
    private readonly UiUtil $ui_util;
    private readonly WrapperFactory $http_wrapper;
    private readonly Factory $refinery;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->lng = $this->dic->language();
        $this->lng->loadLanguageModule("assessment");
        $this->http_wrapper = $this->dic->http()->wrapper();
        $this->refinery = $this->dic->refinery();
        $this->ui_util = new UiUtil($this->dic);
    }

    /**
     * @return string[]
     */
    protected function uiHookResponse(string $mode = self::KEEP, string $html = ""): array
    {
        return ["mode" => $mode, "html" => $html];
    }

    /**
     * @throws Exception
     */
    public function executeCommand(): void
    {
        $user = $this->dic->user();
        $ctrl = $this->dic->ctrl();
        $cmd = $ctrl->getCmd();
        if (!isset($cmd)) {
            $this->ui_util->sendFailure(sprintf($this->plugin->txt("general.plugin.requiredParameterMissing"), "cmd"), true);
            $ctrl->redirectToURL("ilias.php");
        }

        if ($user->isAnonymous()) {
            $ctrl->redirectToURL("login.php");
        }

        (new TstManualScoringQuestion($this->dic))->performCommand($cmd);
    }
}
