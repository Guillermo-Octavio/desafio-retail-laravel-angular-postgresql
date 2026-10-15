# AGENTS.md — Payment Processing Service

Reglas del proyecto para dos audiencias:

- **Agentes de IA que escriben código** en este repositorio.
- **Gentleman Guardian Angel (GGA)**, que revisa los archivos en stage de cada commit contra este
  documento (configuración en `.gga`).

Estas reglas aplican a todo el monorepo. Las guías de cada framework las complementan; si hay
conflicto en arquitectura o principios, prevalece este documento.

## 1. Mapa del repositorio

| Ruta | Contenido | Guía específica |
|------|-----------|-----------------|
| `api/` | API Laravel 13 (PHP 8.5, Pest 5, Pint) | `api/CLAUDE.md` (Laravel Boost) y skills en `api/.claude/skills/` |
| `web/` | Vista Angular 22 (signals, standalone, Vitest) | `web/CLAUDE.md` |
| `docs/` | Requerimientos, historias, dominio y arquitectura | Fuente de verdad del comportamiento |
| `docker/`, `compose.yml` | Entorno de desarrollo | `docs/04-architecture.md` |

Antes de implementar, leer el documento de `docs/` que define el comportamiento: los criterios
de aceptación de `docs/02-user-stories.md` y las decisiones `DD-xx` y `DA-xx` no se reinterpretan.

## 2. Principios obligatorios

- **SOLID**
  - *Responsabilidad única*: cada clase tiene un solo motivo de cambio (validar, decidir,
    persistir, comunicar o presentar, nunca varios).
  - *Abierto/cerrado*: las reglas de negocio son políticas independientes; agregar una no
    modifica las existentes.
  - *Liskov*: los adaptadores cumplen el contrato completo de su puerto, incluidas sus excepciones.
  - *Segregación de interfaces*: puertos pequeños y específicos, no interfaces genéricas.
  - *Inversión de dependencias*: el dominio y la aplicación dependen de puertos; la infraestructura
    los implementa y se enlaza en el contenedor de dependencias.
- **DRY**: el conocimiento de negocio (límites, estados, códigos de error) vive en un solo lugar.
  No se duplica por copiar y pegar, pero tampoco se abstrae código que solo se parece por casualidad.
- **KISS**: la solución más simple que cumple los criterios de aceptación.
- **YAGNI**: no se escribe código para requerimientos que no están en `docs/`. Lo descrito en
  "Evolución" de la arquitectura queda fuera hasta que se pida.

## 3. Arquitectura de la API

Capas hexagonales dentro de `api/app/` (ver `docs/04-architecture.md`):

| Capa | Responsabilidad | Puede depender de | Prohibido |
|------|-----------------|-------------------|-----------|
| `Payments/Domain` | Agregado, value objects, enums, políticas y puertos | Nada externo | `Illuminate\*`, `Laravel\*`, facades, helpers (`now()`, `config()`, `app()`, `request()`), Eloquent |
| `Payments/Application` | Casos de uso que orquestan el dominio | `Domain` | Eloquent, HTTP, facades |
| `Payments/Infrastructure` | Repositorios Eloquent, cliente HTTP del adquirente, idempotencia | `Domain`, `Application`, Laravel | `Http` |
| `Payments/Http` | Controllers, Form Requests, API Resources, middleware | `Application`, Laravel | Lógica de negocio, Eloquent |
| `AcquirerMock` | Simulador del adquirente | Laravel | Código de `Payments` |
| `Shared` | Correlación, logging y formato de errores | Laravel | Lógica de negocio |

### 3.1 Controllers sin lógica

Un controller solo puede:

1. Recibir los datos ya validados por su Form Request.
2. Invocar **un** caso de uso.
3. Devolver un API Resource o una respuesta HTTP.

Un controller **no** puede contener reglas de negocio, condicionales sobre estados de negocio,
consultas Eloquent o SQL, llamadas HTTP, cálculos, `try/catch` para controlar el flujo, ni
construcción manual de respuestas de error (eso lo hace el manejador central de excepciones).

### 3.2 Dónde va cada cosa

| Necesidad | Lugar |
|-----------|-------|
| Formato y tipos de entrada | Form Request |
| Regla de negocio | Política del dominio |
| Cambio de estado | Método del agregado `Transaction` |
| Orquestación de un flujo | Caso de uso en `Application` |
| Persistencia | Repositorio en `Infrastructure` |
| Representación JSON | API Resource |
| Fecha y hora actual | Puerto `Clock` (nunca `now()` ni `new DateTimeImmutable()` en dominio o aplicación) |
| Valores configurables (límites, timeouts) | `config/` y variables de entorno, inyectados por constructor |

## 4. Reglas de dominio no negociables

1. Los montos son enteros en unidades mínimas (ver `docs/01-requirements-analysis.md`). Nunca
   `float`. La entrada HTTP valida `amount` como entero estricto y rechaza `10.50` y `"1050"`.
2. El número completo de tarjeta y el CVV nunca se persisten, registran en logs, serializan, ni
   aparecen en mensajes de excepción. Solo se guardan la marca y los últimos 4 dígitos.
3. El estado de una transacción solo cambia mediante los métodos del agregado, y cada cambio
   genera su evento. Los eventos son de solo inserción.
4. Ninguna transacción de base de datos queda abierta durante una llamada HTTP al adquirente.
5. La idempotencia se garantiza con la restricción única de la base de datos, no con locks en memoria.
6. Cada log de una solicitud lleva `correlation_id` mediante el `Context` de Laravel.
7. Todas las respuestas de error usan el formato único definido en la arquitectura.

## 5. Código limpio

- Nombres descriptivos que revelan intención; nada de abreviaturas ambiguas.
- Funciones cortas, con un solo nivel de abstracción y retornos tempranos en lugar de anidar.
- Sin números ni textos mágicos: usar enums, constantes o configuración.
- Sin código muerto, código comentado ni código de depuración (`dd`, `dump`, `var_dump`, `ray`,
  `console.log`).
- Excepciones específicas del dominio. Nunca se captura `\Throwable` o `\Exception` genéricos
  salvo en el borde (manejador central).
- Comentarios solo para explicar el *porqué*; el *qué* lo dice el código.

### 5.1 PHP

- `declare(strict_types=1);` en todo archivo nuevo de `app/Payments`, `app/AcquirerMock` y `app/Shared`.
- Tipos explícitos en parámetros, retornos y propiedades. Clases `final` y `readonly` cuando aplica.
- Inyección por constructor con promoción de propiedades; sin facades fuera de `Infrastructure`,
  `Http` y `Shared`.
- Formato con Pint.

### 5.2 Angular

- Patrón container/presentational: los componentes de presentación solo reciben `input()` y
  emiten `output()`; los containers coordinan servicios.
- Las llamadas HTTP viven en servicios; los componentes no usan `HttpClient`.
- Estado con signals; sin lógica de negocio en las plantillas.
- Tipos para los modelos de la API; nunca `any`.
- Accesibilidad WCAG AA (etiquetas, foco, contraste y navegación con teclado).

## 6. Pruebas

- TDD: toda regla o comportamiento nuevo se escribe primero como prueba que falla.
- Las pruebas del dominio son unitarias y no usan framework ni base de datos.
- Las pruebas de endpoints son feature tests contra PostgreSQL, con el adquirente simulado
  mediante `Http::fake`.
- Deterministas: reloj fijo y sin `sleep` real.
- El nombre de cada prueba describe el comportamiento; se prueba por la interfaz pública.
- Las reglas de arquitectura de la [sección 3](#3-arquitectura-de-la-api) se verifican con `arch()` de Pest.

Comandos (desde la raíz del repositorio):

```sh
docker compose exec -T api php artisan test --compact
docker compose exec -T api vendor/bin/pint app tests --format agent
docker compose exec -T web npx ng test --watch=false
```

`pint --dirty` no funciona dentro del contenedor, porque el repositorio git está en la raíz y
no en `api/`: se le pasan rutas explícitas.

## 7. Convenciones de idioma, commits y documentación

- Código, identificadores, comentarios y mensajes de commit en **inglés**.
- Documentación en `docs/` y este archivo en **español neutro**, con diagramas en **Mermaid**.
- No se usa el carácter de sección: las referencias son enlaces explícitos.
- Commits con Conventional Commits, sin líneas de coautoría ni atribución de IA.

## 8. Criterios de revisión de GGA

Un commit se **rechaza** si alguno de los archivos revisados:

1. Tiene lógica de negocio, consultas o llamadas HTTP en un controller.
2. Importa Laravel, facades o helpers desde `Payments/Domain`, o Eloquent desde `Payments/Application`.
3. Usa `float` para montos.
4. Persiste, registra en logs o expone el número completo de tarjeta o el CVV.
5. Cambia el estado de una transacción fuera del agregado, o modifica o borra eventos.
6. Agrega comportamiento sin pruebas que lo cubran.
7. Construye SQL concatenando datos de entrada, o incluye secretos o credenciales.
8. Deja código de depuración, código comentado o código muerto.
9. Usa `any` en TypeScript o `HttpClient` directamente en un componente.

Las demás desviaciones de este documento (nombres, tamaño de funciones, duplicación menor) se
informan como observaciones sin rechazar el commit.
