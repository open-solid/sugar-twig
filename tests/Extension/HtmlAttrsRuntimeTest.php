<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TalesFromADev\Twig\Extra\Tailwind\TailwindRuntime;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\TwigFilter;
use OpenSolid\SugarTwig\Extension\HtmlAttrsExtension;
use OpenSolid\SugarTwig\Extension\HtmlAttrsRuntime;

final class HtmlAttrsRuntimeTest extends TestCase
{
    private Environment $env;
    private HtmlAttrsRuntime $runtime;

    protected function setUp(): void
    {
        $this->env = new Environment(new ArrayLoader());
        $this->env->addExtension(new HtmlAttrsExtension());
        $this->runtime = new HtmlAttrsRuntime();
        $this->env->addRuntimeLoader(new readonly class($this->runtime) implements RuntimeLoaderInterface {
            public function __construct(private HtmlAttrsRuntime $runtime)
            {
            }

            public function load(string $class): ?object
            {
                return HtmlAttrsRuntime::class === $class ? $this->runtime : null;
            }
        });
    }

    #[Test]
    public function it_renders_standard_html_attributes(): void
    {
        $context = ['id' => 'main', 'role' => 'alert', 'class' => 'text-red-500', 'variant' => 'destructive'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="main" role="alert" class="text-red-500"', $result);
    }

    #[Test]
    public function it_filters_out_non_html_attributes(): void
    {
        $context = ['variant' => 'destructive', 'items' => [], 'separator' => '/', 'id' => 'main'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="main"', $result);
    }

    #[Test]
    public function it_allows_aria_prefixed_attributes(): void
    {
        $context = ['aria-label' => 'Close', 'aria-hidden' => 'true', 'variant' => 'sm'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('aria-label="Close" aria-hidden="true"', $result);
    }

    #[Test]
    public function it_allows_data_prefixed_attributes(): void
    {
        $context = ['data-slot' => 'trigger', 'data-state' => 'open', 'variant' => 'sm'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('data-slot="trigger" data-state="open"', $result);
    }

    #[Test]
    public function it_allows_alpine_directives(): void
    {
        $context = ['x-data' => '{ open: false }', 'x-show' => 'open', '@click' => 'open = !open', ':class' => "open ? 'active' : ''", 'variant' => 'default'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('x-data="{ open: false }" x-show="open" @click="open = !open" :class="open ? &#039;active&#039; : &#039;&#039;"', $result);
    }

    #[Test]
    public function it_excludes_html_attributes_used_as_component_props(): void
    {
        $context = ['title' => 'My Dialog', 'open' => true, 'id' => 'dialog-1', 'aria-live' => 'polite'];
        $result = $this->runtime->render($this->env, $context, [], ['title', 'open']);

        self::assertSame('id="dialog-1" aria-live="polite"', $result);
    }

    #[Test]
    public function it_excludes_twig_internal_variables(): void
    {
        $context = ['_parent' => ['foo' => 'bar'], '_seq' => [], '_key' => 0, 'id' => 'test'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="test"', $result);
    }

    #[Test]
    public function it_returns_empty_string_when_no_html_attributes(): void
    {
        $context = ['variant' => 'destructive', 'items' => [], '_parent' => []];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('', $result);
    }

    #[Test]
    public function it_renders_boolean_true_as_empty_attribute(): void
    {
        $context = ['disabled' => true];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('disabled=""', $result);
    }

    #[Test]
    public function it_omits_false_values(): void
    {
        $context = ['hidden' => false, 'id' => 'test'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="test"', $result);
    }

    #[Test]
    public function it_omits_null_values(): void
    {
        $context = ['hidden' => null, 'id' => 'test'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="test"', $result);
    }

    #[Test]
    public function it_applies_defaults_when_not_in_context(): void
    {
        $context = ['id' => 'main'];
        $result = $this->runtime->render($this->env, $context, ['role' => 'button', 'tabindex' => '0']);

        self::assertSame('id="main" role="button" tabindex="0"', $result);
    }

    #[Test]
    public function it_overrides_defaults_with_context_values(): void
    {
        $context = ['role' => 'alert', 'id' => 'main'];
        $result = $this->runtime->render($this->env, $context, ['role' => 'button', 'tabindex' => '0']);

        self::assertSame('role="alert" id="main" tabindex="0"', $result);
    }

    #[Test]
    public function it_merges_class_name_default_with_context_class(): void
    {
        // Without tailwind_merge filter registered, classes are concatenated
        $result = $this->runtime->render($this->env, context: ['className' => 'bg-primary p-4'], defaults: ['class' => 'text-red-500']);

        self::assertSame('class="text-red-500 bg-primary p-4"', $result);
    }

    #[Test]
    public function it_applies_class_name_default_when_no_class_in_context(): void
    {
        $context = ['id' => 'main'];
        $result = $this->runtime->render($this->env, $context, ['class' => 'bg-primary p-4']);

        self::assertSame('id="main" class="bg-primary p-4"', $result);
    }

    #[Test]
    public function it_combines_defaults_with_exclude(): void
    {
        $context = ['title' => 'Hello', 'id' => 'main'];
        $result = $this->runtime->render($this->env, $context, ['role' => 'dialog'], ['title']);

        self::assertSame('id="main" role="dialog"', $result);
    }

    #[Test]
    public function it_calls_tailwind_merge_filter_when_registered(): void
    {
        $this->env->addFilter(new TwigFilter('tailwind_merge', new TailwindRuntime()->merge(...)));

        $result = $this->runtime->render($this->env, context: ['className' => 'p-8'], defaults: ['class' => 'p-4 bg-primary']);

        self::assertSame('class="bg-primary p-8"', $result);
    }

    #[Test]
    public function it_works_via_twig_template(): void
    {
        $env = new Environment(new ArrayLoader([
            'test.html.twig' => '<div {{ html_attrs(exclude=[\'title\']) }}></div>',
        ]));
        $env->addExtension(new HtmlAttrsExtension());
        $runtime = new HtmlAttrsRuntime();
        $env->addRuntimeLoader(new class($runtime) implements RuntimeLoaderInterface {
            public function __construct(private readonly HtmlAttrsRuntime $runtime)
            {
            }

            public function load(string $class): ?object
            {
                return HtmlAttrsRuntime::class === $class ? $this->runtime : null;
            }
        });

        $result = $env->render('test.html.twig', [
            'variant' => 'destructive',
            'title' => 'Error',
            'id' => 'main',
            'aria-live' => 'polite',
        ]);

        self::assertSame('<div id="main" aria-live="polite"></div>', $result);
    }
}
