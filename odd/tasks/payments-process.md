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
| T1 | S1 | inline | hecho | a034f30; RED: 9 pruebas fallando |
| T2 | S2 | inline | hecho | 831557e; RED: 12 pruebas fallando |
| T3 | S3, S4, S5 | inline | hecho | e71936c; RED: 19 pruebas fallando |
| T4 | S6 | inline | hecho | fe0630a; RED: 1 prueba fallando |

Ruta inline: tareas secuenciales sobre un mismo flujo; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta aprobada: "sí, implementa en feature/payments-process". Con eso quedaron aprobadas las dos recomendaciones: el presentador como puerto implementado en `Http`, y `ACQUIRER_ERROR` para el `4xx`.
- **L2** Diseño de T3:
  - el bucle de intentos vive en `AcquirerAuthorization`, para que `ProcessPayment` no acumule dependencias;
  - `AcquirerDecision::applyTo()` aplica la decisión en el dominio;
  - la duración de cada intento se mide con el puerto `Clock`;
  - los conflictos de idempotencia lanzan `IdempotencyKeyReused` e `IdempotencyKeyInProgress`, que `Http` traducirá a `409`.
- **L3** Se documentaron `ACQUIRER_ERROR` y `INTEGRATION_ERROR` en `docs/01`, `docs/03` y `docs/04`. `BusinessRules`, `RetryPolicy`, `Sleeper` y `RequestFingerprinter` quedaron enlazados en el contenedor. `PaymentResponsePresenter` y `AcquirerGateway` se enlazan en el paso de `Http` y del cliente del adquirente.
- **L4** Observación de GGA para el paso siguiente: el adaptador de `AcquirerGateway` solo debe lanzar las dos excepciones del contrato. Si lanza otra, la transacción queda en `PROCESSING` y la clave sin completar.
- **L5** Verificación local: 236 pruebas aprobadas.
- **L6** Verificador independiente: S1 a S6 en PASS, sin bloqueos.
  - Advisory A1, causado por T4: con `afterCommit`, cada `save()` dentro de una transacción externa que hace commit vuelve a insertar los eventos.
  - Corrección (203d14a): el agregado numera sus eventos (`sequence`); la migración `000004` agrega `UNIQUE (transaction_id, sequence)`; y el repositorio inserta con `ON CONFLICT (transaction_id, sequence) DO NOTHING`. Esto también cubre un commit ambiguo seguido de reintento.
  - RED: 7 pruebas fallando, entre ellas la reproducción de A1. GREEN: 239 pruebas. Se probaron el rollback y la nueva migración, y se documentó en la sección 9.2 del modelo de dominio.
  - El advisory A2 (excepción inesperada del gateway) queda para el adaptador, según L4.
- **L7** Revisión RDD de la rama: **aprobada** y cerrada (lineage `review-7002908b5ca3bdd1`), con hallazgos informativos.
- **L8** (2026-10-16) Instrucción del usuario: "ok, corrije", sobre los hallazgos 1 (CVV en la huella) y 2 (duración negativa).
  - 2914d95: el CVV queda fuera de la huella, porque PCI DSS prohíbe conservarlo después de autorizar, ni siquiera como hash. Se documentó en DD-10.
  - baf818a: puerto `MonotonicTimer` (`hrtime`) para medir la duración de cada intento; la fecha del evento sigue viniendo de `Clock`.
  - RED: 21 pruebas fallando. GREEN: 242 pruebas.
  - Quedan abiertos, sin cambios: la migración `000004` sin backfill (ninguna base tiene filas), la rotación de `APP_KEY`, la secuencia ante una reconstrucción (no existe) y la clave atascada (limitación conocida).
- **L9** (2026-10-16) Instrucción del usuario: "sí, agrégalo y luego haz merge a main y push". Se documentó en SUP-11 y en el criterio 4 de HU-06 que el CVV no forma parte del contenido comparado.
