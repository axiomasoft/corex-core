<?php

declare(strict_types=1);

namespace CoreX\Views\Data;

final readonly class SavedQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array{field: string, direction: string}>  $sort
     * @param  list<string>  $projection
     */
    public function __construct(public string $schemaVersion, public array $filters, public array $sort, public array $projection, public ?string $groupBy) {}
}
