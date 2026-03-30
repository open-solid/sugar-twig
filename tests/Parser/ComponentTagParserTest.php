<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Tests\Parser;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use OpenSolid\SugarTwig\Parser\ComponentTagParser;
use OpenSolid\SugarTwig\Parser\TagType;

final class ComponentTagParserTest extends TestCase
{
    private ComponentTagParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ComponentTagParser();
    }

    #[Test]
    public function it_parses_self_closing_tag_with_no_attributes(): void
    {
        $tags = $this->parser->parse('<Alert />');

        self::assertCount(1, $tags);
        self::assertSame('Alert', $tags[0]->name);
        self::assertSame(TagType::SelfClosing, $tags[0]->type);
        self::assertSame(0, $tags[0]->startOffset);
        self::assertSame(9, $tags[0]->endOffset);
        self::assertSame([], $tags[0]->attributes);
    }

    #[Test]
    public function it_parses_self_closing_tag_with_string_attributes(): void
    {
        $tags = $this->parser->parse('<Alert title="Heads up!" description="You can add components." />');

        self::assertCount(1, $tags);
        self::assertSame(TagType::SelfClosing, $tags[0]->type);
        self::assertCount(2, $tags[0]->attributes);
        self::assertSame('title', $tags[0]->attributes[0]->name);
        self::assertSame('Heads up!', $tags[0]->attributes[0]->value);
        self::assertSame('description', $tags[0]->attributes[1]->name);
        self::assertSame('You can add components.', $tags[0]->attributes[1]->value);
    }

    #[Test]
    public function it_parses_boolean_attribute(): void
    {
        $tags = $this->parser->parse('<Checkbox id="terms" checked />');

        self::assertCount(1, $tags);
        self::assertCount(2, $tags[0]->attributes);
        self::assertSame('id', $tags[0]->attributes[0]->name);
        self::assertSame('terms', $tags[0]->attributes[0]->value);
        self::assertSame('checked', $tags[0]->attributes[1]->name);
        self::assertTrue($tags[0]->attributes[1]->value);
    }

    #[Test]
    public function it_parses_hyphenated_attribute_names(): void
    {
        $tags = $this->parser->parse('<Alert aria-label="notification" x-data="{}" />');

        self::assertCount(1, $tags);
        self::assertCount(2, $tags[0]->attributes);
        self::assertSame('aria-label', $tags[0]->attributes[0]->name);
        self::assertSame('notification', $tags[0]->attributes[0]->value);
        self::assertSame('x-data', $tags[0]->attributes[1]->name);
        self::assertSame('{}', $tags[0]->attributes[1]->value);
    }

    #[Test]
    public function it_parses_opening_tag(): void
    {
        $tags = $this->parser->parse('<Dialog title="Edit">content</Dialog>');

        self::assertCount(2, $tags);

        self::assertSame('Dialog', $tags[0]->name);
        self::assertSame(TagType::Opening, $tags[0]->type);
        self::assertCount(1, $tags[0]->attributes);
        self::assertSame('title', $tags[0]->attributes[0]->name);
        self::assertSame('Edit', $tags[0]->attributes[0]->value);

        self::assertSame('Dialog', $tags[1]->name);
        self::assertSame(TagType::Closing, $tags[1]->type);
        self::assertSame([], $tags[1]->attributes);
    }

    #[Test]
    public function it_handles_greater_than_inside_quoted_attribute(): void
    {
        $source = '<Button class=\'x-on:click="if (count > 5) alert()"\' />';
        $tags = $this->parser->parse($source);

        self::assertCount(1, $tags);
        self::assertSame(TagType::SelfClosing, $tags[0]->type);
        self::assertSame('class', $tags[0]->attributes[0]->name);
        self::assertSame('x-on:click="if (count > 5) alert()"', $tags[0]->attributes[0]->value);
    }

    #[Test]
    public function it_handles_multiline_tags(): void
    {
        $source = "<Alert\n    title=\"Heads up!\"\n    description=\"Content\"\n/>";
        $tags = $this->parser->parse($source);

        self::assertCount(1, $tags);
        self::assertSame('Alert', $tags[0]->name);
        self::assertSame(TagType::SelfClosing, $tags[0]->type);
        self::assertCount(2, $tags[0]->attributes);
    }

    #[Test]
    public function it_skips_tags_inside_twig_comments(): void
    {
        $tags = $this->parser->parse('{# <Alert title="test" /> #}');

        self::assertSame([], $tags);
    }

    #[Test]
    public function it_skips_tags_inside_verbatim_blocks(): void
    {
        $tags = $this->parser->parse('{% verbatim %}<Alert title="test" />{% endverbatim %}');

        self::assertSame([], $tags);
    }

    #[Test]
    public function it_parses_tags_outside_comments_but_skips_inside(): void
    {
        $source = '<Button />{# <Alert /> #}<Input />';
        $tags = $this->parser->parse($source);

        self::assertCount(2, $tags);
        self::assertSame('Button', $tags[0]->name);
        self::assertSame('Input', $tags[1]->name);
    }

    #[Test]
    public function it_handles_nested_same_name_tags(): void
    {
        $source = '<Card><Card>Inner</Card></Card>';
        $tags = $this->parser->parse($source);

        self::assertCount(4, $tags);
        self::assertSame('Card', $tags[0]->name);
        self::assertSame(TagType::Opening, $tags[0]->type);
        self::assertSame('Card', $tags[1]->name);
        self::assertSame(TagType::Opening, $tags[1]->type);
        self::assertSame('Card', $tags[2]->name);
        self::assertSame(TagType::Closing, $tags[2]->type);
        self::assertSame('Card', $tags[3]->name);
        self::assertSame(TagType::Closing, $tags[3]->type);
    }

    #[Test]
    public function it_ignores_lowercase_html_tags(): void
    {
        $tags = $this->parser->parse('<div class="test"><span>text</span></div>');

        self::assertSame([], $tags);
    }

    #[Test]
    public function it_returns_empty_for_source_without_component_tags(): void
    {
        $tags = $this->parser->parse('<div>{{ include("template.html.twig") }}</div>');

        self::assertSame([], $tags);
    }

    #[Test]
    public function it_parses_single_quoted_attribute_values(): void
    {
        $tags = $this->parser->parse("<Alert title='Hello' />");

        self::assertCount(1, $tags);
        self::assertSame('Hello', $tags[0]->attributes[0]->value);
    }

    #[Test]
    public function it_parses_compound_block_tag(): void
    {
        $source = '<BlockTrigger>content</BlockTrigger>';
        $tags = $this->parser->parse($source);

        self::assertCount(2, $tags);
        self::assertSame('BlockTrigger', $tags[0]->name);
        self::assertSame(TagType::Opening, $tags[0]->type);
        self::assertSame([], $tags[0]->attributes);
        self::assertSame('BlockTrigger', $tags[1]->name);
        self::assertSame(TagType::Closing, $tags[1]->type);
    }

    // --- US: Expression-valued attributes ---

    #[Test]
    public function it_captures_expression_value_verbatim(): void
    {
        $tags = $this->parser->parse('<Alert title="{{ alertTitle }}" />');

        self::assertCount(1, $tags);
        self::assertSame('title', $tags[0]->attributes[0]->name);
        self::assertSame('{{ alertTitle }}', $tags[0]->attributes[0]->value);
    }

    #[Test]
    public function it_captures_expression_with_filter(): void
    {
        $tags = $this->parser->parse('<Badge count="{{ items|length }}" />');

        self::assertCount(1, $tags);
        self::assertSame('count', $tags[0]->attributes[0]->name);
        self::assertSame('{{ items|length }}', $tags[0]->attributes[0]->value);
    }

    #[Test]
    public function it_captures_mixed_static_and_expression_value(): void
    {
        $tags = $this->parser->parse('<Card title="Hello {{ name }}" />');

        self::assertCount(1, $tags);
        self::assertSame('title', $tags[0]->attributes[0]->name);
        self::assertSame('Hello {{ name }}', $tags[0]->attributes[0]->value);
    }

    // --- US: Conditional attributes ---

    #[Test]
    public function it_parses_conditional_attribute_with_if(): void
    {
        $tags = $this->parser->parse('<Button {% if cond %}variant="primary"{% endif %} />');

        self::assertCount(1, $tags);
        self::assertCount(1, $tags[0]->attributes);
        self::assertSame('variant', $tags[0]->attributes[0]->name);
        self::assertSame('primary', $tags[0]->attributes[0]->value);
        self::assertSame('cond', $tags[0]->attributes[0]->condition);
        self::assertNull($tags[0]->attributes[0]->elseValue);
    }

    #[Test]
    public function it_parses_conditional_attribute_with_if_else(): void
    {
        $tags = $this->parser->parse('<Button {% if cond %}variant="primary"{% else %}variant="secondary"{% endif %} />');

        self::assertCount(1, $tags);
        self::assertCount(1, $tags[0]->attributes);
        self::assertSame('variant', $tags[0]->attributes[0]->name);
        self::assertSame('primary', $tags[0]->attributes[0]->value);
        self::assertSame('cond', $tags[0]->attributes[0]->condition);
        self::assertSame('secondary', $tags[0]->attributes[0]->elseValue);
    }

    #[Test]
    public function it_parses_conditional_boolean_attribute(): void
    {
        $tags = $this->parser->parse('<Input {% if cond %}checked{% endif %} />');

        self::assertCount(1, $tags);
        self::assertCount(1, $tags[0]->attributes);
        self::assertSame('checked', $tags[0]->attributes[0]->name);
        self::assertTrue($tags[0]->attributes[0]->value);
        self::assertSame('cond', $tags[0]->attributes[0]->condition);
    }

    #[Test]
    public function it_parses_multiple_attrs_in_single_conditional(): void
    {
        $tags = $this->parser->parse('<Button {% if cond %}a="1" b="2"{% endif %} />');

        self::assertCount(1, $tags);
        self::assertCount(2, $tags[0]->attributes);
        self::assertSame('a', $tags[0]->attributes[0]->name);
        self::assertSame('1', $tags[0]->attributes[0]->value);
        self::assertSame('cond', $tags[0]->attributes[0]->condition);
        self::assertSame('b', $tags[0]->attributes[1]->name);
        self::assertSame('2', $tags[0]->attributes[1]->value);
        self::assertSame('cond', $tags[0]->attributes[1]->condition);
    }

    #[Test]
    public function it_parses_mixed_static_and_conditional_attrs(): void
    {
        $tags = $this->parser->parse('<Button variant="outline" {% if c %}size="lg"{% endif %} />');

        self::assertCount(1, $tags);
        self::assertCount(2, $tags[0]->attributes);

        self::assertSame('variant', $tags[0]->attributes[0]->name);
        self::assertSame('outline', $tags[0]->attributes[0]->value);
        self::assertNull($tags[0]->attributes[0]->condition);

        self::assertSame('size', $tags[0]->attributes[1]->name);
        self::assertSame('lg', $tags[0]->attributes[1]->value);
        self::assertSame('c', $tags[0]->attributes[1]->condition);
    }
}
