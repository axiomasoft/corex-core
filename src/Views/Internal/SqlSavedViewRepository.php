<?php

declare(strict_types=1);

namespace CoreX\Views\Internal;

use CoreX\Contracts\SavedViewRepository;
use CoreX\Contracts\ViewAccessPolicy;
use CoreX\Contracts\ViewSchemaProvider;
use CoreX\Views\Data\SavedQuery;
use CoreX\Views\Data\SavedViewRef;
use CoreX\Views\Data\SaveViewInput;
use CoreX\Views\Data\ViewAction;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewScope;
use CoreX\Views\Data\ViewSubject;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** @internal */
final class SqlSavedViewRepository implements SavedViewRepository
{
    public function __construct(private readonly ViewSchemaProvider $schemas, private readonly ViewAccessPolicy $policy, private readonly ViewConfigValidator $validator) {}

    public function viewsFor(ViewContext $context, ViewScope $scope): array
    {
        $this->authorize($context, ViewAction::Read, $scope, null);

        return $this->db()->table($this->viewsTable())->where('entity_code', $scope->entityHandle)
            ->where('scope_key', $this->scopeKey($scope))->orderBy('position')->get()
            ->map(fn (object $row): SavedViewRef => $this->reference($row))->all();
    }

    public function save(ViewContext $context, SaveViewInput $input): SavedViewRef
    {
        $this->authorize($context, $input->id === null ? ViewAction::Create : ViewAction::Update, $input->scope, $input->id);
        $schema = $this->schemas->schema($context, $input->scope->entityHandle);
        $normalized = $this->validator->validate($input->config, $schema);

        if ($input->kind === '' || count($input->localizedName) === 0 || strlen(json_encode($input->config, JSON_THROW_ON_ERROR)) > 65_536) {
            throw new InvalidArgumentException('Invalid saved view input.');
        }

        return $this->db()->transaction(function () use ($context, $input, $schema, $normalized): SavedViewRef {
            $scopeKey = $this->scopeKey($input->scope);
            $this->lockScope($input->scope, $scopeKey);
            $table = $this->viewsTable();

            if ($input->id === null) {
                $id = (string) Str::uuid7();
                $isDefault = ! $this->db()->table($table)->where('entity_code', $input->scope->entityHandle)->where('scope_key', $scopeKey)->exists();
                $this->db()->table($table)->insert($this->payload($context, $input, $schema->version, $scopeKey, $id, '1', $isDefault, $normalized));

                return new SavedViewRef($id, $input->scope, $input->kind, '1', $isDefault);
            }
            $row = $this->db()->table($table)->where('id', $input->id)->lockForUpdate()->first();

            if ($row === null || $row->scope_key !== $scopeKey || (string) $row->revision !== $input->expectedRevision) {
                throw new RuntimeException('Saved view revision conflict.');
            }
            $revision = (string) ((int) $row->revision + 1);
            $payload = $this->payload($context, $input, $schema->version, $scopeKey, $input->id, $revision, (bool) $row->is_default, $normalized);
            unset($payload['created_at'], $payload['created_by']);
            $this->db()->table($table)->where('id', $input->id)->update($payload);

            return new SavedViewRef($input->id, $input->scope, $input->kind, $revision, (bool) $row->is_default);
        });
    }

    public function delete(ViewContext $context, string $viewId): void
    {
        $this->db()->transaction(function () use ($context, $viewId): void {
            $row = $this->db()->table($this->viewsTable())->where('id', $viewId)->lockForUpdate()->first();

            if ($row === null) {
                return;
            }
            $scope = $this->scopeFrom($row);
            $this->authorize($context, ViewAction::Delete, $scope, $viewId);
            $this->lockScope($scope, $row->scope_key);
            $this->db()->table($this->viewsTable())->where('id', $viewId)->delete();

            if ((bool) $row->is_default) {
                $replacement = $this->db()->table($this->viewsTable())->where('entity_code', $row->entity_code)->where('scope_key', $row->scope_key)->orderBy('position')->first();

                if ($replacement !== null) {
                    $this->db()->table($this->viewsTable())->where('id', $replacement->id)->update(['is_default' => true, 'default_scope_key' => $row->scope_key]);
                }
            }
        });
    }

    public function setDefault(ViewContext $context, string $viewId): SavedViewRef
    {
        return $this->db()->transaction(function () use ($context, $viewId): SavedViewRef {
            $row = $this->db()->table($this->viewsTable())->where('id', $viewId)->lockForUpdate()->first();

            if ($row === null) {
                throw new RuntimeException('Saved view not found.');
            }
            $scope = $this->scopeFrom($row);
            $this->authorize($context, ViewAction::SetDefault, $scope, $viewId);
            $this->lockScope($scope, $row->scope_key);
            $this->db()->table($this->viewsTable())->where('entity_code', $row->entity_code)->where('scope_key', $row->scope_key)->update(['is_default' => false, 'default_scope_key' => null]);
            $this->db()->table($this->viewsTable())->where('id', $viewId)->update(['is_default' => true, 'default_scope_key' => $row->scope_key]);

            return new SavedViewRef($row->id, $scope, $row->kind, (string) $row->revision, true);
        });
    }

    public function compile(ViewContext $context, string $viewId): SavedQuery
    {
        $row = $this->db()->table($this->viewsTable())->where('id', $viewId)->first();

        if ($row === null) {
            throw new RuntimeException('Saved view not found.');
        }
        $scope = $this->scopeFrom($row);
        $this->authorize($context, ViewAction::Read, $scope, $viewId);
        $schema = $this->schemas->schema($context, $scope->entityHandle);

        if ($schema->version !== $row->schema_version) {
            throw new RuntimeException('Unsupported saved view schema.');
        }
        $config = json_decode($row->config, true, flags: JSON_THROW_ON_ERROR);
        $validated = $this->validator->validate($config, $schema);

        return new SavedQuery($schema->version, $validated['filters'], $validated['sorts'], $validated['columns'], $validated['groupBy']);
    }

    /**
     * @param  array{filters: array<string,mixed>, sorts: list<array{field: string, direction: string}>, columns: list<string>, groupBy: string|null}  $normalized
     * @return array<string, mixed>
     */
    private function payload(ViewContext $context, SaveViewInput $input, string $schemaVersion, string $scopeKey, string $id, string $revision, bool $isDefault, array $normalized): array
    {
        $now = now();

        return ['id' => $id, 'entity_code' => $input->scope->entityHandle, 'workspace_id' => $input->scope->workspaceId, 'owner_kind' => $input->scope->ownerKind, 'owner_id' => $input->scope->ownerId, 'scope_key' => $scopeKey, 'kind' => $input->kind, 'name' => json_encode($input->localizedName, JSON_THROW_ON_ERROR), 'config' => json_encode($input->config, JSON_THROW_ON_ERROR), 'schema_version' => $schemaVersion, 'revision' => $revision, 'is_default' => $isDefault, 'default_scope_key' => $isDefault ? $scopeKey : null, 'position' => 0, 'created_by' => $context->actorId ?? $context->actorKind, 'created_at' => $now, 'updated_at' => $now];
    }

    private function authorize(ViewContext $context, ViewAction $action, ViewScope $scope, ?string $viewId): void
    {
        if (! $this->policy->allows($context, $action, new ViewSubject($scope, $viewId))) {
            throw new RuntimeException('Saved view action denied.');
        }
    }

    private function lockScope(ViewScope $scope, string $scopeKey): void
    {
        $this->db()->table($this->scopesTable())->updateOrInsert(['entity_code' => $scope->entityHandle, 'scope_key' => $scopeKey]);
        $this->db()->table($this->scopesTable())->where('entity_code', $scope->entityHandle)->where('scope_key', $scopeKey)->lockForUpdate()->first();
    }

    private function scopeKey(ViewScope $scope): string
    {
        return implode('', array_map(static fn (?string $part): string => $part === null ? 'N;' : strlen($part).':'.$part.';', [$scope->entityHandle, $scope->workspaceId, $scope->ownerKind, $scope->ownerId]));
    }

    private function scopeFrom(object $row): ViewScope
    {
        return new ViewScope($row->entity_code, $row->workspace_id, $row->owner_kind, $row->owner_id);
    }

    private function reference(object $row): SavedViewRef
    {
        return new SavedViewRef($row->id, $this->scopeFrom($row), $row->kind, (string) $row->revision, (bool) $row->is_default);
    }

    private function db(): ConnectionInterface
    {
        return DB::connection(config('corex.views.connection') ?: config('database.default'));
    }

    private function viewsTable(): string
    {
        return (string) config('corex.tables.saved_views', 'sys_saved_views');
    }

    private function scopesTable(): string
    {
        return (string) config('corex.tables.saved_view_scopes', 'sys_saved_view_scopes');
    }
}
