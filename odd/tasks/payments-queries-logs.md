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
| T1 | S1 | inline | hecho | b2fb688; RED: 4 pruebas fallando |
| T2 | S2 | inline | hecho | 5b28aa5; RED: 10 pruebas fallando |
| T3 | S3 | inline | hecho | 79b8a9f (junto con T4); RED: 8 pruebas fallando; se confirmó que la prueba detecta errores quitando el `transaction_id` del contexto |
| T4 | S4 | inline | hecho | 79b8a9f; verificación en vivo con `docker compose logs \| grep <correlation_id>` en `api` y `acquirer-mock`, y 0 apariciones del PAN |

Ruta inline: tareas secuenciales; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta B2 aprobada: "ok", con sus dos decisiones.
- **L2** Diseño:
  - `TransactionSummary` es el modelo de lectura que comparten el `POST` (desde el agregado) y los `GET` (desde la base), con un solo `TransactionResource`;
  - el detalle y el listado no van envueltos en `data` en el nivel superior, igual que el `POST`; el listado usa `data` y `meta`;
  - `TransactionId::tryFrom()` permite que un identificador mal formado dé `404` sin capturar excepciones;
  - `ListTransactions` limita `per_page` a 100 en lugar de rechazarlo (SUP-13).
- **L3** Logs:
  - `Application` usa PSR-3 y el puerto `LogContext`, implementado con `Context`;
  - el formulario registra `payment.validation_failed` solo con los nombres de los campos;
  - `payment.unexpected_error` reemplaza el reporte por defecto;
  - el mock registra `acquirer_mock.authorized`;
  - en Compose, `LOG_CHANNEL=stderr` con `JsonFormatter` para `api` y `acquirer-mock`.
- **L4** Se aclaró HU-07: el detalle tiene los mismos campos que la creación, más `events`. Verificación local: 387 pruebas aprobadas.
- **L5** Verificador independiente: S1, S3 y S4 en PASS; S2 en PARTIAL por el bloqueo B1. Con un `page` muy grande, `(page-1)*per_page` desbordaba el entero y respondía `500`.
  - Corrección (1451b91): `page` tiene un máximo de 1.000.000. Recheck en vivo: `page=9223372036854775807` y `922337203685477580` dan `422`, y `page=1000000` da `200`. RED: 2 pruebas fallando.
  - Advisories aplicados:
    - `phpunit.xml` fuerza `APP_ENV=testing` y `LOG_CHANNEL=null`, y la suite ya no escribe logs en la salida del contenedor (0 líneas);
    - la prueba de `payment.unexpected_error` verifica la clase de la excepción y una sola línea.
  - Advisories no aplicados:
    - el `transaction_id` en `idempotency.replayed` y `idempotency.conflict`, porque la reserva no lo conoce;
    - la sangría de las líneas de `artisan serve` en el mock;
    - el límite de 8192 bytes por línea de php-fpm.
  - Pasan 390 pruebas.
