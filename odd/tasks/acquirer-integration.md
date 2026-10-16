# acquirer-integration

Objetivo: implementar el Acquirer Mock, su contenedor y el adaptador HTTP del puerto `AcquirerGateway`.
Rama: `feature/acquirer-integration`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T api php artisan test --compact`.

## Specs

- **S1** Acquirer Mock en `app/AcquirerMock`: `POST /acquirer/authorize` responde según las tarjetas de prueba:
  - `4111111111111111` y cualquier otra tarjeta válida aprueban;
  - `4000000000000002` rechaza con `INSUFFICIENT_FUNDS`;
  - `4000000000000119` responde `503` siempre;
  - `4000000000000259` responde `503` una vez y luego aprueba, contando los intentos por `transaction_id` en caché.

  Sus rutas solo existen con `ACQUIRER_MOCK_ENABLED=true` (DA-03), y el mock no usa código de `Payments` ([arquitectura, secciones 5.1 y 5.2](../../docs/04-architecture.md#51-contrato-http-del-acquirer-mock); [requerimientos, sección 7](../../docs/01-requirements-analysis.md#7-tarjetas-de-prueba-del-acquirer-mock)).
- **S2** Servicio `acquirer-mock` en `compose.yml`: la misma imagen, `php artisan serve` con varios workers, sin base de datos y sin exponerse por el gateway ([arquitectura, sección 2](../../docs/04-architecture.md#2-contenedores)).
- **S3** `HttpAcquirerGateway` implementa el puerto y clasifica cada respuesta:
  - `200` aprobado o rechazado es una decisión;
  - timeout, conexión fallida o `5xx` lanzan `AcquirerTemporarilyUnavailable`;
  - cualquier otro `4xx` o una respuesta mal formada lanzan `AcquirerIntegrationFailed`.

  Nunca lanza otra excepción (observación L4 de payments-process). Usa un timeout de conexión de 1 s, uno de 2 s por intento, y envía `X-Correlation-Id` ([arquitectura, secciones 5.1 y 5.3](../../docs/04-architecture.md#53-política-de-reintentos)).

Fuera de alcance: los endpoints de pagos, el manejo central de errores y los logs estructurados (unidad `payments-http`).

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3 | inline | pendiente | |

Ruta inline: tareas pequeñas y secuenciales; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta aprobada: "ok". Alcance: la unidad A (`acquirer-integration`); la unidad B (`payments-http`) queda para después.
