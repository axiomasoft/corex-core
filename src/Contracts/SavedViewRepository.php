<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Views\Data\SavedQuery;
use CoreX\Views\Data\SavedViewRef;
use CoreX\Views\Data\SaveViewInput;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewScope;

interface SavedViewRepository
{
    /** @return list<SavedViewRef> */
    public function viewsFor(ViewContext $context, ViewScope $scope): array;

    public function save(ViewContext $context, SaveViewInput $input): SavedViewRef;

    public function delete(ViewContext $context, string $viewId): void;

    public function setDefault(ViewContext $context, string $viewId): SavedViewRef;

    public function compile(ViewContext $context, string $viewId): SavedQuery;
}
