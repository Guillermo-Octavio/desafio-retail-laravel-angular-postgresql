# business-timezone

Objetivo: usar America/Santiago como zona de negocio para el vencimiento de la tarjeta, las fechas de la API y la vista web; el almacenamiento y los logs siguen en UTC.
Rama: `feature/business-timezone`. Entrega: `ask-on-risk`. Runners: `docker compose exec -T api php artisan test --compact` y `docker compose exec -T web npx ng test --watch=false`.

## Specs

- **S1** Zona de negocio configurable (`PAYMENTS_BUSINESS_TIMEZONE`, por defecto `America/Santiago`). El vencimiento de la tarjeta se evalúa contra el mes en curso **en esa zona** (corrige DD-06): un pago el 31 de octubre a las 22:00 en Santiago (1 de noviembre en UTC) acepta una tarjeta que vence en octubre.
- **S2** Las fechas de las respuestas de la API (`created_at`, `updated_at` y el `occurred_at` de los eventos) se expresan en la zona de negocio, en ISO 8601 con su offset (`-03:00` o `-04:00`). Representan el mismo instante que hoy.
- **S3** La vista web muestra las fechas en hora de Santiago, con la etiqueta de esa zona en lugar de "(UTC)".
- **S4** Se mantienen en UTC el almacenamiento (`timestamptz`, sesión UTC) y los logs. Se actualizan DD-06, la arquitectura, el README y la colección de Postman, que calcula los meses de vencimiento en la zona de negocio.

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3 | inline | pendiente | |
| T4 | S4 | inline | pendiente | |

## Log

- **L1** (2026-10-16) Instrucción del usuario: "las fechas están en UTC, pero el sistema debería registrarlas con America/Santiago". Alcance aprobado ("ok, aplica ese alcance en una rama nueva"):
  - el almacenamiento y los logs siguen en UTC, porque un `timestamptz` guarda un instante y el horario de verano de Chile repite una hora local;
  - la zona de negocio se usa para el vencimiento, las respuestas de la API y la vista.
