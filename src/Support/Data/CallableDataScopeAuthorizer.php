<?php

declare(strict_types=1);

namespace CoreX\Support\Data;

use Closure;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Data\DataScopeAccessDecision;

final readonly class CallableDataScopeAuthorizer implements DataScopeAuthorizer
{
    /**
     * @param  Closure(DataScope, string, string, list<string>, string): bool  $predicate
     */
    public function __construct(private Closure $predicate) {}

    public function authorize(
        DataScope $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ): DataScopeAccessDecision {
        $allowed = ($this->predicate)($scope, $entity, $subjectId, $fields, $action);

        return new DataScopeAccessDecision(
            allowed: $allowed,
            reasonCode: $allowed ? 'callable.allowed' : 'callable.denied',
        );
    }
}
