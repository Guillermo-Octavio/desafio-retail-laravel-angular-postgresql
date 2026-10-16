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
| T1 | S1 | inline | pendiente | |
| T2 | S2 | inline | pendiente | |
| T3 | S3, S5 | inline | pendiente | |
| T4 | S4 | inline | pendiente | |

Ruta inline: tareas secuenciales del mismo módulo; no hay motivo de delegación.

## Log

- **L1** (2026-10-16) Propuesta aprobada: "ok, implementa en feature/transactions-web", con sus dos decisiones.
