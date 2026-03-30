<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Preprocessor;

use InvalidArgumentException;
use OpenSolid\SugarTwig\Parser\ComponentTagParser;
use OpenSolid\SugarTwig\Parser\ParsedAttribute;
use OpenSolid\SugarTwig\Parser\ParsedTag;
use OpenSolid\SugarTwig\Parser\TagType;

final readonly class ComponentPreprocessor
{
    public function __construct(
        private ComponentTagParser $parser,
        private ComponentNameResolver $resolver,
    ) {
    }

    /**
     * Transforms <PascalCase> tags into standard Twig include/embed syntax.
     * Returns source unchanged if no component tags found.
     *
     * @throws InvalidArgumentException when a tag has both `content` attribute and children
     */
    public function process(string $source): string
    {
        // Fast-path: skip if no uppercase tags found
        if (!preg_match('/<[A-Z]/', $source)) {
            return $source;
        }

        $tags = $this->parser->parse($source);

        if ([] === $tags) {
            return $source;
        }

        return $this->processSegment($source, $tags, 0);
    }

    /**
     * Recursively processes a source segment by building a tree of top-level
     * component nodes and transforming them. Inner components are transformed
     * first via recursion on children content.
     *
     * @param list<ParsedTag> $tags       Tags within this segment (offsets are absolute)
     * @param int             $baseOffset Offset to subtract from tag positions to get positions relative to $source
     */
    private function processSegment(string $source, array $tags, int $baseOffset): string
    {
        // Build top-level nodes from these tags
        $nodes = $this->buildTopLevelNodes($tags, $baseOffset);

        if ([] === $nodes) {
            return $source;
        }

        // Process right-to-left to maintain byte offsets
        $nodes = array_reverse($nodes);

        foreach ($nodes as $node) {
            $replacement = $this->renderNode($source, $node);
            $source = substr_replace($source, $replacement, $node['start'], $node['end'] - $node['start']);
        }

        return $source;
    }

    /**
     * Builds top-level (non-nested) component nodes from a flat list of tags.
     * Nested tags are stored within their parent node for recursive processing.
     *
     * @param list<ParsedTag> $tags
     * @param int             $baseOffset Offset to subtract from tag positions
     *
     * @return list<array<string, mixed>>
     */
    private function buildTopLevelNodes(array $tags, int $baseOffset): array
    {
        $nodes = [];
        $depth = 0;
        $currentOpen = null;
        $nestedTags = [];

        foreach ($tags as $tag) {
            if (0 === $depth) {
                if (TagType::SelfClosing === $tag->type) {
                    $nodes[] = [
                        'type' => 'self-closing',
                        'start' => $tag->startOffset - $baseOffset,
                        'end' => $tag->endOffset - $baseOffset,
                        'name' => $tag->name,
                        'attributes' => $tag->attributes,
                    ];
                } elseif (TagType::Opening === $tag->type) {
                    $currentOpen = $tag;
                    $nestedTags = [];
                    $depth = 1;
                }
            // Closing tags at depth 0 shouldn't happen; ignore
            } else {
                if (TagType::Opening === $tag->type) {
                    $nestedTags[] = $tag;
                    ++$depth;
                } elseif (TagType::Closing === $tag->type) {
                    --$depth;
                    if (0 === $depth && $currentOpen->name === $tag->name) {
                        $childrenAbsStart = $currentOpen->endOffset;
                        $nodes[] = [
                            'type' => 'paired',
                            'start' => $currentOpen->startOffset - $baseOffset,
                            'end' => $tag->endOffset - $baseOffset,
                            'name' => $currentOpen->name,
                            'attributes' => $currentOpen->attributes,
                            'openEnd' => $currentOpen->endOffset - $baseOffset,
                            'closeStart' => $tag->startOffset - $baseOffset,
                            'childrenAbsStart' => $childrenAbsStart,
                            'nestedTags' => $nestedTags,
                        ];
                        $currentOpen = null;
                        $nestedTags = [];
                    } else {
                        $nestedTags[] = $tag;
                    }
                } elseif (TagType::SelfClosing === $tag->type) {
                    $nestedTags[] = $tag;
                }
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderNode(string $source, array $node): string
    {
        $name = $node['name'];
        /** @var list<ParsedAttribute> $attributes */
        $attributes = $node['attributes'];

        // Self-closing compound block tags: preserve raw tag for extractBlocks()
        if ('self-closing' === $node['type'] && $this->isCompoundBlockTag($name)) {
            return substr($source, $node['start'], $node['end'] - $node['start']);
        }

        if ('self-closing' === $node['type']) {
            $templatePath = $this->resolver->resolve($name);
            $attrsStr = $this->serializeAttributes($attributes);

            return '' !== $attrsStr
                ? "{{ include('{$templatePath}', {{$attrsStr}}, with_context=false) }}"
                : "{{ include('{$templatePath}', {}, with_context=false) }}";
        }

        // Paired compound block tags (e.g. <BlockTrigger>...</BlockTrigger>): recursively
        // process inner content but preserve the tag wrapper so extractBlocks() can find them
        if ($this->isCompoundBlockTag($name)) {
            $childrenStart = $node['openEnd'];
            $childrenEnd = $node['closeStart'];
            $childrenSource = substr($source, $childrenStart, $childrenEnd - $childrenStart);

            /** @var list<ParsedTag> $nestedTags */
            $nestedTags = $node['nestedTags'];
            if ([] !== $nestedTags) {
                $childrenSource = $this->processSegment($childrenSource, $nestedTags, $node['childrenAbsStart']);
            }

            // Reconstruct the Block tag with processed inner content
            $openTag = substr($source, $node['start'], $childrenStart - $node['start']);
            $closeTag = substr($source, $childrenEnd, $node['end'] - $childrenEnd);

            return $openTag.$childrenSource.$closeTag;
        }

        // Validate: reject content attribute + children
        foreach ($attributes as $attr) {
            if ('content' === $attr->name) {
                throw new InvalidArgumentException(\sprintf('Component <%s> cannot have both a "content" attribute and children. Use either the attribute or child content, not both.', $name));
            }
        }

        $templatePath = $this->resolver->resolve($name);
        $attrsStr = $this->serializeAttributes($attributes);

        // Get raw children source
        $childrenStart = $node['openEnd'];
        $childrenEnd = $node['closeStart'];
        $childrenSource = substr($source, $childrenStart, $childrenEnd - $childrenStart);

        // Recursively process nested component tags within children
        /** @var list<ParsedTag> $nestedTags */
        $nestedTags = $node['nestedTags'];

        if ([] !== $nestedTags) {
            $childrenSource = $this->processSegment($childrenSource, $nestedTags, $node['childrenAbsStart']);
        }

        // Extract compound block tags from the processed children
        $blocks = $this->extractBlocks($childrenSource);

        $withClause = '' !== $attrsStr ? " with {{$attrsStr}}" : '';
        $result = "{% embed '{$templatePath}'{$withClause} only %}";

        if ([] !== $blocks['named']) {
            if ('' !== $blocks['default']) {
                $result .= "{% block content %}{$blocks['default']}{% endblock %}";
            }
            foreach ($blocks['named'] as $blockName => $blockContent) {
                $result .= "{% block {$blockName} %}{$blockContent}{% endblock %}";
            }
        } else {
            $result .= "{% block content %}{$childrenSource}{% endblock %}";
        }

        $result .= '{% endembed %}';

        return $result;
    }

    /**
     * Extracts compound block tags (e.g. <BlockTrigger>...</BlockTrigger>) from children source.
     * When a compound block tag has attributes, wraps content in a <div> carrying those attributes.
     *
     * @return array{named: array<string, string>, default: string}
     */
    private function extractBlocks(string $children): array
    {
        $tags = $this->parser->parse($children);

        $blockPairs = [];
        $blockStack = [];

        foreach ($tags as $tag) {
            if ($this->isCompoundBlockTag($tag->name) && TagType::Opening === $tag->type) {
                $blockStack[] = $tag;
            } elseif ($this->isCompoundBlockTag($tag->name) && TagType::Closing === $tag->type && [] !== $blockStack) {
                // Match by exact tag name (e.g. BlockTrigger open matches BlockTrigger close)
                for ($i = \count($blockStack) - 1; $i >= 0; --$i) {
                    if ($blockStack[$i]->name === $tag->name) {
                        $openTag = $blockStack[$i];
                        array_splice($blockStack, $i, 1);
                        $blockPairs[] = ['open' => $openTag, 'close' => $tag];
                        break;
                    }
                }
            } elseif ($this->isCompoundBlockTag($tag->name) && TagType::SelfClosing === $tag->type) {
                $blockPairs[] = ['open' => $tag, 'close' => null];
            }
        }

        if ([] === $blockPairs) {
            return ['named' => [], 'default' => ''];
        }

        $named = [];
        $remaining = $children;

        // Process block pairs in reverse order to maintain offsets for removal
        $blockPairsSorted = $blockPairs;
        usort($blockPairsSorted, static fn (array $a, array $b): int => $b['open']->startOffset <=> $a['open']->startOffset);

        foreach ($blockPairsSorted as $pair) {
            $blockName = $this->deriveBlockName($pair['open']->name);

            if (null === $pair['close']) {
                // Self-closing compound block tag
                $blockContent = $this->wrapWithDivIfAttributes($pair['open']->attributes, '');
                $named[$blockName] = $blockContent;
                $remaining = substr_replace($remaining, '', $pair['open']->startOffset, $pair['open']->endOffset - $pair['open']->startOffset);
                continue;
            }

            $contentStart = $pair['open']->endOffset;
            $contentEnd = $pair['close']->startOffset;
            $blockContent = substr($children, $contentStart, $contentEnd - $contentStart);

            $named[$blockName] = $this->wrapWithDivIfAttributes($pair['open']->attributes, $blockContent);

            // Remove block from remaining content
            $remaining = substr_replace($remaining, '', $pair['open']->startOffset, $pair['close']->endOffset - $pair['open']->startOffset);
        }

        // Restore original order
        $named = array_reverse($named, true);

        return ['named' => $named, 'default' => trim($remaining)];
    }

    /**
     * @param list<ParsedAttribute> $attributes
     */
    private function wrapWithDivIfAttributes(array $attributes, string $content): string
    {
        if ([] === $attributes) {
            return $content;
        }

        $parts = [];
        foreach ($attributes as $attr) {
            if (true === $attr->value) {
                $parts[] = $attr->name;
            } else {
                $parts[] = "{$attr->name}=\"{$attr->value}\"";
            }
        }

        $attrsStr = implode(' ', $parts);

        return "<div {$attrsStr}>{$content}</div>";
    }

    /**
     * @param list<ParsedAttribute> $attributes
     */
    private function serializeAttributes(array $attributes): string
    {
        if ([] === $attributes) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $attr) {
            if ($attr->isDynamic) {
                // Dynamic attribute: {{ expr }} in attribute zone → computed key
                $parts[] = "({$attr->name}): true";
                continue;
            }

            $key = $this->needsQuoting($attr->name)
                ? "'{$attr->name}'"
                : $attr->name;

            $serializedValue = true === $attr->value ? 'true' : $this->serializeValue($attr->value);

            if (null !== $attr->condition) {
                $fallback = null === $attr->elseValue
                    ? 'null'
                    : (true === $attr->elseValue ? 'true' : $this->serializeValue($attr->elseValue));
                $parts[] = "{$key}: {$attr->condition} ? {$serializedValue} : {$fallback}";
            } else {
                $parts[] = "{$key}: {$serializedValue}";
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Serializes an attribute value string, detecting {{ }} expression delimiters.
     *
     * - Pure expression "{{ expr }}" → expr
     * - Mixed content "text {{ expr }}" → 'text ' ~ expr
     * - Static "text" → 'text'
     */
    private function serializeValue(string $value): string
    {
        // No expression delimiters — static value
        if (!str_contains($value, '{{')) {
            $escaped = str_replace("'", "\\'", $value);

            return "'{$escaped}'";
        }

        // Split on {{ }} boundaries
        $segments = preg_split('/(\{\{.+?\}\})/', $value, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);

        // Pure expression: single segment that is {{ expr }}
        if (1 === \count($segments) && preg_match('/^\{\{\s*(.+?)\s*\}\}$/', $segments[0], $m)) {
            return $m[1];
        }

        // Mixed content: concatenate static and expression parts
        $parts = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{\{\s*(.+?)\s*\}\}$/', $segment, $m)) {
                $parts[] = $m[1];
            } else {
                $escaped = str_replace("'", "\\'", $segment);
                $parts[] = "'{$escaped}'";
            }
        }

        return implode(' ~ ', $parts);
    }

    private function needsQuoting(string $name): bool
    {
        return \strlen($name) !== strspn($name, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_');
    }

    private function isCompoundBlockTag(string $name): bool
    {
        return \strlen($name) > 5
            && str_starts_with($name, 'Block')
            && ctype_upper($name[5]);
    }

    private function deriveBlockName(string $tagName): string
    {
        $suffix = substr($tagName, 5);
        $result = strtolower($suffix[0]);

        for ($i = 1, $len = \strlen($suffix); $i < $len; ++$i) {
            if (ctype_upper($suffix[$i])) {
                $result .= '_';
            }
            $result .= strtolower($suffix[$i]);
        }

        return $result;
    }
}
