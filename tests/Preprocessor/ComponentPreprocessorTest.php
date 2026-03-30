<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Preprocessor;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use OpenSolid\SugarTwig\Parser\ComponentTagParser;
use OpenSolid\SugarTwig\Preprocessor\ComponentNameResolver;
use OpenSolid\SugarTwig\Preprocessor\ComponentPreprocessor;

final class ComponentPreprocessorTest extends TestCase
{
    private ComponentPreprocessor $preprocessor;

    protected function setUp(): void
    {
        $this->preprocessor = new ComponentPreprocessor(
            new ComponentTagParser(),
            new ComponentNameResolver(),
        );
    }

    // --- US1: Self-closing tags -> include ---

    #[Test]
    public function it_transforms_self_closing_tag_to_include(): void
    {
        $source = '<Alert title="Heads up!" description="You can add components." />';
        $expected = "{{ include('components/alert.html.twig', {title: 'Heads up!', description: 'You can add components.'}, with_context=false) }}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_transforms_self_closing_tag_with_boolean_attribute(): void
    {
        $source = '<Checkbox id="terms" label="Accept terms" checked />';
        $expected = "{{ include('components/checkbox.html.twig', {id: 'terms', label: 'Accept terms', checked: true}, with_context=false) }}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_quotes_hyphenated_attribute_names(): void
    {
        $source = '<Alert title="Error" aria-label="Alert" x-data="{}" />';
        $expected = "{{ include('components/alert.html.twig', {title: 'Error', 'aria-label': 'Alert', 'x-data': '{}'}, with_context=false) }}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_returns_source_unchanged_when_no_component_tags(): void
    {
        $source = '<div class="test">{{ include("template.html.twig") }}</div>';

        self::assertSame($source, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_handles_self_closing_tag_with_no_attributes(): void
    {
        $source = '<Spinner />';
        $expected = "{{ include('components/spinner.html.twig', {}, with_context=false) }}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_escapes_single_quotes_in_attribute_values(): void
    {
        $source = '<Alert title="He said \'hello\'" />';
        $expected = "{{ include('components/alert.html.twig', {title: 'He said \\'hello\\''}, with_context=false) }}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_preserves_surrounding_content(): void
    {
        $source = '<div>Before <Alert title="Test" /> After</div>';
        $expected = "<div>Before {{ include('components/alert.html.twig', {title: 'Test'}, with_context=false) }} After</div>";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    // --- US2: Children/blocks -> embed ---

    #[Test]
    public function it_transforms_tag_with_children_to_embed(): void
    {
        $source = '<Button variant="outline">Click me</Button>';
        $expected = "{% embed 'components/button.html.twig' with {variant: 'outline'} only %}{% block content %}Click me{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_transforms_named_blocks(): void
    {
        $source = '<Dialog title="Edit"><BlockTrigger>btn</BlockTrigger><BlockBody>form</BlockBody></Dialog>';
        $expected = "{% embed 'components/dialog.html.twig' with {title: 'Edit'} only %}{% block trigger %}btn{% endblock %}{% block body %}form{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_transforms_mixed_content_and_named_blocks(): void
    {
        $source = '<Alert title="Error">Some preamble text<BlockAction>btn</BlockAction></Alert>';
        $expected = "{% embed 'components/alert.html.twig' with {title: 'Error'} only %}{% block content %}Some preamble text{% endblock %}{% block action %}btn{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_rejects_content_attribute_with_children(): void
    {
        $source = '<Button content="Click me">child text</Button>';

        $this->expectException(InvalidArgumentException::class);
        $this->preprocessor->process($source);
    }

    #[Test]
    public function it_transforms_tag_with_children_and_no_attributes(): void
    {
        $source = '<Card>content</Card>';
        $expected = "{% embed 'components/card.html.twig' only %}{% block content %}content{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    // --- US3: Nested components ---

    #[Test]
    public function it_transforms_nested_components(): void
    {
        $source = '<Card><Button variant="outline">Click</Button></Card>';
        $expected = "{% embed 'components/card.html.twig' only %}{% block content %}{% embed 'components/button.html.twig' with {variant: 'outline'} only %}{% block content %}Click{% endblock %}{% endembed %}{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_transforms_self_closing_inside_parent(): void
    {
        $source = '<Card><Alert title="Test" /></Card>';
        $expected = "{% embed 'components/card.html.twig' only %}{% block content %}{{ include('components/alert.html.twig', {title: 'Test'}, with_context=false) }}{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_transforms_same_name_nesting(): void
    {
        $source = '<Card><Card>Inner</Card></Card>';
        $expected = "{% embed 'components/card.html.twig' only %}{% block content %}{% embed 'components/card.html.twig' only %}{% block content %}Inner{% endblock %}{% endembed %}{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_transforms_three_levels_deep(): void
    {
        $source = '<Dialog title="Edit"><BlockBody><Field id="name" label="Name"><Input id="name" name="name" /></Field></BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("include('components/input.html.twig'", $result);
        self::assertStringContainsString("embed 'components/field.html.twig'", $result);
        self::assertStringContainsString("embed 'components/dialog.html.twig'", $result);
    }

    #[Test]
    public function it_transforms_full_dialog_example(): void
    {
        $source = <<<'TWIG'
            <Dialog title="Edit profile">
                <BlockTrigger>
                    <Button variant="outline" content="Edit Profile" />
                </BlockTrigger>
                <BlockBody>
                    <Field id="name" label="Name">
                        <Input id="name" name="name" />
                    </Field>
                </BlockBody>
                <BlockFooter>
                    <Button content="Save" />
                </BlockFooter>
            </Dialog>
            TWIG;

        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("embed 'components/dialog.html.twig'", $result);
        self::assertStringContainsString("include('components/button.html.twig', {variant: 'outline', content: 'Edit Profile'}, with_context=false)", $result);
        self::assertStringContainsString("embed 'components/field.html.twig'", $result);
        self::assertStringContainsString("include('components/input.html.twig'", $result);
        self::assertStringContainsString("include('components/button.html.twig', {content: 'Save'}, with_context=false)", $result);
        self::assertStringContainsString('{% block trigger %}', $result);
        self::assertStringContainsString('{% block body %}', $result);
        self::assertStringContainsString('{% block footer %}', $result);
        self::assertDoesNotMatchRegularExpression('/<[A-Z][a-zA-Z]+/', $result);
    }

    // --- US4: Bare Block tag ---

    #[Test]
    public function it_ignores_bare_block_tag(): void
    {
        $source = '<Dialog title="Test"><Block>content</Block></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringNotContainsString('{% block block %}', $result);
        self::assertStringContainsString('{% block content %}', $result);
    }

    // --- US5: Multiple compound block tags ---

    #[Test]
    public function it_transforms_multiple_compound_blocks(): void
    {
        $source = '<Dialog><BlockTrigger>btn</BlockTrigger><BlockBody>form</BlockBody><BlockFooter>actions</BlockFooter></Dialog>';
        $expected = "{% embed 'components/dialog.html.twig' only %}{% block trigger %}btn{% endblock %}{% block body %}form{% endblock %}{% block footer %}actions{% endblock %}{% endembed %}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_pairs_compound_blocks_by_full_tag_name(): void
    {
        $source = '<Dialog><BlockTrigger>btn</BlockTrigger><BlockBody>form</BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block trigger %}btn{% endblock %}', $result);
        self::assertStringContainsString('{% block body %}form{% endblock %}', $result);
    }

    #[Test]
    public function it_handles_nested_compound_blocks(): void
    {
        $source = '<Dialog><BlockBody><Card><BlockFooter>inner</BlockFooter></Card></BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block body %}', $result);
        self::assertStringContainsString('{% block footer %}inner{% endblock %}', $result);
    }

    // --- US6: Block attributes with conditional wrapper ---

    #[Test]
    public function it_wraps_block_content_with_div_when_attributes_present(): void
    {
        $source = '<Dialog><BlockBody class="p-4">form</BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block body %}<div class="p-4">form</div>{% endblock %}', $result);
    }

    #[Test]
    public function it_does_not_wrap_block_content_without_attributes(): void
    {
        $source = '<Dialog><BlockBody>form</BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block body %}form{% endblock %}', $result);
        self::assertStringNotContainsString('<div', $result);
    }

    #[Test]
    public function it_wraps_self_closing_block_with_attributes(): void
    {
        $source = '<Dialog><BlockIcon class="size-4" /></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block icon %}<div class="size-4"></div>{% endblock %}', $result);
    }

    #[Test]
    public function it_wraps_block_with_multiple_attributes(): void
    {
        $source = '<Dialog><BlockFooter class="flex gap-2" id="actions">buttons</BlockFooter></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block footer %}<div class="flex gap-2" id="actions">buttons</div>{% endblock %}', $result);
    }

    #[Test]
    public function it_passes_twig_expressions_in_block_attributes(): void
    {
        $source = '<Dialog><BlockBody class="{{ dynamicClass }}">form</BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block body %}<div class="{{ dynamicClass }}">form</div>{% endblock %}', $result);
    }

    // --- Edge cases ---

    #[Test]
    public function it_skips_tags_inside_comments(): void
    {
        $source = '{# <Alert title="test" /> #}';
        self::assertSame($source, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_skips_tags_inside_verbatim(): void
    {
        $source = '{% verbatim %}<Alert title="test" />{% endverbatim %}';
        self::assertSame($source, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_handles_greater_than_inside_quoted_attr(): void
    {
        $source = '<Button class=\'x-on:click="if (count > 5) alert()"\' />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("include('components/button.html.twig'", $result);
        self::assertStringNotContainsString('<Button', $result);
    }

    #[Test]
    public function it_passes_through_twig_expressions_in_children(): void
    {
        $source = '<Card>{{ some_variable }}</Card>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{{ some_variable }}', $result);
        self::assertStringContainsString("embed 'components/card.html.twig'", $result);
    }

    #[Test]
    public function it_only_transforms_outside_comments(): void
    {
        $source = '<Button />{# <Alert /> #}<Input />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("include('components/button.html.twig', {}, with_context=false)", $result);
        self::assertStringContainsString('{# <Alert /> #}', $result);
        self::assertStringContainsString("include('components/input.html.twig', {}, with_context=false)", $result);
    }

    // --- US: Expression-valued attributes ---

    #[Test]
    public function it_serializes_pure_expression_value(): void
    {
        $source = '<Alert title="{{ alertTitle }}" />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('title: alertTitle', $result);
        self::assertStringNotContainsString("title: '{{ alertTitle }}'", $result);
    }

    #[Test]
    public function it_serializes_boolean_expression_value(): void
    {
        $source = '<Button disabled="{{ false }}" />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('disabled: false', $result);
    }

    #[Test]
    public function it_serializes_filter_expression_value(): void
    {
        $source = '<Badge count="{{ items|length }}" />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('count: items|length', $result);
    }

    #[Test]
    public function it_serializes_mixed_content_value(): void
    {
        $source = '<Card title="Hello {{ name }}" />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("title: 'Hello ' ~ name", $result);
    }

    #[Test]
    public function it_serializes_multi_expression_value(): void
    {
        $source = '<Input class="{{ base }} {{ modifier }}" />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("class: base ~ ' ' ~ modifier", $result);
    }

    #[Test]
    public function it_preserves_static_value_unchanged(): void
    {
        $source = '<Button variant="outline" />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("variant: 'outline'", $result);
    }

    #[Test]
    public function it_serializes_expression_in_paired_tag(): void
    {
        $source = '<Button disabled="{{ false }}">Click</Button>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('disabled: false', $result);
        self::assertStringContainsString("embed 'components/button.html.twig'", $result);
        self::assertStringContainsString('{% block content %}Click{% endblock %}', $result);
    }

    #[Test]
    public function it_preserves_expression_in_compound_block_attrs(): void
    {
        $source = '<Dialog><BlockBody class="{{ cls }}">form</BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('{% block body %}<div class="{{ cls }}">form</div>{% endblock %}', $result);
    }

    // --- US: Conditional attributes ---

    #[Test]
    public function it_transforms_conditional_attribute_to_ternary(): void
    {
        $source = '<Button {% if isPrimary %}variant="primary"{% endif %} />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("variant: isPrimary ? 'primary' : null", $result);
    }

    #[Test]
    public function it_transforms_conditional_if_else_to_ternary(): void
    {
        $source = '<Button {% if isLarge %}size="lg"{% else %}size="sm"{% endif %} />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("size: isLarge ? 'lg' : 'sm'", $result);
    }

    #[Test]
    public function it_transforms_conditional_boolean_attribute(): void
    {
        $source = '<Input {% if req %}required{% endif %} />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('required: req ? true : null', $result);
    }

    #[Test]
    public function it_transforms_conditional_with_expression_value(): void
    {
        $source = '<Button {% if hasIcon %}icon="{{ iconName }}"{% endif %} />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString('icon: hasIcon ? iconName : null', $result);
    }

    #[Test]
    public function it_transforms_multiple_conditional_attrs_in_one_block(): void
    {
        $source = '<Button {% if active %}variant="default" size="lg"{% endif %} />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("variant: active ? 'default' : null", $result);
        self::assertStringContainsString("size: active ? 'lg' : null", $result);
    }

    #[Test]
    public function it_transforms_mixed_unconditional_and_conditional_attrs(): void
    {
        $source = '<Button variant="outline" {% if isPrimary %}size="lg"{% endif %} />';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("variant: 'outline'", $result);
        self::assertStringContainsString("size: isPrimary ? 'lg' : null", $result);
    }

    // --- US: Context isolation ---

    #[Test]
    public function it_isolates_self_closing_tag_with_no_attrs(): void
    {
        $source = '<Spinner />';
        $expected = "{{ include('components/spinner.html.twig', {}, with_context=false) }}";

        self::assertSame($expected, $this->preprocessor->process($source));
    }

    #[Test]
    public function it_isolates_paired_tag_with_no_attrs(): void
    {
        $source = '<Card>content</Card>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("embed 'components/card.html.twig' only %}", $result);
    }

    #[Test]
    public function it_isolates_nested_components(): void
    {
        $source = '<Card><Alert title="Test" /></Card>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("embed 'components/card.html.twig' only %}", $result);
        self::assertStringContainsString("include('components/alert.html.twig', {title: 'Test'}, with_context=false)", $result);
    }

    #[Test]
    public function it_isolates_compound_block_embeds(): void
    {
        $source = '<Dialog><BlockBody>form</BlockBody></Dialog>';
        $result = $this->preprocessor->process($source);

        self::assertStringContainsString("embed 'components/dialog.html.twig' only %}", $result);
    }
}
