<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Extension;

use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Extra\Html\HtmlExtension;

final class HtmlAttrsRuntime implements RuntimeExtensionInterface
{
    /**
     * Twig internal context variables — never rendered as HTML attributes.
     */
    private const array TWIG_INTERNALS = ['_parent' => true, '_seq' => true, '_key' => true, '_iterated' => true, '_charset' => true];

    /**
     * Standard HTML attributes allowed to pass through.
     * `class` is intentionally excluded — components handle it via tailwind_merge.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes
     */
    private const array HTML_ATTRIBUTES = [
        // Global attributes
        'accesskey' => true, 'autocapitalize' => true, 'autofocus' => true, 'contenteditable' => true,
        'dir' => true, 'draggable' => true, 'enterkeyhint' => true, 'hidden' => true, 'id' => true, 'inert' => true,
        'inputmode' => true, 'is' => true, 'itemid' => true, 'itemprop' => true, 'itemref' => true, 'itemscope' => true,
        'itemtype' => true, 'lang' => true, 'nonce' => true, 'part' => true, 'popover' => true, 'popovertarget' => true,
        'popovertargetaction' => true, 'role' => true, 'slot' => true, 'spellcheck' => true, 'style' => true,
        'tabindex' => true, 'title' => true, 'translate' => true, 'virtualkeyboardpolicy' => true, 'class' => true,
        // Link and navigation
        'download' => true, 'href' => true, 'hreflang' => true, 'ping' => true, 'referrerpolicy' => true, 'rel' => true, 'target' => true,
        // Embedded content
        'alt' => true, 'coords' => true, 'crossorigin' => true, 'decoding' => true, 'fetchpriority' => true, 'height' => true,
        'ismap' => true, 'loading' => true, 'sizes' => true, 'src' => true, 'srcset' => true, 'usemap' => true, 'width' => true,
        // Media
        'autoplay' => true, 'controls' => true, 'loop' => true, 'muted' => true, 'playsinline' => true, 'poster' => true, 'preload' => true,
        // Table
        'colspan' => true, 'headers' => true, 'rowspan' => true, 'scope' => true, 'span' => true,
        // Form
        'accept' => true, 'action' => true, 'autocomplete' => true, 'capture' => true, 'checked' => true, 'cols' => true,
        'dirname' => true, 'disabled' => true, 'enctype' => true, 'for' => true, 'form' => true, 'formaction' => true,
        'formenctype' => true, 'formmethod' => true, 'formnovalidate' => true, 'formtarget' => true, 'high' => true,
        'label' => true, 'list' => true, 'low' => true, 'max' => true, 'maxlength' => true, 'method' => true, 'min' => true,
        'minlength' => true, 'multiple' => true, 'name' => true, 'novalidate' => true, 'open' => true, 'optimum' => true,
        'pattern' => true, 'placeholder' => true, 'readonly' => true, 'required' => true, 'rows' => true, 'selected' => true,
        'size' => true, 'step' => true, 'type' => true, 'value' => true, 'wrap' => true,
        // Meta/script/style
        'charset' => true, 'content' => true, 'http-equiv' => true, 'media' => true,
        // Other
        'cite' => true, 'datetime' => true, 'default' => true, 'kind' => true, 'reversed' => true, 'sandbox' => true,
        'shape' => true, 'srcdoc' => true, 'srclang' => true, 'start' => true,
    ];

    /**
     * Filters the template context to pass through only recognized HTML attributes,
     * merges with defaults, and delegates to HtmlExtension::htmlAttr() for safe rendering.
     *
     * @param array<string, mixed> $context  Full template context (injected by Twig via needs_context)
     * @param array<string, mixed> $defaults Default attributes; context values override these.
     *                                       Use 'className' to set a default class that merges
     *                                       with the caller's 'class' via tailwind_merge.
     * @param list<string>         $exclude  Attributes to exclude even if they are valid HTML
     */
    public function render(Environment $env, array $context, array $defaults = [], array $exclude = []): string
    {
        $excluded = [] === $exclude ? self::TWIG_INTERNALS : self::TWIG_INTERNALS + array_flip($exclude);

        if (isset($context['className'])) {
            $context['class'] = isset($defaults['class'])
                ? $this->tailwindMerge($env, $defaults['class'].' '.$context['className'])
                : $context['className'];

            unset($context['className']);
        }

        $attrs = [];
        foreach ($context + $defaults as $key => $value) {
            if (isset($excluded[$key])) {
                continue;
            }

            if (self::isHtmlAttribute($key)) {
                $attrs[$key] = $value;
            }
        }

        return HtmlExtension::htmlAttr($env, $attrs);
    }

    private function tailwindMerge(Environment $env, string $classes): string
    {
        if (null === $filter = $env->getFilter('tailwind_merge')) {
            return $classes;
        }

        return $filter->getCallable()($classes);
    }

    private static function isHtmlAttribute(string $name): bool
    {
        if (isset(self::HTML_ATTRIBUTES[$name])) {
            return true;
        }

        return match ($name[0] ?? '') {
            'a' => str_starts_with($name, 'aria-'),
            'd' => str_starts_with($name, 'data-'),
            'o' => str_starts_with($name, 'on'),
            'x' => str_starts_with($name, 'x-'),
            '@', ':' => true,
            default => false,
        };
    }
}
