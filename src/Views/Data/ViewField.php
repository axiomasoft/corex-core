<?php

declare(strict_types=1);

namespace CoreX\Views\Data;

final readonly class ViewField
{
    /** @param list<string> $operators */
    public function __construct(public string $key, public string $type, public array $operators, public bool $visible, public bool $filterable, public bool $sortable, public bool $projectable, public string $mappingKey) {}
}
