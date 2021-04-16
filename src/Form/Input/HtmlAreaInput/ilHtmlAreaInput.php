<?php declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace TstManualScoringQuestion\Form\Input\HtmlAreaInput;

use ilFormPropertyGUI;
use ilTemplateException;
use ilTemplate;
use ilGlobalPageTemplate;

/**
 * Class ilHtmlAreaInput
 * @package TstManualScoringQuestion\Form\Input\HtmlAreaInput
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ilHtmlAreaInput extends ilFormPropertyGUI
{
    /**
     * @var bool
     */
    private $editable = true;
    /**
     * @var string
     */
    protected $htmlClass = "html-area-input";
    /**
     * @var string
     */
    protected $value = "";
    /**
     * @var ilGlobalPageTemplate
     */
    protected $mainTpl;

    /**
     * ilRichTextInput constructor.
     * @param string $a_title
     * @param string $a_postvar
     */
    public function __construct($a_title = "", $a_postvar = "")
    {
        global $DIC;
        $this->mainTpl = $DIC->ui()->mainTemplate();
        parent::__construct($a_title, $a_postvar);
    }

    public function checkInput() : bool
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
    public function setValueByArray(array $post)
    {
        $value = $post[$this->getPostVar()];
        $this->setValue($value ?: "");
    }

    public function setValue(string $value)
    {
        $this->value = $value;
    }

    /**
     * Inserts the input into the template.
     * @param $a_tpl
     * @return void
     * @throws ilTemplateException
     */
    public function insert($a_tpl)
    {
        $tpl = new ilTemplate($this->getFolderPath() . "tpl.htmlAreaInput.html", true, true);
        $tpl->setVariable("TEXT", $this->value);
        $tpl->setVariable("POST_VAR", $this->getPostVar());
        $tpl->setVariable("HTML_CLASS", $this->htmlClass);
        $tpl->setVariable("EDITABLE", $this->editable ? "true" : "false");

        $a_tpl->setCurrentBlock('prop_generic');
        $a_tpl->setVariable('PROP_GENERIC', $tpl->get());
        $a_tpl->parseCurrentBlock();
    }

    /**
     * Returns the path to the folder where the input is located.
     * @return string
     */
    protected function getFolderPath() : string
    {
        return strstr(realpath(__DIR__), "Customizing") . "/";
    }

    /**
     * Changes the html class of the div
     * @param string $htmlClass
     */
    public function setHtmlClass(string $htmlClass)
    {
        $this->htmlClass = $htmlClass;
    }

    public function setEditable(bool $editable)
    {
        $this->editable = $editable;
    }
}
