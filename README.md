# Payment Processing Service

Servicio de procesamiento de pagos con Laravel, PostgreSQL y una vista opcional en Angular.

Documentación del diseño:

- [Análisis de requerimientos](docs/01-requirements-analysis.md)
- [Historias de usuario](docs/02-user-stories.md)
- [Modelo de dominio](docs/03-domain-model.md)
- [Arquitectura](docs/04-architecture.md)

## Ejecución local

Requisitos: Docker con Docker Compose v2.

1. Crear el archivo de configuración a partir de la plantilla:

   ```sh
   cp .env.example .env
   ```

2. Completar los valores vacíos de `.env`:

   - `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`: el nombre de la base de datos, el usuario y
     la contraseña que elijas.
   - `APP_KEY`: generarla con

     ```sh
     echo "base64:$(openssl rand -base64 32)"
     ```

3. Levantar el stack:

   ```sh
   docker compose up -d --build
   ```

4. Verificar:

   - API: <http://localhost:8080/up> responde `200`.
   - Vista web: <http://localhost:8080>.

### Configuración

- El `.env` de la raíz es la **única** fuente de configuración del stack. Docker Compose lo lee y
  entrega los mismos valores a PostgreSQL y a la API, por lo que no pueden quedar desalineados.
- `.env` está ignorado por git. `.env.example` es la plantilla versionada y no contiene secretos.
- Si falta un valor obligatorio, `docker compose` se detiene e indica qué variable completar.
- Dentro de Docker la API no usa `api/.env`: toma su configuración de las variables del contenedor.
- PostgreSQL solo aplica las credenciales al crear su volumen. Para cambiarlas después hay que
  recrearlo con `docker compose down -v`, lo que **borra los datos** de la base.

### Pruebas

Las pruebas usan PostgreSQL, en la base `payments_test`, separada de la de desarrollo. El
contenedor de PostgreSQL la crea al inicializar un volumen vacío
([script](docker/postgres/initdb/01-create-test-database.sh)). Si el volumen se creó antes de que
existiera el script, se crea una sola vez con:

```sh
docker compose exec postgres sh -c 'createdb -U "$POSTGRES_USER" payments_test'
```

Para ejecutarlas:

```sh
docker compose exec -T api php artisan test --compact
```
