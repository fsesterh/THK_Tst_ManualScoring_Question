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

namespace ILIAS\Plugin\TstManualScoringQuestion\Form\Input\HtmlAreaInput;

use ilFormPropertyGUI;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;

class HtmlAreaInput extends ilFormPropertyGUI
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
     * @throws ilTemplateException|ilSystemStyleException
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

    protected function getFolderPath(): string
    {
        return strstr(realpath(__DIR__), "Customizing") . "/";
    }

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

    public function setDisabled(bool $a_disabled): void
    {
        $this->disabled = $a_disabled;
        if ($a_disabled) {
            $this->editable = false;
        }
    }
}
