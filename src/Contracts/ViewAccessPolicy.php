<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Views\Data\ViewAction;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewSubject;

interface ViewAccessPolicy
{
    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool;
}
