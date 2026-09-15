# Changelog

Все заметные изменения `corex/core` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

### Added

- `TenantStorage` with scoped durable/ephemeral local disks, explicit usage capability and an
  explicit unsupported-export outcome. The boxed adapter derives its root exclusively from the
  current account context and does not require tenancy, auth, Stancl, cloud storage, or archives.

- **`ImpersonationService` contract + `ImpersonationState` + boxed `NullImpersonationService`
  (P2.11, B-11 §5.9, D139/D140).** Lives in `corex/core` — the sole consumer, `CurrentActor`,
  is level 0 and must not import `corex/tenancy`; the cloud implementation rebinds via
  `Config::impersonationServiceClass()` (D10). `ImpersonationGuard` (L3 — semantic layer of the
  read-only invariant, D141) + `ImpersonationRestrictedAction` enum (closed, five cases §5.9) +
  `ImpersonationRestrictedException`. `ActorType::Support` added (D137) — `sys_audit_actor_ck`
  widened by a new additive migration (closed P1.8 file untouched). `CurrentActor` now resolves
  the impersonation contract from the container: `type()`/`id()`/`context()` distinguish a
  support actor from `user`/`system`.
- `FeatureFlagDefinition::make()` — статическая фабрика (+ `module()`/`payload()` fluent-сеттеры),
  зеркалит `SettingDefault`-канон; конструктор не тронут (P3.6).

### Changed

- Публичные тексты пакета продукт-нейтральны: composer-`description` («… for the Flex ecosystem»
  → «… for any Laravel application») и шапка `README.md` — уровень 0 доктрины (D91) не несёт
  привязки к продукту-потребителю (P2.17).
- Продуктовые докблоки публичной поверхности `Contracts/**`: убраны внутренние спека-ссылки
  (B-10 §…) из основного текста, перенесены в трейлинг `@internal spec: …`; `ModuleRegistrar`
  получил контрактный докблок (аддитивность регистрации, канонический путь динамического
  выключения через lifecycle-FSM corex/modules + рестарт воркера) (P3.6).

## [0.1.0] - 2026-07-15

Первый тегированный релиз. Реализует roadmap-фазу P0 / блупринт B-10 (примитивы ядра).

### Requires

- **PostgreSQL 16** для тенантской БД. Миграции ядра (`sys_*`) используют `ltree`, BRIN-индексы,
  `jsonb`, `UNIQUE NULLS NOT DISTINCT`, `pg_advisory_xact_lock` — PG-нативные конструкции без
  SQLite-эквивалента. SQLite-лейн (`composer test`) покрывает только pure-PHP код пакета для
  удобства unit-тестирования — это **не заявленная поддержка SQLite в продакшене** (OQ-12). Все
  гарантии целостности/конкурентности проверяются исключительно на PG-лейне (`composer test:pg`).

### Added

- Конфигурируемая ID-стратегия: `IdStrategy::Uuid7` добавлена и стала дефолтом (нативный PG `uuid`,
  time-sortable), наряду с существующими `ulid`/`int`-швами (P1.1, P1.20).
- `TenantContext`/`AccountRef`/`WorkspaceRef` — контракты для модели tenancy P2 (реализация — фаза P2) (P1.12).
- Settings v2: namespaced, scoped (`sys_settings`), обработка sensitive-значений, события изменений (P1.7);
  каскад скоупов и payload-фолбэк (P1.22).
- Audit v2: очередной `AuditEntry`, `sys_audit_log`, авто-observer, sensitive-фильтр колонок,
  killswitch-guard на шве записи, монотонный курсор `seq`, retention-шов (P1.8, P1.21).
- `DomainEvent` база (eventId/occurredAt/tenant), заменяет пустой маркер `CoreEvent`;
  `Cancellable` — отдельный опциональный интерфейс (P1.9, P1.25).
- FeatureFlags: персистентные `sys_feature_flags`/`sys_feature_overrides` вместо config-driven резолвера (P1.9, P1.22).
- Departments (`sys_departments`, `ltree`): `DepartmentRepository` с lock-then-read `move()`,
  after-commit доменные события, soft-delete (P1.10, P1.23).
- PubSub-контракт + драйверы `redis`/`db-poll`/`sync-null`, рантайм-guard, курсор/retention-шов (P1.11, P1.24).
- Новое доменное исключение `CrossWorkspaceMoveException` (guard в `Departments::move()`, P1.29).

### Changed

- Старый `EntityRegistry` (`CoreX\Registry\EntityRegistry`/`CoreX\Contracts\EntityRegistry`) **удалён** —
  заменён `EntityRegistry` v2 из `flex/modules` (см. его CHANGELOG). Breaking, легально pre-1.0 (P1.2).
- `IdColumns` эмитят нативные PG-типы по сконфигурированной стратегии (P1.20).

### Fixed

- `Audit`: sensitive-фильтр больше не пропускает помеченные атрибуты в `sys_audit_log`;
  killswitch теперь гардит непосредственно шов записи (P1.21).
- `Departments::move()`: теперь проверяет `workspace_id` нового родителя под уже взятым advisory-локом
  до перемещения — устраняет тихий cross-workspace перенос поддерева (M-2, P1.29).

### Deprecated / Will break in 0.2

- **Row-level tenancy** — `CoreX\Concerns\BelongsToTenant`, `CoreX\Scopes\TenantScope`,
  `CoreX\Contracts\TenantService`. Реализуют **опциональную row-level** мульти-тенантность и
  **замещаются физической DB-per-account моделью**, спроектированной для фазы P2 (ADR-004,
  stancl/tenancy v4). В 0.1.0 остаются рабочими (on-prem/boxed-инсталляции могут по-прежнему
  биндить `TenantService`), но новые фичи на этот шов строить не стоит — в 0.2 он будет заменён, не расширен.
- `AuditEntry`, `SettingsScope`, `TenantContext` — контракты расширены волной и будут расширены P2;
  считать их текущую форму предварительной.
- **`int`-стратегия ID** — ожидаемо конфликтует с DB-per-account (у каждой аккаунт-БД своя
  последовательность); избегать для новых tenant-scoped моделей.

### Known limitations

- Каскад скоупов `FeatureFlags` (`findOverride()`) — single-scope exact-match; narrow→wide не
  реализован (ждёт P2 tenancy).
- Secret-колонки вне `password`/`remember_token` (`api_token`, `two_factor_secret`) на
  `$guarded=[]`-моделях могут утекать в аудит, если хост не объявил `auditSensitive()`.
