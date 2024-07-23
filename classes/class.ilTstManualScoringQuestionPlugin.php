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

class ilTstManualScoringQuestionPlugin extends ilUserInterfaceHookPlugin
{
    public const ID = "tmsq";

    protected Container $dic;
    protected ilCtrlInterface $ctrl;

    private static ilTstManualScoringQuestionPlugin|ilPlugin|null $instance = null;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        parent::__construct($db, $component_repository, $id);
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();

        parent::__construct($db, $component_repository, $id);
    }

    public function assetsFolder(): string
    {
        return $this->getDirectory() . "/assets/";
    }

    public function cssFolder(string $file = ""): string
    {
        return $this->assetsFolder() . "/css/$file";
    }

    public function templatesFolder(string $file = ""): string
    {
        return $this->assetsFolder() . "/templates/$file";
    }

    public function jsFolder(string $file = ""): string
    {
        return $this->assetsFolder() . "/js/$file";
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

    public function redirectToHome(): void
    {
        $this->ctrl->redirectByClass("ilDashboardGUI", "show");
    }
}
