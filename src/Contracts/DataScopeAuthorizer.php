<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Data\DataScopeAccessDecision;
use CoreX\Support\Data\DataScope;

interface DataScopeAuthorizer
{
    /**
     * @param  list<string>  $fields
     */
    public function authorize(
        DataScope $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ): DataScopeAccessDecision;
}
