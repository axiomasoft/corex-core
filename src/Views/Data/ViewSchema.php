<?php

declare(strict_types=1);

namespace CoreX\Views\Data;

final readonly class ViewSchema
{
    /** @param list<ViewField> $fields */
    public function __construct(public string $version, public string $entityHandle, public array $fields) {}
}
