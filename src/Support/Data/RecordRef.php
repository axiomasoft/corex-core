<?php

declare(strict_types=1);

namespace CoreX\Support\Data;

use InvalidArgumentException;

final readonly class RecordRef
{
    public function __construct(
        public string $entity,
        public string $id,
    ) {
        if (preg_match('/\\A[a-z][a-z0-9._-]{0,127}\\z/D', $entity) !== 1) {
            throw new InvalidArgumentException('Record entity must be a registered opaque handle.');
        }

        if ($id === '' || strlen($id) > 128 || preg_match('/[\\x00-\\x1f\\x7f]/', $id) === 1) {
            throw new InvalidArgumentException('Record identifier must be a bounded opaque value.');
        }
    }
}
