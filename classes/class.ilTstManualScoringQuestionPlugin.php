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
use ILIAS\Plugin\TstManualScoringQuestion\Enum\PluginAsset;
use ILIAS\Plugin\TstManualScoringQuestion\Utils\UiUtil;
use ILIAS\Test\Presentation\TestScreenGUI;

class ilTstManualScoringQuestionPlugin extends ilUserInterfaceHookPlugin
{
    public const ID = "tmsq";

    protected Container $dic;

    private static ilTstManualScoringQuestionPlugin|ilPlugin|null $instance = null;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        parent::__construct($db, $component_repository, $id);
        global $DIC;
        $this->dic = $DIC;

        parent::__construct($db, $component_repository, $id);
    }

    public function getRelativeDirectory(): string
    {
        return str_replace(ILIAS_ABSOLUTE_PATH . "/public/", "", realpath($this->getDirectory()));
    }

    public function assetsFile(PluginAsset $assetType, string $file, bool $relative = true): string
    {
        $basePath = $relative ? $this->getRelativeDirectory() : $this->getDirectory();
        return $basePath . "/assets/" . $assetType->value . "/" . $file;
    }

    public static function getInstance(): ilTstManualScoringQuestionPlugin
    {
        global $DIC;

        if (self::$instance instanceof self) {
            return self::$instance;
        }

        /**
         * @var ilComponentFactory $componentFactory
         */
        $componentFactory = $DIC["component.factory"];

        self::$instance = $componentFactory->getPlugin(self::ID);
        return self::$instance;
    }


    public function accessViolationRedirect(): never
    {
        $uiUtil = new UiUtil($this->dic);
        $uiUtil->sendFailure(
            $this->dic->language()->txt("no_permission"),
            true
        );
        $this->dic->ctrl()->redirectByClass(
            [ilObjTestGUI::class, TestScreenGUI::class],
            TestScreenGUI::DEFAULT_CMD
        );
        exit;
    }

    public function redirectToHome(): void
    {
        $this->dic->ctrl()->redirectByClass(ilDashboardGUI::class, "show");
    }
}
