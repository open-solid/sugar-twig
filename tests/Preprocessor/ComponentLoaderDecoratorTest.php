<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Preprocessor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use OpenSolid\SugarTwig\Extension\HtmlAttrsExtension;
use OpenSolid\SugarTwig\Extension\HtmlAttrsRuntime;
use OpenSolid\SugarTwig\Parser\ComponentTagParser;
use OpenSolid\SugarTwig\Preprocessor\ComponentLoaderDecorator;
use OpenSolid\SugarTwig\Preprocessor\ComponentNameResolver;
use OpenSolid\SugarTwig\Preprocessor\ComponentPreprocessor;

final class ComponentLoaderDecoratorTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $innerLoader = new ArrayLoader([
            'components/alert.html.twig' => '<div data-slot="alert" class="alert" {{ html_attrs(exclude=[\'variant\', \'title\', \'description\', \'icon\', \'class\']) }}>{% if title is defined %}<h5>{{ title }}</h5>{% endif %}{% if description is defined %}<p>{{ description }}</p>{% endif %}{% block content %}{% endblock %}</div>',
            'components/button.html.twig' => '<button data-slot="button" class="btn" {{ html_attrs(exclude=[\'variant\', \'size\', \'content\', \'as\', \'class\']) }}>{% block content %}{{ content|default(\'\') }}{% endblock %}</button>',
            'components/card.html.twig' => '<div data-slot="card" class="card" {{ html_attrs(exclude=[\'class\']) }}>{% block content %}{% endblock %}</div>',
            'components/dialog.html.twig' => '<div data-slot="dialog">{% block trigger %}{% endblock %}<div class="dialog-content">{% if title is defined %}<h2>{{ title }}</h2>{% endif %}{% block body %}{% endblock %}{% block footer %}{% endblock %}</div></div>',
            'components/field.html.twig' => '<div data-slot="field">{% if label is defined %}<label for="{{ id|default(\'\') }}">{{ label }}</label>{% endif %}{% block content %}{% endblock %}</div>',
            'components/input.html.twig' => '<input data-slot="input" id="{{ id|default(\'\') }}" name="{{ name|default(\'\') }}" {{ html_attrs(exclude=[\'id\', \'name\', \'type\', \'class\']) }} />',
            'components/checkbox.html.twig' => '<input type="checkbox" id="{{ id|default(\'\') }}" {{ checked|default(false) ? \'checked\' : \'\' }} {{ html_attrs(exclude=[\'id\', \'label\', \'checked\', \'class\']) }} />',
            'page.html.twig' => '<Alert title="Heads up!" description="You can add components." />',
            'page-with-blocks.html.twig' => '<Dialog title="Edit profile"><BlockTrigger><Button variant="outline" content="Edit Profile" /></BlockTrigger><BlockBody><Field id="name" label="Name"><Input id="name" name="name" /></Field></BlockBody><BlockFooter><Button content="Save" /></BlockFooter></Dialog>',
            'page-self-closing.html.twig' => '<Checkbox id="terms" label="Accept terms" checked />',
            'page-nested.html.twig' => '<Card><Button variant="outline">Click me</Button></Card>',
        ]);

        $decorator = new ComponentLoaderDecorator(
            $innerLoader,
            new ComponentPreprocessor(new ComponentTagParser(), new ComponentNameResolver()),
        );

        $this->twig = new Environment($decorator);
        $this->twig->addExtension(new HtmlAttrsExtension());
        $runtime = new HtmlAttrsRuntime();
        $this->twig->addRuntimeLoader(new class($runtime) implements RuntimeLoaderInterface {
            public function __construct(private readonly HtmlAttrsRuntime $runtime)
            {
            }

            public function load(string $class): ?object
            {
                return HtmlAttrsRuntime::class === $class ? $this->runtime : null;
            }
        });
    }

    #[Test]
    public function it_renders_self_closing_component(): void
    {
        $result = $this->twig->render('page.html.twig');

        self::assertStringContainsString('data-slot="alert"', $result);
        self::assertStringContainsString('<h5>Heads up!</h5>', $result);
        self::assertStringContainsString('<p>You can add components.</p>', $result);
    }

    #[Test]
    public function it_renders_boolean_attribute(): void
    {
        $result = $this->twig->render('page-self-closing.html.twig');

        self::assertStringContainsString('type="checkbox"', $result);
        self::assertStringContainsString('checked', $result);
    }

    #[Test]
    public function it_renders_nested_components(): void
    {
        $result = $this->twig->render('page-nested.html.twig');

        self::assertStringContainsString('data-slot="card"', $result);
        self::assertStringContainsString('data-slot="button"', $result);
        self::assertStringContainsString('Click me', $result);
    }

    #[Test]
    public function it_renders_full_dialog_with_blocks(): void
    {
        $result = $this->twig->render('page-with-blocks.html.twig');

        self::assertStringContainsString('data-slot="dialog"', $result);
        self::assertStringContainsString('<h2>Edit profile</h2>', $result);
        self::assertStringContainsString('Edit Profile', $result);
        self::assertStringContainsString('data-slot="field"', $result);
        self::assertStringContainsString('<label for="name">Name</label>', $result);
        self::assertStringContainsString('data-slot="input"', $result);
        self::assertStringContainsString('Save', $result);
    }

    #[Test]
    public function it_delegates_cache_methods_to_inner_loader(): void
    {
        self::assertTrue($this->twig->getLoader()->exists('page.html.twig'));
        self::assertFalse($this->twig->getLoader()->exists('nonexistent.html.twig'));
    }
}
