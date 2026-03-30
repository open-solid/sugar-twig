<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Parser;

final readonly class ComponentTagParser
{
    private const int STATE_TEXT = 0;
    private const int STATE_COMMENT = 1;
    private const int STATE_VERBATIM = 2;
    private const int STATE_TAG_NAME = 3;
    private const int STATE_TAG_ATTRS = 4;
    private const int STATE_TAG_CLOSE = 5;

    /**
     * Scans source and returns all <PascalCase> tags found.
     *
     * @return list<ParsedTag>
     */
    public function parse(string $source): array
    {
        $tags = [];
        $len = \strlen($source);
        $state = self::STATE_TEXT;
        $i = 0;

        // Tag accumulation state
        $tagStart = 0;
        $tagNameStart = 0;
        $tagName = '';
        $isClosing = false;
        /** @var list<ParsedAttribute> $attributes */
        $attributes = [];

        // Attribute parsing state
        $attrNameStart = 0;
        $attrName = '';
        $attrValueStart = 0;
        $inAttrName = false;
        $inAttrValue = false;
        $attrQuote = '';

        // Conditional attribute state ({% if %}...{% endif %} in attribute zone)
        $inConditional = false;
        $conditionExpr = '';
        $inElseBranch = false;

        while ($i < $len) {
            $ch = $source[$i];

            switch ($state) {
                case self::STATE_TEXT:
                    // Check for Twig comment start: {#
                    if ('{' === $ch && $i + 1 < $len && '#' === $source[$i + 1]) {
                        $state = self::STATE_COMMENT;
                        $i += 2;
                        break;
                    }

                    // Check for verbatim block: {% verbatim %}
                    if ('{' === $ch && $i + 1 < $len && '%' === $source[$i + 1]) {
                        $blockContent = $this->extractTwigBlock($source, $i);
                        if ('verbatim' === $blockContent) {
                            $state = self::STATE_VERBATIM;
                            // Skip past {% verbatim %}
                            $closingPos = strpos($source, '%}', $i + 2);
                            $i = false !== $closingPos ? $closingPos + 2 : $i + 2;
                            break;
                        }
                    }

                    // Check for closing tag: </[A-Z]
                    if ('<' === $ch && $i + 1 < $len && '/' === $source[$i + 1] && $i + 2 < $len && ctype_upper($source[$i + 2])) {
                        $tagStart = $i;
                        $tagNameStart = $i + 2;
                        $isClosing = true;
                        $state = self::STATE_TAG_CLOSE;
                        $i += 2; // skip </
                        break;
                    }

                    // Check for opening tag: <[A-Z]
                    if ('<' === $ch && $i + 1 < $len && ctype_upper($source[$i + 1])) {
                        $tagStart = $i;
                        $tagNameStart = $i + 1;
                        $isClosing = false;
                        $attributes = [];
                        $state = self::STATE_TAG_NAME;
                        ++$i; // skip <
                        break;
                    }

                    ++$i;
                    break;

                case self::STATE_COMMENT:
                    // Look for #}
                    if ('#' === $ch && $i + 1 < $len && '}' === $source[$i + 1]) {
                        $state = self::STATE_TEXT;
                        $i += 2;
                    } else {
                        ++$i;
                    }
                    break;

                case self::STATE_VERBATIM:
                    // Look for {% endverbatim %}
                    if ('{' === $ch && $i + 1 < $len && '%' === $source[$i + 1]) {
                        $blockContent = $this->extractTwigBlock($source, $i);
                        if ('endverbatim' === $blockContent) {
                            $closingPos = strpos($source, '%}', $i + 2);
                            $i = false !== $closingPos ? $closingPos + 2 : $i + 2;
                            $state = self::STATE_TEXT;
                            break;
                        }
                    }
                    ++$i;
                    break;

                case self::STATE_TAG_NAME:
                    if (ctype_alpha($ch)) {
                        ++$i;
                    } elseif (' ' === $ch || "\t" === $ch || "\n" === $ch || "\r" === $ch) {
                        $tagName = substr($source, $tagNameStart, $i - $tagNameStart);
                        $state = self::STATE_TAG_ATTRS;
                        $inAttrName = false;
                        $inAttrValue = false;
                        ++$i;
                    } elseif ('/' === $ch && $i + 1 < $len && '>' === $source[$i + 1]) {
                        // Self-closing: />
                        $tagName = substr($source, $tagNameStart, $i - $tagNameStart);
                        $tags[] = new ParsedTag($tagStart, $i + 2, $tagName, TagType::SelfClosing, $attributes);
                        $state = self::STATE_TEXT;
                        $i += 2;
                    } elseif ('>' === $ch) {
                        // Opening tag with no attributes
                        $tagName = substr($source, $tagNameStart, $i - $tagNameStart);
                        $tags[] = new ParsedTag($tagStart, $i + 1, $tagName, TagType::Opening, $attributes);
                        $state = self::STATE_TEXT;
                        ++$i;
                    } else {
                        ++$i;
                    }
                    break;

                case self::STATE_TAG_ATTRS:
                    if ($inAttrValue) {
                        if ('' !== $attrQuote) {
                            // Inside quoted value
                            if ($ch === $attrQuote) {
                                // End of quoted value
                                $attrName = substr($source, $attrNameStart, \strlen($attrName) > 0 ? \strlen($attrName) : $i - $attrNameStart);
                                $this->addParsedAttribute($attributes, $attrName, substr($source, $attrValueStart, $i - $attrValueStart), $inConditional, $inElseBranch, $conditionExpr);
                                $attrName = '';
                                $inAttrValue = false;
                                $inAttrName = false;
                                $attrQuote = '';
                                ++$i;
                            } else {
                                ++$i;
                            }
                        }
                        break;
                    }

                    if ($inAttrName) {
                        // Twig tag encountered while building attribute name
                        if ('{' === $ch && $i + 1 < $len && '%' === $source[$i + 1]) {
                            $currentName = substr($source, $attrNameStart, $i - $attrNameStart);
                            if ('' !== $currentName) {
                                $this->addParsedAttribute($attributes, $currentName, true, $inConditional, $inElseBranch, $conditionExpr);
                            }
                            $attrName = '';
                            $inAttrName = false;
                            // Re-enter STATE_TAG_ATTRS on next iteration to handle {%
                            break;
                        }

                        if ('=' === $ch) {
                            // Finalize attr name and start value
                            $attrName = substr($source, $attrNameStart, $i - $attrNameStart);
                            $inAttrValue = true;
                            ++$i;
                            // Check for quote
                            if ($i < $len && ('"' === $source[$i] || '\'' === $source[$i])) {
                                $attrQuote = $source[$i];
                                ++$i;
                                $attrValueStart = $i;
                            }
                        } elseif (' ' === $ch || "\t" === $ch || "\n" === $ch || "\r" === $ch) {
                            // Boolean attribute (no value)
                            $this->addParsedAttribute($attributes, substr($source, $attrNameStart, $i - $attrNameStart), true, $inConditional, $inElseBranch, $conditionExpr);
                            $attrName = '';
                            $inAttrName = false;
                            ++$i;
                        } elseif ('/' === $ch && $i + 1 < $len && '>' === $source[$i + 1]) {
                            // Boolean attribute followed by />
                            $this->addParsedAttribute($attributes, substr($source, $attrNameStart, $i - $attrNameStart), true, $inConditional, $inElseBranch, $conditionExpr);
                            $tags[] = new ParsedTag($tagStart, $i + 2, $tagName, TagType::SelfClosing, $attributes);
                            $state = self::STATE_TEXT;
                            $i += 2;
                        } elseif ('>' === $ch) {
                            // Boolean attribute followed by >
                            $this->addParsedAttribute($attributes, substr($source, $attrNameStart, $i - $attrNameStart), true, $inConditional, $inElseBranch, $conditionExpr);
                            $tags[] = new ParsedTag($tagStart, $i + 1, $tagName, TagType::Opening, $attributes);
                            $state = self::STATE_TEXT;
                            ++$i;
                        } else {
                            ++$i;
                        }
                        break;
                    }

                    // Not in attr name or value — check for Twig print: {{ expr }}
                    if ('{' === $ch && $i + 1 < $len && '{' === $source[$i + 1]) {
                        $closingPos = strpos($source, '}}', $i + 2);
                        if (false !== $closingPos) {
                            $expr = trim(substr($source, $i + 2, $closingPos - $i - 2));
                            if ('' !== $expr) {
                                $attributes[] = new ParsedAttribute($expr, true, isDynamic: true);
                            }
                            $i = $closingPos + 2;
                        } else {
                            $i += 2;
                        }
                        break;
                    }

                    // Check for Twig tags: {% if/else/endif %}
                    if ('{' === $ch && $i + 1 < $len && '%' === $source[$i + 1]) {
                        $twigTag = $this->extractTwigBlock($source, $i);
                        $closingPos = strpos($source, '%}', $i + 2);

                        if ('if' === $twigTag && false !== $closingPos) {
                            $inConditional = true;
                            $inElseBranch = false;
                            $conditionExpr = trim(substr($source, $i + 2, $closingPos - $i - 2));
                            // Remove "if " prefix and surrounding whitespace
                            $conditionExpr = trim(substr($conditionExpr, 2));
                            $i = $closingPos + 2;
                        } elseif ('else' === $twigTag && false !== $closingPos) {
                            $inElseBranch = true;
                            $i = $closingPos + 2;
                        } elseif ('endif' === $twigTag && false !== $closingPos) {
                            $inConditional = false;
                            $inElseBranch = false;
                            $conditionExpr = '';
                            $i = $closingPos + 2;
                        } else {
                            // Unknown Twig tag — skip past %}
                            $i = false !== $closingPos ? $closingPos + 2 : $i + 2;
                        }
                        break;
                    }

                    if ('/' === $ch && $i + 1 < $len && '>' === $source[$i + 1]) {
                        $tags[] = new ParsedTag($tagStart, $i + 2, $tagName, TagType::SelfClosing, $attributes);
                        $state = self::STATE_TEXT;
                        $i += 2;
                    } elseif ('>' === $ch) {
                        $tags[] = new ParsedTag($tagStart, $i + 1, $tagName, TagType::Opening, $attributes);
                        $state = self::STATE_TEXT;
                        ++$i;
                    } elseif (' ' !== $ch && "\t" !== $ch && "\n" !== $ch && "\r" !== $ch) {
                        // Start of attribute name
                        $attrNameStart = $i;
                        $attrName = '';
                        $inAttrName = true;
                        ++$i;
                    } else {
                        ++$i;
                    }
                    break;

                case self::STATE_TAG_CLOSE:
                    if (ctype_alpha($ch)) {
                        ++$i;
                    } elseif ('>' === $ch) {
                        $tagName = substr($source, $tagNameStart, $i - $tagNameStart);
                        $tags[] = new ParsedTag($tagStart, $i + 1, $tagName, TagType::Closing);
                        $state = self::STATE_TEXT;
                        ++$i;
                    } else {
                        ++$i;
                    }
                    break;
            }
        }

        return $tags;
    }

    /**
     * Adds a parsed attribute to the list, handling conditional ({% if %}) context.
     *
     * @param list<ParsedAttribute> $attributes
     */
    private function addParsedAttribute(array &$attributes, string $name, string|true $value, bool $inConditional, bool $inElseBranch, string $conditionExpr): void
    {
        if (!$inConditional) {
            $attributes[] = new ParsedAttribute($name, $value);

            return;
        }

        if (!$inElseBranch) {
            $attributes[] = new ParsedAttribute($name, $value, $conditionExpr);

            return;
        }

        // Else branch: find matching if-branch attribute by name and set elseValue
        foreach ($attributes as $idx => $existing) {
            if ($existing->name === $name && $existing->condition === $conditionExpr) {
                $attributes[$idx] = new ParsedAttribute($existing->name, $existing->value, $existing->condition, $value);

                return;
            }
        }

        // No matching if-branch attribute — standalone else-branch
        $attributes[] = new ParsedAttribute($name, $value, $conditionExpr);
    }

    /**
     * Extracts the block name from a {% block_name %} tag at position $pos.
     */
    private function extractTwigBlock(string $source, int $pos): string
    {
        // Skip past {%
        $start = $pos + 2;
        $len = \strlen($source);

        // Skip whitespace
        while ($start < $len && (' ' === $source[$start] || "\t" === $source[$start])) {
            ++$start;
        }

        // Extract word
        $end = $start;
        while ($end < $len && ctype_alpha($source[$end])) {
            ++$end;
        }

        return substr($source, $start, $end - $start);
    }
}
