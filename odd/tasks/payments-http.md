# payments-http

Objetivo: exponer `POST /api/payments` con el formato único de errores y el identificador de correlación (unidad B1).
Rama: `feature/payments-http`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T api php artisan test --compact`.

## Specs

- **S1** Formato único de errores en `withExceptions` (arquitectura, sección 8):
  - `400 IDEMPOTENCY_KEY_MISSING`;
  - `404 TRANSACTION_NOT_FOUND` (las rutas inexistentes usan `NOT_FOUND`);
  - `409 IDEMPOTENCY_KEY_REUSED` e `IDEMPOTENCY_KEY_IN_PROGRESS`;
  - `422 VALIDATION_ERROR` con detalle por campo;
  - `500 INTERNAL_ERROR`, sin detalles internos.
- **S2** Middleware de correlación en `Shared` (HU-10, SUP-12, DA-07):
  - usa `X-Correlation-Id` si tiene entre 1 y 64 caracteres ASCII visibles (`0x21`–`0x7E`); si no, genera un UUID y registra una advertencia con el largo, nunca con el valor;
  - agrega el identificador al `Context`;
  - lo devuelve en **toda** respuesta, incluidas las de error.
- **S3** `POST /api/payments` (HU-01, HU-02, HU-06):
  - un Form Request con las reglas de HU-02, con entero estricto;
  - un middleware que exige `Idempotency-Key`;
  - un controller que solo invoca `ProcessPayment`;
  - un API Resource como `PaymentResponsePresenter`;
  - respuesta `201` y `Idempotent-Replayed: true` cuando se repite.

  La respuesta incluye `transaction_id`, `merchant_id`, `amount`, `currency`, `status`, `decline_reason`, `card.brand`, `card.last4`, `created_at` y `updated_at`.
- **S4** Pruebas de punta a punta de los escenarios Gherkin de HU-01 a HU-06, HU-10 y HU-11, contra PostgreSQL y con el adquirente simulado con `Http::fake`.

Fuera de alcance (unidad B2): los `GET`, los eventos en el detalle y los logs JSON estructurados.

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3 | inline | pendiente | |
| T4 | S4 | inline | pendiente | |

Ruta inline: tareas secuenciales sobre el mismo flujo HTTP; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta B1 aprobada: "ok, implementa en feature/payments-http". La regla de `X-Correlation-Id` es la corregida: hasta 64 caracteres ASCII visibles; si no cumple, se genera un UUID y se registra una advertencia. Se documenta en SUP-12.
- **L2** Decisiones de diseño:
  - el agregado expone `updatedAt()`, que usan la respuesta y el repositorio, para que coincidan;
  - el presentador se enlaza en un provider de `Payments/Http`, porque `Infrastructure` no puede depender de `Http`.
