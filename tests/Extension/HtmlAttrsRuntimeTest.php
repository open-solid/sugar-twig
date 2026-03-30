<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Extension;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
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
        $context = ['id' => 'main', 'role' => 'alert', 'variant' => 'destructive'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="main" role="alert"', $result);
    }

    #[Test]
    public function it_filters_out_non_html_attributes(): void
    {
        $context = ['variant' => 'destructive', 'items' => [], 'separator' => '/', 'id' => 'main'];
        $result = $this->runtime->render($this->env, $context);

        self::assertSame('id="main"', $result);
    }

    #[Test]
    public function it_filters_out_class_attribute(): void
    {
        $context = ['class' => 'text-red-500', 'id' => 'main'];
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
        $result = $this->runtime->render($this->env, $context, ['title', 'open']);

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
