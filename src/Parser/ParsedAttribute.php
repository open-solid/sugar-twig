<?php

declare(strict_types=1);

namespace OpenSolid\SugarTwig\Parser;

final readonly class ParsedAttribute
{
    public function __construct(
        public string $name,
        public string|true $value,
        public ?string $condition = null,
        public string|true|null $elseValue = null,
        public bool $isDynamic = false,
    ) {
    }
}
