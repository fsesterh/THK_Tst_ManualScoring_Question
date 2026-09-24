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

namespace ILIAS\Plugin\TstManualScoringQuestion\Renderer;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ilCtrlInterface;
use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
use ILIAS\Test\Scoring\Manual\ConsecutiveScoringGUI;
use ILIAS\UI\Component\Component;
use ILIAS\UI\Component\Navigation\Sequence\Sequence;
use ILIAS\UI\Implementation\Render\DecoratedRenderer;
use ILIAS\UI\Renderer;
use ilObjTestGUI;
use ilTstManualScoringQuestionPlugin;
use ilTstManualScoringQuestionUIHookGUI;
use ilUIPluginRouterGUI;
use ReflectionMethod;

class ManualScoringQuestionRenderer extends DecoratedRenderer
{
    private readonly ilTstManualScoringQuestionPlugin $plugin;
    private readonly ilCtrlInterface $ctrl;
    private readonly Factory $refinery;
    private WrapperFactory $http_wrapper;

    private static bool $injected_plugin_scoring_mode = false;

    public function __construct(private readonly Container $dic, Closure $closure)
    {
        $this->plugin = ilTstManualScoringQuestionPlugin::getInstance();
        $this->ctrl = $this->dic->ctrl();
        $this->refinery = $this->dic->refinery();
        $this->http_wrapper = $this->dic->http()->wrapper();
        parent::__construct($closure($dic));
    }

    protected function manipulateRendering($component, Renderer $root): ?string
    {
        if (strtolower($this->ctrl->getCmdClass()) === strtolower(ilObjTestGUI::class)) {
            $cmd_class = $this->http_wrapper->query()->retrieve("cmdClass", $this->refinery->kindlyTo()->string());
            if (strtolower($cmd_class) !== strtolower(str_replace("\\", "", ConsecutiveScoringGUI::class))) {
                return null;
            }

            $ref_id = $this->http_wrapper->query()->retrieve(
                "ref_id",
                $this->refinery->byTrying([
                    $this->refinery->kindlyTo()->int(),
                    $this->refinery->always(null)
                ])
            );

            if (!$ref_id) {
                return null;
            }

            if (!self::$injected_plugin_scoring_mode) {
                return $this->injectPluginScoringMode($component, $root, $ref_id);
            }
        }

        return null;
    }

    /**
     * @param Component[]|Component $components
     */
    private function injectPluginScoringMode(array|Component $components, Renderer $root, int $ref_id): ?string
    {
        if (
            !is_array($components)
        ) {
            return null;
        }

        $other_components = [];
        /** @var Sequence $sequence */
        $sequence = null;
        foreach ($components as $component) {
            if ($component instanceof Sequence) {
                $sequence = $component;
            } else {
                $other_components[] = $component;
            }
        }

        if (!$sequence) {
            return null;
        }

        $refl_method = new ReflectionMethod($sequence, "getId");
        if ($refl_method->invoke($sequence) !== "cs_$ref_id") {
            return null;
        }

        self::$injected_plugin_scoring_mode = true;

        $ui_renderer = $this->dic->ui()->renderer();
        $lng = $this->dic->language();

        $sequence_html = $ui_renderer->render($sequence);

        $dom = new DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8"?>' . $sequence_html);

        $x_path = new DOMXPath($dom);
        foreach ($x_path->query("//div[@class='c-sequence__navigation--viewcontrols']/form/div[contains(@class, 'il-viewcontrol')]") as $view_control) {
            $correct_view_control_found = false;

            /** @var DOMElement $button */
            foreach ($x_path->query("button", $view_control) as $button) {
                if (in_array($button->nodeValue, [$lng->txt('mode_user'), $lng->txt('mode_question')], true)) {
                    $correct_view_control_found = true;
                    break;
                }
            }

            if (!$correct_view_control_found) {
                continue;
            }

            $plugin_link = $dom->createElement("a", $this->plugin->txt("tmsq_scoring"));
            $plugin_link->className = "btn btn-default";

            $this->ctrl->setParameterByClass(ilTstManualScoringQuestionUIHookGUI::class, "ref_id", $ref_id);

            $plugin_link->setAttribute(
                "href",
                $this->dic->ctrl()->getLinkTargetByClass(
                    [ilUIPluginRouterGUI::class, ilTstManualScoringQuestionUIHookGUI::class],
                    "showTmsqManualScoring"
                )
            );
            $view_control->append($plugin_link);
        }

        return $ui_renderer->render($other_components) . $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
    }
}
