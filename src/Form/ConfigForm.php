<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Form;

use ilPropertyFormGUI;
use ilSetting;
use TstManualScoringQuestion\TstManualScoringQuestion;
use ilTstManualScoringQuestionPlugin;
use ilTstManualScoringQuestionConfigGUI;
use ilNumberInputGUI;

/**
 * Class ConfigForm
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ConfigForm extends ilPropertyFormGUI
{
    /**
     * @var ilSetting
     */
    protected $setting;

    /**
     * @var TstManualScoringQuestion
     */
    protected $plugin;

    /**
     * ConfigForm constructor.
     */
    public function __construct()
    {
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        parent::__construct();

        $this->setTitle($this->plugin->txt("tstManualScoringQuestion_config"));

        $this->setting = new ilSetting(ilTstManualScoringQuestionPlugin::class);

        $answersPerPageInput = new ilNumberInputGUI($this->plugin->txt("answersPerPage"), "answersPerPage");
        $answersPerPageInput->setMinValue(1);
        $answersPerPageInput->setRequired(true);

        $this->addItem($answersPerPageInput);

        $this->setFormAction($this->ctrl->getFormActionByClass(
            ilTstManualScoringQuestionConfigGUI::class,
            "showSettings"
        ));
        $this->addCommandButton("saveSettings", $this->plugin->txt("save"));
    }

    /**
     * Binds the values from the settings object to the config form fields
     */
    public function bind()
    {
        $binds = [
            "answersPerPage" => $this->setting->get("answersPerPage", 10),
        ];

        $this->setValuesByArray($binds, true);
    }

    /**
     * Handles the form submit
     */
    public function handleSubmit()
    {
        /**
         * @var ilNumberInputGUI $answersPerPageInput
         */
        $answersPerPageInput = $this->getItemByPostVar("answersPerPage");
        $this->setting->set("answersPerPage", $answersPerPageInput->getValue());
    }
}
