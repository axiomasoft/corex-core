<?php

declare(strict_types=1);

namespace CoreX\Views\Internal;

use CoreX\Contracts\ViewAccessPolicy;
use CoreX\Views\Data\ViewAction;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewSubject;

/** @internal */
final class DenyViewAccessPolicy implements ViewAccessPolicy
{
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
    {
        return false;
    }
}
