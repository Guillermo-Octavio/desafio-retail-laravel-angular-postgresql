# payments-persistence

Objetivo: implementar la persistencia en PostgreSQL de `api/app/Payments/Infrastructure` (migraciones, repositorio, idempotencia y adaptadores).
Rama: `feature/payments-persistence`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T api php artisan test --compact`.

## Specs

- **S1** Las pruebas corren contra PostgreSQL y no contra SQLite en memoria (DA-09), en una base propia que no es la de desarrollo.
- **S2** Migración de `transactions` con `CHECK` de estado, moneda, monto positivo y coherencia entre estado, `decline_reason` y `acquirer_reference`, más sus 3 índices (DD-12; [modelo de dominio, sección 9.1](../../docs/03-domain-model.md#91-transactions)).
- **S3** Migración de `transaction_events` con el trigger que rechaza `UPDATE` y `DELETE` (DD-11; [modelo de dominio, sección 9.2](../../docs/03-domain-model.md#92-transaction_events)).
- **S4** Migración de `idempotency_keys` con `UNIQUE (merchant_id, idempotency_key)` (DD-09; [modelo de dominio, sección 9.3](../../docs/03-domain-model.md#93-idempotency_keys)).
- **S5** `EloquentTransactionRepository::save()`: inserta o actualiza el estado y agrega los eventos de `pullRecordedEvents()` en una sola transacción de base de datos (DA-04).
- **S6** `PostgresIdempotencyStore`: `reserve()` con `INSERT … ON CONFLICT DO NOTHING`, `attachTransaction()` y `complete()` (DA-06).
- **S7** Adaptadores de `Clock` y de generación de UUID v7, y enlace de los puertos en el contenedor de dependencias (DD-02).

Fuera de alcance: casos de uso, cliente HTTP del adquirente, Acquirer Mock y endpoints.

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | hecho | 719c4cd; RED: la base era `payments` |
| T2 | S2 | inline | hecho | 5b07723; RED: 19 pruebas fallando |
| T3 | S3 | inline | hecho | 2e3bd36; RED: 5 pruebas fallando |
| T4 | S4 | inline | hecho | 1b2d139; RED: 4 pruebas fallando |
| T5 | S5 | inline | hecho | 90ea022; RED: 6 pruebas fallando |
| T6 | S6 | inline | hecho | 396bbd1; RED: 6 pruebas fallando |
| T7 | S7 | inline | hecho | 466e204; RED: 6 pruebas fallando |

Ruta inline: unidades secuenciales que dependen de las migraciones; no hay motivo de delegación. Al cierre corresponde un verificador independiente, por riesgo alto (migraciones y restricciones de datos).

## Log

- **L1** (2026-10-15) Propuesta aprobada: "ok, vamos!". Alcance: T1 a T7 de la propuesta `payments-persistence`.
- **L2** Hallazgo: las pruebas apuntaban a la base de desarrollo `payments`. Las variables del contenedor tienen prioridad sobre el `<env>` de `phpunit.xml` cuando no lleva `force="true"`, y con `RefreshDatabase` las pruebas habrían borrado los datos de desarrollo.
- **L3** El `<env force>` de PHPUnit no alcanza: Laravel lee `$_SERVER` primero, así que también se fuerza `<server>`. La base `payments_test` la crea `docker/postgres/initdb/01-create-test-database.sh`, probado en un contenedor desechable. En el volumen existente se creó con `createdb`, como indica el README.
- **L4** `CREATE OR REPLACE FUNCTION` en el trigger: `migrate:fresh` borra las tablas pero no las funciones. La suite se corrió dos veces seguidas y se probaron el rollback y la nueva migración.
- **L5** Contrato de `TransactionRepository::save()`: los eventos se toman del agregado antes de escribir, así que una instancia cuyo `save()` falló se descarta (observación de GGA). Queda documentado en el puerto.
- **L6** Se agregó el puerto `TransactionIdGenerator`, necesario para S7, porque el dominio no puede usar `Str::uuid7()`.
- **L7** La concurrencia de `reserve()` se prueba con una segunda conexión que hace commit primero. La carrera simultánea real la garantiza la restricción única.
- **L8** Verificación: 183 pruebas aprobadas. Pendientes: el verificador independiente y la revisión RDD.
