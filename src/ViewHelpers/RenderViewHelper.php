<?php
namespace TYPO3Fluid\Fluid\ViewHelpers;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\ParsedTemplateInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * A ViewHelper to render a section, a partial, a specified section in a partial
 * or a delegate ParsedTemplateInterface implementation.
 *
 * = Examples =
 *
 * <code title="Rendering partials">
 * <f:render partial="SomePartial" arguments="{foo: someVariable}" />
 * </code>
 * <output>
 * the content of the partial "SomePartial". The content of the variable {someVariable} will be available in the partial as {foo}
 * </output>
 *
 * <code title="Rendering sections">
 * <f:section name="someSection">This is a section. {foo}</f:section>
 * <f:render section="someSection" arguments="{foo: someVariable}" />
 * </code>
 * <output>
 * the content of the section "someSection". The content of the variable {someVariable} will be available in the partial as {foo}
 * </output>
 *
 * <code title="Rendering recursive sections">
 * <f:section name="mySection">
 *  <ul>
 *    <f:for each="{myMenu}" as="menuItem">
 *      <li>
 *        {menuItem.text}
 *        <f:if condition="{menuItem.subItems}">
 *          <f:render section="mySection" arguments="{myMenu: menuItem.subItems}" />
 *        </f:if>
 *      </li>
 *    </f:for>
 *  </ul>
 * </f:section>
 * <f:render section="mySection" arguments="{myMenu: menu}" />
 * </code>
 * <output>
 * <ul>
 *   <li>menu1
 *     <ul>
 *       <li>menu1a</li>
 *       <li>menu1b</li>
 *     </ul>
 *   </li>
 * [...]
 * (depending on the value of {menu})
 * </output>
 *
 *
 * <code title="Passing all variables to a partial">
 * <f:render partial="somePartial" arguments="{_all}" />
 * </code>
 * <output>
 * the content of the partial "somePartial".
 * Using the reserved keyword "_all", all available variables will be passed along to the partial
 * </output>
 *
 *
 * <code title="Rendering via a delegate ParsedTemplateInterface implementation w/ custom arguments">
 * <f:render delegate="My\Special\ParsedTemplateImplementation" arguments="{_all}" />
 * </code>
 * <output>
 * Whichever output was generated by calling My\Special\ParsedTemplateImplementation->render()
 * with cloned RenderingContextInterface $renderingContext as only argument and content of arguments
 * assigned in VariableProvider of cloned context. Supports all other input arguments including
 * recursive rendering, contentAs argument, default value etc.
 * Note that while ParsedTemplateInterface supports returning a Layout name, this Layout will not
 * be respected when rendering using this method. Only the `render()` method will be called!
 * </output>
 *
 * @api
 */
class RenderViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;
    
    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('section', 'string', 'Section to render - combine with partial to render section in partial');
        $this->registerArgument('partial', 'string', 'Partial to render, with or without section');
        $this->registerArgument('delegate', 'string', 'Optional PHP class name of a permanent, included-in-app ParsedTemplateInterface implementation to override partial/section');
        $this->registerArgument('arguments', 'array', 'Array of variables to be transferred. Use {_all} for all variables', false, []);
        $this->registerArgument('optional', 'boolean', 'If TRUE, considers the *section* optional. Partial never is.', false, false);
        $this->registerArgument('default', 'mixed', 'Value (usually string) to be displayed if the section or partial does not exist');
        $this->registerArgument('contentAs', 'string', 'If used, renders the child content and adds it as a template variable with this name for use in the partial/section');
    }

    /**
     * @return mixed
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $section = $arguments['section'];
        $partial = $arguments['partial'];
        $variables = (array) $arguments['arguments'];
        $optional = (boolean) $arguments['optional'];
        $delegate = $arguments['delegate'];
        $tagContent = $renderChildrenClosure();
        if ($arguments['contentAs']) {
            $variables[$arguments['contentAs']] = $tagContent;
        }

        $view = $renderingContext->getViewHelperVariableContainer()->getView();
        $content = '';
        if ($delegate !== null) {
            if (!is_a($delegate, ParsedTemplateInterface::class, true)) {
                throw new \InvalidArgumentException(sprintf('Cannot render %s - must implement ParsedTemplateInterface!', $delegate));
            }
            $renderingContext = clone $renderingContext;
            $renderingContext->getVariableProvider()->setSource($variables);
            $content = (new $delegate())->render($renderingContext);
        } elseif ($partial !== null) {
            $content = $view->renderPartial($partial, $section, $variables, $optional);
        } elseif ($section !== null) {
            $content = $view->renderSection($section, $variables, $optional);
        } elseif (!$optional) {
            throw new \InvalidArgumentException('ViewHelper f:render called without either argument section, partial or delegate and optional flag is false');
        }
        // Replace empty content with default value. If default is
        // not set, NULL is returned and cast to a new, empty string
        // outside of this ViewHelper.
        if ($content === '') {
            $content = $arguments['default'] ?: $tagContent;
        }
        return $content;
    }
}
