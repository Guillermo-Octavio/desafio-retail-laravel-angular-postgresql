# payments-domain

Objetivo: implementar la capa `api/app/Payments/Domain` (PHP puro) con TDD.
Rama: `feature/payments-domain`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T api php artisan test --compact`.

## Specs

- **S1** Pruebas `arch()` de Pest para la regla de dependencias entre capas y estructura de carpetas (DA-02, DA-10; [AGENTS.md, sección 3](../../AGENTS.md#3-arquitectura-de-la-api)). `Payments/Domain` no depende de `Illuminate\*`, `Laravel\*`, facades ni helpers; `Payments/Application` no depende de Eloquent ni HTTP.
- **S2** `Money` y `Currency`: monto entero en unidades mínimas, siempre positivo; `Currency` `CLP` (exponente 0) y `USD` (exponente 2) ([modelo de dominio, sección 4](../../docs/03-domain-model.md#4-value-objects); SUP-02, SUP-03).
- **S3** `CardNumber` (13 a 19 dígitos, Luhn, marca por prefijo según DD-05, últimos 4 dígitos, transitorio), `Cvv` (3 o 4 dígitos, transitorio), `CardExpiry` (vigente hasta el último día del mes, en UTC) y el puerto `Clock` (DD-04, DD-05, DD-06). Ningún dato sensible aparece en volcados, serialización ni mensajes de excepción (HU-11).
- **S4** Políticas de negocio evaluadas en orden fijo, que se detienen en la primera falla: Luhn → `INVALID_CARD_NUMBER`, vencimiento → `CARD_EXPIRED`, monto sobre el límite por moneda → `AMOUNT_LIMIT_EXCEEDED`. Un monto igual al límite está permitido. El límite es configurable e inyectado (SUP-04, DD-07, HU-03).
- **S5** Agregado `Transaction`: invariantes 1 a 8, máquina de estados, `InvalidTransactionTransition` sin modificar el agregado, y eventos `STATUS_CHANGED` y `ACQUIRER_ATTEMPT` creados solo por sus operaciones ([modelo de dominio, secciones 3 y 7](../../docs/03-domain-model.md#3-agregado-transaction); DD-01).
- **S6** Puertos `TransactionRepository`, `AcquirerGateway` e `IdempotencyStore`, solo como interfaces ([modelo de dominio, sección 6](../../docs/03-domain-model.md#6-puerto-del-adquirente); DD-08).

Fuera de alcance: migraciones, Eloquent, casos de uso, HTTP, Acquirer Mock y generación de UUID v7 (se resuelve en infraestructura).

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | hecho | 1c8a904; RED con clase temporal que usaba `now()` y `Str`, sin strict types |
| T2 | S2 | inline | hecho | 668471b; RED: clases inexistentes |
| T3 | S3 | inline | hecho | 615a0f4; RED: clases inexistentes |
| T4 | S4 | inline | hecho | caa0102; RED: clases inexistentes |
| T5 | S5 | inline | hecho | 20406d6 + ef82fdc (fixtures); RED: clases inexistentes |
| T6 | S6 | inline | hecho | a666647; RED: 14 pruebas fallando |

Ruta inline: no hay motivo de delegación (unidades secuenciales que dependen entre sí, sin paralelismo).

## Log

- **L1** (2026-10-15) Propuesta aprobada: "sí, implementa en feature/payments-domain". Alcance aprobado: T1 a T6 de la propuesta (capa `Payments/Domain`).
- **L2** Decisión: el orden de las reglas sigue DD-07 (Luhn, vencimiento, monto); HU-03 solo las enumera.
- **L3** Verificación: `php artisan test --compact` da 136 pruebas aprobadas (257 aserciones) y Pint pasa. GGA aprobó cada commit.
- **L4** Observaciones de GGA aplicadas:
  - los helpers de prueba se movieron de funciones globales a `Tests\Support\PaymentFixtures`;
  - `AmountLimits` rechaza límites que no son enteros, porque `config()` puede entregar strings.
- **L5** Observaciones de GGA no aplicadas, por KISS/YAGNI:
  - `AcquirerReference` como value object;
  - la longitud de `error_code`;
  - constantes con nombre para los prefijos de las marcas.
- **L6** Limitación: el cast `(array)` y la reflexión exponen las propiedades privadas de `CardNumber` y `Cvv`. PHP no lo puede impedir; `var_dump`, `print_r`, `json_encode` y `serialize` están cubiertos.
- **L7** Siguiente paso: la evaluación nativa marca riesgo alto (`hot_path` payments) con unas 2.200 líneas, y queda pendiente el consentimiento de revisión RDD.
