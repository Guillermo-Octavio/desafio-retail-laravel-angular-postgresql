# transactions-web

Objetivo: implementar la vista Angular opcional para consultar transacciones (HU-12).
Rama: `feature/transactions-web`. Entrega: `ask-on-risk`. Runner: `docker compose exec -T web npx ng test --watch=false`.

## Specs

- **S1** Modelos de TypeScript del contrato de la API, sin `any`, y el servicio `TransactionsApi` (`list` y `get`) como única pieza que usa `HttpClient` (AGENTS.md, sección 5.2). Se activa `strict` en TypeScript.
- **S2** Componentes de presentación, solo con `input()` y `output()`:
  - filtros por comercio y estado;
  - tabla de transacciones;
  - paginación;
  - distintivo de estado;
  - detalle con su línea de tiempo de eventos;
  - estados de carga, vacío y error con "Reintentar".

  Los montos se muestran según el exponente de la moneda (CLP 0, USD 2).
- **S3** Containers y rutas con carga diferida: `/transactions` (listado) y `/transactions/:id` (detalle). El estado vive en signals, y los filtros y la página van en la URL (HU-12, criterios 1 y 2).
- **S4** Accesibilidad WCAG AA, verificada con `axe-core` (dependencia solo de desarrollo): etiquetas, teclado, foco, `aria-live` y contraste (HU-12, criterio 4). Se agregan al README las pruebas del frontend.
- **S5** Escenarios Gherkin de HU-12: filtrar por estado; ver el historial de una transacción; la API no responde, y se muestra el error con "Reintentar" (criterio 3).

Decisiones aprobadas: el detalle es una ruta propia, y `axe-core` se agrega como dependencia de desarrollo.

## Tasks

| ID | Specs | Ruta | Estado | Evidencia |
|----|-------|------|--------|-----------|
| T1 | S1 | inline | hecho | 14e33be; RED: no compilaba sin los modelos |
| T2 | S2 | inline | hecho | c444247; RED: no compilaba sin los componentes |
| T3 | S3, S5 | inline | hecho | 6bd2619; RED: 11 pruebas fallando |
| T4 | S4 | inline | hecho | 9c2fb17; prueba de contraste con un control negativo |

Ruta inline: tareas secuenciales del mismo módulo; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta aprobada: "ok, implementa en feature/transactions-web", con sus dos decisiones.
- **L2** Decisiones dentro del alcance:
  - textos de la interfaz en inglés, como el código y el contrato de la API;
  - montos con código de moneda (`CLP 15,000`, `USD 10.50`), porque `$` es ambiguo entre CLP y USD;
  - `page` solo se envía cuando es mayor que 1;
  - se activó `strict` en TypeScript;
  - los datos de prueba y el helper de axe viven en `src/testing/`, excluido de la compilación de la aplicación.
- **L3** Accesibilidad:
  - axe en cada componente y en las páginas;
  - el contraste se verifica aparte, con la paleta OKLCH de Tailwind 4, porque `jsdom` no calcula colores;
  - enlace para saltar al contenido, landmarks, `role="status"` y `role="alert"`;
  - el foco va al encabezado al abrir el detalle;
  - `:focus-visible` global.
- **L4** Observaciones de GGA aplicadas:
  - la lógica del `submit` salió de la plantilla;
  - se renombró la referencia `#statusSelect`;
  - `describeEvent` no muestra `undefined` y tiene pruebas directas;
  - los inputs de la URL usan camelCase con alias;
  - la paleta está tipada y no se modifica estado compartido.
- **L5** Verificación: 45 pruebas del frontend y build de producción con chunks diferidos por ruta. El gateway sirve `/transactions` con `200`. Se actualizó el README.
- **L6** Verificador independiente: S1, S2, S3 y S5 en PASS; S4 en PARTIAL por el bloqueo B1.
  - B1: con `<base href="/">`, el enlace `href="#main"` navegaba a `/#main` y recargaba la aplicación, perdiendo los filtros o el detalle.
  - Corrección (ea13536): el `click` hace `preventDefault()` y mueve el foco a `<main tabindex="-1">`. Se quitó `focus:outline-none` del h1 (no tenía efecto) y el enlace tiene un color explícito. RED: 1 prueba fallando. Pasan 46 pruebas en tres ejecuciones seguidas.
  - Dependencia: `axe-core` 4.10.3 exacta, solo de desarrollo, y fuera del build de producción.
  - Advisories no aplicados:
    - al paginar o reintentar, el foco cae al `body` porque el estado de carga reemplaza la tabla;
    - las regiones `role=status` y `alert` se insertan nuevas cada vez;
    - una página mayor a `last_page` no tiene camino de vuelta;
    - el `select` navega con cada `change`.
