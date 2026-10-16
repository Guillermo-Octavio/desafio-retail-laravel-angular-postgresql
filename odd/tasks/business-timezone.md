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
| T1 | S1 | inline | hecho | c329e6e; RED: 26 pruebas fallando; caso del borde de mes: 31 de octubre a las 22:00 en Santiago |
| T2 | S2 | inline | hecho | 4326ded; RED: 3 pruebas fallando; offsets de verano (-03:00) e invierno (-04:00), y el instante sigue en UTC en la base |
| T3 | S3 | inline | hecho | 59efc8d; RED: no compilaba sin el módulo; se usa `Intl` porque `DatePipe` no acepta zonas IANA |
| T4 | S4 | inline | hecho | DD-06, arquitectura 7.3, README y Postman. Newman: 48 requests y 182 aserciones, todas en verde |

## Log

- **L1** (2026-10-16) Instrucción del usuario: "las fechas están en UTC, pero el sistema debería registrarlas con America/Santiago". Alcance aprobado ("ok, aplica ese alcance en una rama nueva"):
  - el almacenamiento y los logs siguen en UTC, porque un `timestamptz` guarda un instante y el horario de verano de Chile repite una hora local;
  - la zona de negocio se usa para el vencimiento, las respuestas de la API y la vista.
- **L2** Postman: el sandbox de Newman no tiene `Intl` ("Intl is not defined"), pero `toLocaleString` con `timeZone` sí aplica la zona. Prueba en el borde de mes: el 1 de noviembre a las 01:00 UTC da 10/2026.
- **L3** Verificación: 398 pruebas de la API y 55 de la web.
- **L4** Revisión RDD de la rama con tres lentes: **aprobada** y cerrada.
- **L5** (2026-10-16) Instrucción del usuario: "Sí, corrije los hallazgos".
  - 9d16143: `BusinessTimezone`, en `Application`, es un único singleton validado; el provider falla al arrancar si la zona no existe (`InvalidBusinessTimezone`), en vez de hacer fallar cada pago.
  - Commit de la web: la vista muestra la hora que entrega la API, que ya viene en la zona de negocio con su offset, sin una zona propia (columna "business time"), y el detalle recupera los milisegundos, que se habían perdido sin que nadie lo pidiera.
  - Postman: la zona es una constante con nombre, con un comentario que exige que coincida con la configuración de la API.
  - RED: 5 pruebas de la API fallando y la web sin compilar. Pasan 401 pruebas de la API y 57 de la web, y Newman pasa con 48 requests y 182 aserciones.
- **L6** La revisión RDD de las correcciones pidió **una corrección acotada** (crítica). La validación con `DateTimeZone::listIdentifiers()` rechazaba zonas que existen (`Etc/UTC`, `GMT`, `US/Pacific`, `-03:00`, mayúsculas distintas) y habría impedido arrancar con una configuración que antes funcionaba. Corrección (fbb6331): se valida construyendo la zona y capturando `DateInvalidTimeZoneException`, que solo ocurre si la zona no existe. RED: 5 pruebas fallando. La validación dirigida del proveedor la aprobó, y la revisión quedó cerrada. Pasan 406 pruebas de la API.
