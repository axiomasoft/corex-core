# corex/core

Ядро CoreX для любого Laravel-приложения: `BaseModel` (ULID/UUIDv7/int-шов), `Settings` (каскад
System→Tenant→User), append-only `Audit`, `MoneyCast`, департаменты (ltree),
фичефлаги, PubSub-контракт и реестр сущностей. MIT.

Пакет ставится как зависимость приложения-потребителя; публичные контракты —
в `src/Contracts/`, реализации скрыты.

## Требование к СУБД

Ядро **требует PostgreSQL 16** для тенантской БД: `sys_*`-миграции используют
`ltree`/BRIN/`jsonb`/`UNIQUE NULLS NOT DISTINCT`/`pg_advisory_xact_lock` —
PG-нативные конструкции без SQLite-эквивалента. SQLite-лейн (`composer test`)
покрывает только pure-PHP код пакета для удобства unit-тестирования — это
НЕ заявленная поддержка SQLite в продакшене (OQ-12). Все гарантии
целостности/конкурентности проверяются исключительно на PG-лейне
(`composer test:pg`).

## Установка миграций (важно)

Миграции ядра (`sys_*`) — **PostgreSQL-only** (jsonb, `CHECK`, `UNIQUE NULLS NOT
DISTINCT`, BRIN, ltree/GIST) и **тенантские** (живут в базе аккаунта, не в
корневой control-plane БД). Поэтому пакет их **НЕ подключает автоматически**
(`loadMigrationsFrom` сломал бы sqlite-сьюты потребителя и прогнал бы
тенантский DDL на дефолтном коннекшене). Схема ставится явной публикацией:

```bash
# 1. Скопировать миграции в приложение
php artisan vendor:publish --tag=corex-migrations

# 2. Прогнать их НА ТЕНАНТСКОМ коннекшене (под DB-per-account, P2 — это база
#    аккаунта; в boxed/single-DB установке это дефолтный коннекшен)
php artisan migrate --database=<tenant>
```

Тег `corex-migrations-tenant` — синоним `corex-migrations`, явно называющий
скоуп коннекшена: `sys_*` — **тенант-схема**. Корневые (`root_*`) таблицы —
предмет отдельного пакета/плана (P2) и публикуются отдельным тегом на
центральный коннекшен. Никогда не гоняйте `corex-migrations` на корневом
коннекшене — это засорит golden-шаблон аккаунта чужими таблицами.

### Версия схемы и апгрейды

Потребитель публикует **копию** миграций, поэтому апгрейд пакета не может
изменить уже опубликованные файлы «на месте». Изменения схемы поставляются
**новыми аддитивными миграциями** под тем же тегом: при обновлении пакета
повторите `vendor:publish --tag=corex-migrations` (без `--force` — дозабирает
только новые файлы) и `migrate`. Текущее поколение схемы —
`CoreX\CoreServiceProvider::SCHEMA_VERSION`.

## Стратегия идентификаторов

Тип ключевых колонок (`id`, ссылки, FK) следует `config('corex.ids.strategy')`:
`uuid7` (дефолт, ADR-003), `uuid`, `ulid`, `int`. Миграции пишут колонки через
`CoreX\Support\Schema\IdColumns`, модели — через трейт
`CoreX\Concerns\HasConfiguredId`, так что boxed/legacy-установка выравнивает
ключи ядра под свой тип без форка миграций. Меняйте стратегию **до** первого
`migrate` — она фиксирует тип колонок в БД.

## Точность времени

Все `timestamptz`-колонки объявлены с явной микросекундной точностью
(`precision: 6`): PostgreSQL при `timestamp(0)` **округляет** значение, что
исказило бы хронологический порядок в юридически значимом append-only аудите
(`sys_audit_log`). `sys_audit_log.seq bigserial` — монотонный курсор аудита:
keyset-пагинация и tie-break сортировки идут **только по `seq`**, а не по
`id`/`occurred_at` (часы принявшего узла монотонности не дают).

## Compatibility evidence

The factual compatibility passport, its local proof class, and explicit limits
are in [PACKAGE.md](PACKAGE.md). It does not extend Composer constraints or
constitute a publication promise.
