<?php

declare(strict_types=1);

namespace CoreX\Views\Data;

final readonly class ViewSubject
{
    public function __construct(public ViewScope $scope, public ?string $viewId) {}
}
