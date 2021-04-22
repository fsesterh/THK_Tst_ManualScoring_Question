<?php declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\HTTPServices;
use TstManualScoringQuestion\Form\ConfigForm;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class ilTstManualScoringQuestionConfigGUI
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ilTstManualScoringQuestionConfigGUI extends ilPluginConfigGUI
{
    /**
     * @var ilTstManualScoringQuestionConfigGUI
     */
    protected $plugin;
    /**
     * @var ilTemplate
     */
    protected $tpl;
    /**
     * @var HTTPServices
     */
    protected $http;
    /**
     * @var ilCtrl
     */
    protected $ctrl;
    /**
     * @var ilSetting
     */
    protected $settings;

    public function __construct()
    {
        global $DIC;
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->http = $DIC->http();
        $this->ctrl = $DIC->ctrl();
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
    }

    public function showSettings()
    {
        $form = new ConfigForm();
        $form->bind();
        $this->tpl->setContent($form->getHTML());
    }

    public function saveSettings() : void
    {
        $form = new ConfigForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            $form->handleSubmit();
            ilUtil::sendSuccess($this->plugin->txt("config_saved"), true);
        }

        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Calls the function for a received command
     * @param $cmd
     */
    public function performCommand($cmd)
    {
        switch (true) {
            case method_exists($this, $cmd):
                $this->{$cmd}();
                break;
            default:
                $this->{$this->getDefaultCommand()}();
        }
    }

    /**
     * Returns the default command
     * @return string
     */
    protected function getDefaultCommand() : string
    {
        return "showSettings";
    }
}
