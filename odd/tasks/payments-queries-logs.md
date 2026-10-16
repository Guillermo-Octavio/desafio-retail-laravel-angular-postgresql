# payments-queries-logs

Objetivo: implementar las consultas de transacciones y los logs estructurados (unidad B2).
Rama: `feature/payments-queries-logs`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T api php artisan test --compact`.

## Specs

- **S1** `GET /api/payments/{id}`:
  - usa el puerto de lectura `TransactionQueries` (en `Application`, implementado con Eloquent) y el caso de uso `GetTransaction`;
  - devuelve los mismos campos que el `POST` más `events`, en orden;
  - un identificador inexistente o mal formado responde `404 TRANSACTION_NOT_FOUND` (HU-07, HU-09).
- **S2** `GET /api/payments` (HU-08, SUP-13):
  - filtros opcionales `merchant_id` y `status`, combinables;
  - orden del más reciente al más antiguo;
  - 20 por página por defecto, con un máximo de 100 (`per_page=500` usa 100);
  - un `status` inválido responde `422 VALIDATION_ERROR`;
  - sin coincidencias, responde `200` con una lista vacía.
- **S3** Logs JSON por `stderr` (arquitectura, sección 7; DA-07; HU-10):
  - `correlation_id` en toda línea, mediante `Context`;
  - `transaction_id` desde que se crea la transacción, mediante el puerto `LogContext`;
  - el mock también registra con `correlation_id`.
- **S4** Eventos de log de la arquitectura, sección 7.2, emitidos desde `Application` mediante PSR-3 `LoggerInterface`. Incluye la advertencia `idempotency.conflict` para una clave en `IN_PROGRESS`, y una prueba de que el PAN y el CVV nunca aparecen en los logs (HU-11).

Decisiones aprobadas:
- el detalle es la representación del `POST` más `events`, y el `POST` no lleva eventos (se aclara en HU-07);
- `Application` registra mediante PSR-3, y `LogContext` agrega el `transaction_id` al contexto.

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3 | inline | pendiente | |
| T4 | S4 | inline | pendiente | |

Ruta inline: tareas secuenciales; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta B2 aprobada: "ok", con sus dos decisiones.
