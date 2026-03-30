<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Extension;

use TalesFromADev\Twig\Extra\Tailwind\TailwindRuntime;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Extra\Html\HtmlExtension;

final readonly class HtmlAttrsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ?TailwindRuntime $tailwindRuntime = null,
    ) {
    }

    /**
     * Twig internal context variables — never rendered as HTML attributes.
     */
    private const array TWIG_INTERNALS = ['_parent' => true, '_seq' => true, '_key' => true, '_iterated' => true, '_charset' => true];

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
            $context['class'] = isset($defaults['class']) ? $defaults['class'].' '.$context['className'] : $context['className'];
            unset($context['className']);
        }

        $attrs = [];
        foreach ($context + $defaults as $key => $value) {
            if (isset($excluded[$key]) || !is_scalar($value)) {
                continue;
            }

            if ('class' === $key && $this->tailwindRuntime) {
                $value = $this->tailwindRuntime->merge($value);
            }

            $attrs[$key] = $value;
        }

        return HtmlExtension::htmlAttr($env, $attrs);
    }
}
