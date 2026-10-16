# payments-process

Objetivo: implementar el caso de uso `ProcessPayment` en `api/app/Payments/Application`, con reintentos, huella de idempotencia y presentación de la respuesta guardada.
Rama: `feature/payments-process`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T api php artisan test --compact`.

## Specs

- **S1** `RetryPolicy`: hasta 3 intentos en total, con backoff exponencial de 200 ms y 400 ms más jitter. Espera mediante un puerto `Sleeper`, sin `sleep` real en las pruebas. Los valores son configurables (SUP-10; [arquitectura, sección 5.3](../../docs/04-architecture.md#53-política-de-reintentos)).
- **S2** Puerto `RequestFingerprinter` y adaptador HMAC-SHA256 del contenido normalizado, firmado con la clave de la aplicación (DD-10).
- **S3** `ProcessPayment` sigue el flujo de la [arquitectura, sección 4](../../docs/04-architecture.md#4-flujo-de-post-apipayments) con los límites transaccionales de DA-04, y cubre HU-01, HU-03, HU-04, HU-05 y HU-06:
  - repetir la respuesta original;
  - `IDEMPOTENCY_KEY_REUSED` cuando el contenido es distinto;
  - `IDEMPOTENCY_KEY_IN_PROGRESS` cuando la primera solicitud sigue en curso;
  - rechazo por reglas, sin llamar al adquirente;
  - aprobación y rechazo del adquirente, sin reintentar el rechazo;
  - reintentos ante errores temporales, y `FAILED` con `ACQUIRER_UNAVAILABLE` al agotarlos.
- **S4** Decisión aprobada: el adaptador del Resource de `Http` implementa un puerto `PaymentResponsePresenter`, y la respuesta guardada para repetir es exactamente la original.
- **S5** Decisión aprobada: un `4xx` del adquirente es un error de integración. Se registra el intento con `outcome` `INTEGRATION_ERROR`, y la transacción termina en `FAILED` con el motivo nuevo `ACQUIRER_ERROR`, sin reintentar. Se documenta en `docs/`.
- **S6** Advertencia de la revisión anterior: el repositorio borra los eventos registrados con `DB::afterCommit()`, para que una transacción externa revertida no los pierda.

Fuera de alcance: el Acquirer Mock, el cliente HTTP del adquirente, el endpoint, la implementación del presentador en `Http` y los logs estructurados.

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3, S4, S5 | inline | pendiente | |
| T4 | S6 | inline | pendiente | |

Ruta inline: tareas secuenciales sobre un mismo flujo; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta aprobada: "sí, implementa en feature/payments-process". Con eso quedaron aprobadas las dos recomendaciones: el presentador como puerto implementado en `Http`, y `ACQUIRER_ERROR` para el `4xx`.
