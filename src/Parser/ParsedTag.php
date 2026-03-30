<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Parser;

final readonly class ParsedTag
{
    /**
     * @param list<ParsedAttribute> $attributes
     */
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public string $name,
        public TagType $type,
        public array $attributes = [],
    ) {
    }
}
