<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewSchema;

interface ViewSchemaProvider
{
    public function schema(ViewContext $context, string $entityHandle): ViewSchema;
}
