<?php /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
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
    const CTYPE = "Services";
    /** @var string */
    const CNAME = "UIComponent";
    /** @var string */
    const SLOT_ID = "uihk";
    /** @var string */
    const PNAME = "TstManualScoringQuestion";
    /**
     * @var Container
     */
    protected $dic;
    /**
     * @var ilCtrl
     */
    protected $ctrl;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();

        parent::__construct();
    }

    /**
     * @var ilTstManualScoringQuestionPlugin|null
     */
    private static $instance = null;

    /**
     * @inheritdoc
     */
    public function getPluginName() : string
    {
        return self::PNAME;
    }

    public function assetsFolder() : string
    {
        return $this->getDirectory() . "/assets/";
    }

    public function cssFolder(string $file = "") : string
    {
        return $this->assetsFolder() . "/css/{$file}";
    }

    public function templatesFolder(string $file = "") : string
    {
        return $this->assetsFolder() . "/templates/{$file}";
    }

    public function jsFolder(string $file = "") : string
    {
        return $this->assetsFolder() . "/js/{$file}";
    }

    /**
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @return ilTstManualScoringQuestionPlugin
     */
    public static function getInstance() : ilTstManualScoringQuestionPlugin
    {
        if (null === self::$instance) {
            return self::$instance = ilPluginAdmin::getPluginObject(
                self::CTYPE,
                self::CNAME,
                self::SLOT_ID,
                self::PNAME
            );
        }

        return self::$instance;
    }

    public function redirectToHome()
    {
        if ($this->isIlias6()) {
            $this->ctrl->redirectByClass("ilDashboardGUI", "show");
        } else {
            $this->ctrl->redirectByClass("ilPersonalDesktopGUI");
        }
    }

    public function isIlias6() : bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "6.0.0", ">");
    }
}
