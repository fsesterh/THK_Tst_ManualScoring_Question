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
use ILIAS\Plugin\TstManualScoringQuestion\TstManualScoringQuestion;
use ILIAS\Plugin\TstManualScoringQuestion\Utils\UiUtil;
use Psr\Http\Message\RequestInterface;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @ilCtrl_isCalledBy ilTstManualScoringQuestionUIHookGUI: ilUIPluginRouterGUI
 */
class ilTstManualScoringQuestionUIHookGUI extends ilUIHookPluginGUI
{
    private const TMSQ_TAB = "tmsq_man_scoring";
    protected ilLanguage $lng;
    protected TstManualScoringQuestion $tstManualScoringQuestion;
    protected RequestInterface $request;
    protected ilTstManualScoringQuestionPlugin $plugin;
    protected Container $dic;
    private UiUtil $uiUtil;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->lng = $this->dic->language();
        $this->lng->loadLanguageModule("assessment");
        $this->request = $this->dic->http()->request();
        $this->uiUtil = new UiUtil($this->dic);
    }

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

    public function modifyGUI(string $a_comp, string $a_part, array $a_par = []): void
    {
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
     * @return string[]
     */
    protected function uiHookResponse(string $mode = self::KEEP, string $html = ""): array
    {
        return ['mode' => $mode, 'html' => $html];
    }

    /**
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
            $this->uiUtil->sendFailure($this->plugin->txt("missing_get_parameter_cmd"), true);
            $ctrl->redirectToURL("ilias.php");
        }

        if ($user->isAnonymous()) {
            $ctrl->redirectToURL('login.php');
        }

        (new TstManualScoringQuestion($this->dic))->performCommand($cmd, $query);
    }
}
