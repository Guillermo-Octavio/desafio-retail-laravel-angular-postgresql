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
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3 | inline | pendiente | |
| T4 | S4 | inline | pendiente | |
| T5 | S5 | inline | pendiente | |
| T6 | S6 | inline | pendiente | |
| T7 | S7 | inline | pendiente | |

Ruta inline: unidades secuenciales que dependen de las migraciones; no hay motivo de delegación. Al cierre corresponde un verificador independiente, por riesgo alto (migraciones y restricciones de datos).

## Log

- **L1** (2026-10-15) Propuesta aprobada: "ok, vamos!". Alcance: T1 a T7 de la propuesta `payments-persistence`.
- **L2** Hallazgo: las pruebas apuntaban a la base de desarrollo `payments`. Las variables del contenedor tienen prioridad sobre el `<env>` de `phpunit.xml` cuando no lleva `force="true"`, y con `RefreshDatabase` las pruebas habrían borrado los datos de desarrollo.
