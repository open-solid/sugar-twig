<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HtmlAttrsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('html_attrs', [HtmlAttrsRuntime::class, 'render'], [
                'needs_environment' => true,
                'needs_context' => true,
                'is_safe' => ['html'],
            ]),
        ];
    }
}
