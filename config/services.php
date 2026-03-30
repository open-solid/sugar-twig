<?php

use OpenSolid\SugarTwig\Extension\HtmlAttrsExtension;
use OpenSolid\SugarTwig\Extension\HtmlAttrsRuntime;
use OpenSolid\SugarTwig\Parser\ComponentTagParser;
use OpenSolid\SugarTwig\Preprocessor\ComponentLoaderDecorator;
use OpenSolid\SugarTwig\Preprocessor\ComponentNameResolver;
use OpenSolid\SugarTwig\Preprocessor\ComponentPreprocessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container
        ->services()
            ->set('sugar_twig.parser', ComponentTagParser::class)

            ->set('sugar_twig.name_resolver', ComponentNameResolver::class)

            ->set('sugar_twig.preprocessor', ComponentPreprocessor::class)
                ->args([
                    service('sugar_twig.parser'),
                    service('sugar_twig.name_resolver'),
                ])

            ->set('sugar_twig.loader_decorator', ComponentLoaderDecorator::class)
                ->decorate('twig.loader.native_filesystem')
                ->args([
                    service('.inner'),
                    service('sugar_twig.preprocessor'),
                ])

            ->set('sugar_twig.html_attrs_extension', HtmlAttrsExtension::class)
                ->tag('twig.extension')

            ->set('sugar_twig.html_attrs_runtime', HtmlAttrsRuntime::class)
                ->args([
                    service('twig.runtime.tailwind')->nullOnInvalid(),
                ])
                ->tag('twig.runtime')
    ;
};
