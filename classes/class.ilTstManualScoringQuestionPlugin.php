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

/**
 * Class ilTstManualScoringQuestionPlugin
 *
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ilTstManualScoringQuestionPlugin extends ilUserInterfaceHookPlugin
{
    /** @var string */
    public const CTYPE = "Services";
    /** @var string */
    public const CNAME = "UIComponent";
    /** @var string */
    public const SLOT_ID = "uihk";
    /** @var string */
    public const PNAME = "TstManualScoringQuestion";
    protected Container $dic;
    protected ilCtrl $ctrl;

    private static ?ilTstManualScoringQuestionPlugin $instance = null;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        parent::__construct($db, $component_repository, $id);
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();

        parent::__construct($db, $component_repository, $id);
    }

    public function getPluginName(): string
    {
        return self::PNAME;
    }

    public function assetsFolder(): string
    {
        return $this->getDirectory() . "/assets/";
    }

    public function cssFolder(string $file = ""): string
    {
        return $this->assetsFolder() . "/css/{$file}";
    }

    public function templatesFolder(string $file = ""): string
    {
        return $this->assetsFolder() . "/templates/{$file}";
    }

    public function jsFolder(string $file = ""): string
    {
        return $this->assetsFolder() . "/js/{$file}";
    }

    public static function getInstance(): ilTstManualScoringQuestionPlugin
    {
        global $DIC;

        if (self::$instance instanceof self) {
            return self::$instance;
        }

        /** @var ilComponentRepository $component_repository */
        $component_repository = $DIC['component.repository'];
        /** @var ilComponentFactory $component_factory */
        $component_factory = $DIC['component.factory'];

        $plugin_info = $component_repository->getComponentByTypeAndName(
            self::CTYPE,
            self::CNAME
        )->getPluginSlotById(self::SLOT_ID)->getPluginByName(self::PNAME);

        self::$instance = $component_factory->getPlugin($plugin_info->getId());

        return self::$instance;
    }

    public function redirectToHome()
    {
        $this->ctrl->redirectByClass("ilDashboardGUI", "show");
    }

    public function isAtLeastIlias6(): bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "6.0.0", ">=");
    }

    /**
     * Checks if the current ilias version is at least ilias 7
     *
     */
    public function isAtLeastIlias7(): bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "7.0", ">=");
    }
}
