<?php

declare(strict_types=1);

namespace CoreX\Views\Internal;

use CoreX\Contracts\ViewSchemaProvider;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewSchema;
use LogicException;

/** @internal */
final class UnavailableViewSchemaProvider implements ViewSchemaProvider
{
    public function schema(ViewContext $context, string $entityHandle): ViewSchema
    {
        throw new LogicException('No saved-view schema provider is configured.');
    }
}
