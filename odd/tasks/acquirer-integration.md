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
| T1 | S1 | inline | hecho | e130cec; RED: 8 pruebas fallando |
| T2 | S2 | inline | hecho | 96ba466; prueba de humo con las 5 tarjetas en el contenedor; el gateway responde `404` en `/acquirer/authorize` |
| T3 | S3 | inline | hecho | ab9e6d7; RED: 18 pruebas fallando; de punta a punta contra el mock real y con el mock detenido (`TIMEOUT` en unos 1,2 s) |

Ruta inline: tareas pequeñas y secuenciales; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta aprobada: "ok". Alcance: la unidad A (`acquirer-integration`); la unidad B (`payments-http`) queda para después.
- **L2** Hallazgo en T2: con `api/.env` presente, `artisan serve` anula las variables que no están en su lista fija, y las rutas del mock no se registraban (`404`). Se corrigió con `--no-reload`. El `entrypoint` ya no ejecuta migraciones en el mock.
- **L3** Hallazgo en T3: el cliente HTTP seguía las redirecciones, y una respuesta `302` reenviaba los datos de tarjeta a otro host. Ahora usa `withoutRedirecting()`, con una prueba que verifica que se envía una sola solicitud.
- **L4** Observaciones de GGA:
  - El controller del mock ya no tiene `match`, porque `AcquirerSimulator` devuelve `MockResponse`.
  - Los textos de protocolo ahora son constantes.
  - Sin aplicar: el entero estricto en el request del mock (es solo un simulador) y la detección del timeout por el mensaje de cURL. Si falla, el error queda como `CONNECTION_FAILED`, que también es temporal.
- **L5** Se documentaron en la sección 5.2 de la arquitectura `--no-reload` y el no seguir redirecciones. Verificación local: 270 pruebas aprobadas.
- **L6** Verificador independiente: S1 y S2 en PASS; S3 en FAIL por el bloqueo B1. Si el cuerpo de un `4xx` o `5xx` se corta después del encabezado, Laravel lanza `RequestException`, que escapaba del contrato del puerto. Corrección: el gateway mapea esa excepción por su estado (`5xx` → temporal; el resto → integración) y no la encadena, porque su mensaje cita el cuerpo de la respuesta. RED: 2 pruebas fallando. Recheck con el servidor TCP real del verificador: `cut503` y `slow503` → `HTTP_503` temporal, `cut422` → `HTTP_422` integración, sin filtrar datos de tarjeta. Pasan 272 pruebas. Se documentó que `--no-reload` también es necesario para `PHP_CLI_SERVER_WORKERS`.
- **L7** Advisories no aplicados:
  - URL base mal formada o sin esquema: es un error de configuración, fuera del dominio;
  - el contador del mock no es atómico ante solicitudes paralelas, pero el flujo real reintenta en secuencia;
  - el entero no estricto en el mock, ya registrado en L4.
