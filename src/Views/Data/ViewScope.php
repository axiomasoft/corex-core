<?php

declare(strict_types=1);

namespace CoreX\Views\Data;

use InvalidArgumentException;

final readonly class ViewScope
{
    public function __construct(public string $entityHandle, public ?string $workspaceId, public ?string $ownerKind, public ?string $ownerId)
    {
        if ($entityHandle === '' || ($ownerKind === null) !== ($ownerId === null)) {
            throw new InvalidArgumentException('Invalid view scope.');
        }
    }
}
