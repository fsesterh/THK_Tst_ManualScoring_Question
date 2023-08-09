<?php

declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput;

use ilFormPropertyGUI;
use ilTemplate;
use ilTemplateException;

/**
 * Class ilHtmlAreaInput
 *
 * @package TstManualScoringQuestion\Form\Input\HtmlAreaInput
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ilHtmlAreaInput extends ilFormPropertyGUI
{
    protected bool $disabled = false;
    protected bool $editable = true;
    protected string $htmlClass = "html-area-input";
    protected string $value = "";

    public function checkInput(): bool
    {
        if ($this->required) {
            if (!empty($this->value)) {
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * @param string[] $post
     */
    public function setValueByArray(array $post): void
    {
        $value = $post[$this->getPostVar()];
        $this->setValue($value ?: "");
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Inserts the input into the template.
     *
     * @param $a_tpl
     * @return void
     * @throws ilTemplateException|\ilSystemStyleException
     */
    public function insert($a_tpl): void
    {
        $tpl = new ilTemplate($this->getFolderPath() . "tpl.htmlAreaInput.html", true, true);
        $tpl->setVariable("TEXT", $this->value);
        $tpl->setVariable("POST_VAR", $this->getPostVar());
        $tpl->setVariable("HTML_CLASS", $this->htmlClass);
        $tpl->setVariable("EDITABLE", $this->editable ? "true" : "false");
        $tpl->setVariable("DISABLED", $this->disabled ? "cursor: not-allowed; background-color: #eeeeee;" : "");

        $a_tpl->setCurrentBlock('prop_generic');
        $a_tpl->setVariable('PROP_GENERIC', $tpl->get());
        $a_tpl->parseCurrentBlock();
    }

    /**
     * Returns the path to the folder where the input is located.
     *
     * @return string
     */
    protected function getFolderPath(): string
    {
        return strstr(realpath(__DIR__), "Customizing") . "/";
    }

    /**
     * Changes the html class of the div
     *
     * @param string $htmlClass
     */
    public function setHtmlClass(string $htmlClass): void
    {
        $this->htmlClass = $htmlClass;
    }

    public function setEditable(bool $editable): void
    {
        if (!$this->disabled) {
            $this->editable = $editable;
        }
    }

    /**
     * @param bool $a_disabled
     */
    public function setDisabled(bool $a_disabled): void
    {
        $this->disabled = $a_disabled;
        if ($a_disabled) {
            $this->editable = false;
        }
    }
}
