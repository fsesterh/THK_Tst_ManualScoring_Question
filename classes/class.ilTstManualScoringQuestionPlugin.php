<?php

/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;

/**
 * Class ilTstManualScoringQuestionPlugin
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
    /**
     * @var Container
     */
    protected $dic;
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        parent::__construct($db, $component_repository, $id);
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();

        parent::__construct($db, $component_repository, $id);
    }

    /**
     * @var ilTstManualScoringQuestionPlugin|null
     */
    private static $instance = null;

    /**
     * @inheritdoc
     */
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

    /**
     * @return ilTstManualScoringQuestionPlugin
     */
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
        if ($this->isAtLeastIlias6()) {
            $this->ctrl->redirectByClass("ilDashboardGUI", "show");
        } else {
            $this->ctrl->redirectByClass("ilPersonalDesktopGUI");
        }
    }

    public function isAtLeastIlias6(): bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "6.0.0", ">=");
    }

    /**
     * Checks if the current ilias version is at least ilias 7
     * @return bool
     */
    public function isAtLeastIlias7(): bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "7.0", ">=");
    }
}
