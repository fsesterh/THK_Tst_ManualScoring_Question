<?php /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

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

}
