# Proyecto Facturación (SaaS)

Sistema SaaS de facturación electrónica con arquitectura modular, multi-tenant y stack **Laravel + React (Inertia.js) + Tailwind CSS**, containerizado con Docker.

Este documento es la guía principal del equipo: estructura del proyecto, funcionamiento y pasos para levantar el entorno local.

---

## Tabla de contenidos

1. [Stack tecnológico](#stack-tecnológico)
2. [Guía de inicio rápido para el equipo](#-guía-de-inicio-rápido-para-el-equipo)
3. [Uso diario](#uso-diario)
4. [Servicios Docker](#servicios-docker)
5. [Conexión a la base de datos](#conexión-a-la-base-de-datos)
6. [Estructura del proyecto](#estructura-del-proyecto)
7. [Arquitectura y funcionamiento](#arquitectura-y-funcionamiento)
8. [Módulos del sistema](#módulos-del-sistema)
9. [Frontend (React + Inertia)](#frontend-react--inertia)
10. [Base de datos y multi-tenant](#base-de-datos-y-multi-tenant)
11. [Comandos útiles](#comandos-útiles)
12. [Flujo de trabajo en equipo](#flujo-de-trabajo-en-equipo)
13. [Herramientas de calidad de código](#herramientas-de-calidad-de-código)
14. [Solución de problemas](#solución-de-problemas)

---

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | React + Inertia.js + TypeScript + Vite |
| Estilos | Tailwind CSS |
| Base de datos | PostgreSQL 16 |
| Admin BD | pgAdmin 4 |
| Caché / Colas | Redis |
| Servidor web | Nginx |
| Contenedores | Docker + Docker Compose |
| Multi-tenant | Stancl Tenancy (por subdominio) |
| Calidad | Laravel Pint, PHPStan (Larastan) |

---

## 🚀 Guía de inicio rápido para el equipo

### Prerrequisitos en su PC

- Tener instalado **Git**.
- Tener instalado **Docker Desktop** (y ejecutándose).

---

### Paso 1: Clonar el repositorio

```bash
git clone https://github.com/RREEC-253/Proyecto-K-rito.git
cd proyecto001
```

---

### Paso 2: Crear el archivo de variables de entorno `.env`

Cada desarrollador debe tener su archivo `.env` local. Copia el archivo `.env.example`:

**Linux / macOS / Git Bash:**

```bash
cp .env.example .env
```

**PowerShell (Windows):**

```powershell
Copy-Item .env.example .env
```

Asegúrate de que contenga las credenciales de la base de datos y Redis de Docker:

```ini
DB_CONNECTION=pgsql
DB_HOST=postgres_db
DB_PORT=5432
DB_DATABASE=root
DB_USERNAME=root
DB_PASSWORD=root

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

> **Importante:** `DB_HOST=postgres_db` y `REDIS_HOST=redis` usan los **nombres de servicio** de Docker, porque Laravel corre dentro del contenedor `saas_app`.

---

### Paso 3: Construir y levantar los contenedores

Para construir las imágenes e iniciar todos los servicios:

```bash
docker compose up -d --build
```

Esto levanta: PostgreSQL, pgAdmin, PHP-FPM (Laravel), Nginx, Redis y Vite (Node).

---

### Paso 4: Instalar dependencias de PHP y Node.js

**1. Instalar paquetes de PHP (Composer):**

```bash
docker exec -it saas_app composer install
```

**2. Instalar paquetes de JavaScript (NPM):**

**Linux / macOS / Git Bash:**

```bash
docker exec -it saas_node npm install
```

### Paso 5: Clave de aplicación y migraciones

**1. Generar la clave de Laravel:**

```bash
docker exec -it saas_app php artisan key:generate
```

**2. Correr las migraciones de la base de datos:**

```bash
docker exec -it saas_app php artisan migrate
```

**3. Reiniciar los contenedores para que Node/Vite tome los cambios:**

```bash
docker compose up -d
```

---

### Paso 6: Verificar que todo funciona

| Servicio | URL / Acceso |
|----------|--------------|
| Aplicación Laravel | http://localhost:8080 |
| Vite (hot reload) | http://localhost:5173 |
| pgAdmin | http://localhost:5050 |
| PostgreSQL (desde tu PC) | `localhost:5433` |
| Redis (desde tu PC) | `localhost:6379` |

---

## Uso diario

En el día a día, únicamente necesitarás:

| Acción | Comando |
|--------|---------|
| **Iniciar a trabajar** | `docker compose up -d` |
| **Apagar el entorno** | `docker compose down` |
| **Ver logs** | `docker compose logs -f` |
| **Entrar al contenedor PHP** | `docker exec -it saas_app bash` |

---

## Servicios Docker

El archivo `docker-compose.yml` orquesta el entorno local en la red `saas-network`:

| Servicio | Contenedor | Imagen / Build | Puerto (host) | Descripción |
|----------|------------|----------------|---------------|-------------|
| `postgres_db` | `mi_proyecto_db` | `postgres:16` | `5433` | Base de datos PostgreSQL |
| `pgadmin` | `mi_proyecto_pgadmin` | `dpage/pgadmin4` | `5050` | Administrador web de PostgreSQL |
| `app` | `saas_app` | `docker/php/Dockerfile` | — | PHP 8.3-FPM + Laravel |
| `webserver` | `saas_webserver` | `nginx:alpine` | `8080` | Servidor web Nginx |
| `redis` | `saas_redis` | `redis:alpine` | `6379` | Caché y colas |
| `node` | `saas_node` | `node:20-alpine` | `5173` | Vite dev server (React) |

### Arquitectura de contenedores

```
                    ┌─────────────┐
  Browser ──8080──► │   Nginx     │
                    │ (webserver) │
                    └──────┬──────┘
                           │ fastcgi
                    ┌──────▼──────┐     ┌─────────────┐
                    │  PHP-FPM    │────►│ PostgreSQL  │
                    │  (saas_app) │     │ (postgres)  │
                    └──────┬──────┘     └─────────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌─────────┐  ┌─────────┐  ┌─────────┐
        │  Redis  │  │  Node   │  │ pgAdmin │
        │         │  │ (Vite)  │  │         │
        └─────────┘  └─────────┘  └─────────┘
```

### Archivos Docker del proyecto

| Archivo | Propósito |
|---------|-----------|
| `docker-compose.yml` | Orquestación de todos los servicios |
| `docker/php/Dockerfile` | Imagen PHP 8.3 con extensiones (pgsql, redis, zip, etc.) y Composer |
| `docker/nginx/conf.d/app.conf` | Configuración Nginx → PHP-FPM en `app:9000` |

### Volúmenes persistentes

```yaml
volumes:
  postgres_data:   # Datos de PostgreSQL (persisten tras docker compose down)
```

> Los volúmenes **no se eliminan** con `docker compose down`. Solo se borran con `docker compose down -v`.

### Comandos Docker frecuentes

```bash
docker compose up -d --build    # Construir y levantar
docker compose up -d            # Levantar (sin rebuild)
docker compose down             # Detener servicios
docker compose down -v          # Detener y borrar volúmenes (⚠️ pierde datos BD)
docker compose logs -f app      # Logs del contenedor PHP
docker compose up -d --force-recreate   # Forzar recreación tras cambios
```

---

## Conexión a la base de datos

### Mapeo de puertos

```yaml
ports:
  - "5433:5432"   # PostgreSQL: host 5433 → contenedor 5432
```

### Credenciales

| Campo | Valor |
|-------|-------|
| Usuario | `root` |
| Contraseña | `root` |
| Base de datos | `root` |

### Desde Laravel (contenedor `saas_app`)

Usar en `.env`:

```ini
DB_HOST=postgres_db
DB_PORT=5432
```

### Desde pgAdmin (http://localhost:5050)

**Login pgAdmin:**

| Campo | Valor |
|-------|-------|
| Email | `admin@admin.com` |
| Contraseña | `saraulitape` |

**Registrar servidor PostgreSQL:**

| Campo | Valor |
|-------|-------|
| Host | `postgres_db` |
| Puerto | `5432` |
| Usuario | `root` |
| Contraseña | `root` |

### Desde tu PC (DBeaver, TablePlus, etc.)

| Campo | Valor |
|-------|-------|
| Host | `localhost` |
| Puerto | `5433` |
| Usuario | `root` |
| Contraseña | `root` |

---

## Estructura del proyecto

```
proyecto001/
├── .github/                       # Flujos CI/CD y templates de Git
├── app/                           # Lógica del backend
│   ├── Console/
│   ├── Exceptions/
│   ├── Http/                      # Controladores globales y middleware base
│   ├── Models/                    # Modelos globales (ej. Tenant central)
│   ├── Providers/
│   └── Modules/                   # Arquitectura modular del proyecto
│       ├── Authentication/
│       │   ├── Controllers/
│       │   ├── DTOs/
│       │   ├── Requests/
│       │   ├── Services/
│       │   ├── Models/
│       │   └── Repositories/
│       ├── Users/
│       ├── Roles/
│       ├── Companies/
│       ├── Inventory/
│       ├── Purchases/
│       ├── Sales/
│       ├── Reports/
│       └── Billing/
├── bootstrap/                     # Arranque de Laravel
├── config/                        # Configuraciones (database, tenancy, etc.)
├── database/
│   ├── factories/
│   ├── migrations/                # Migraciones del sistema central
│   │   └── tenant/                # Migraciones por cliente (Stancl Tenancy)
│   └── seeders/                   # Datos iniciales (roles, permisos, etc.)
├── docker/
│   ├── nginx/
│   │   └── conf.d/
│   │       └── app.conf           # Configuración Nginx
│   ├── php/
│   │   └── Dockerfile             # Imagen PHP 8.3-FPM
│   └── postgres/                  # Scripts iniciales de BD (opcional)
├── resources/                     # Frontend (React + Inertia + Tailwind)
│   ├── css/
│   ├── js/
│   │   ├── Components/            # Componentes reutilizables
│   │   ├── Layouts/               # Plantillas (navbar, sidebar)
│   │   ├── Pages/                 # Vistas Inertia
│   │   │   ├── Auth/
│   │   │   ├── Inventory/
│   │   │   └── Sales/
│   │   ├── Types/                 # Interfaces TypeScript
│   │   └── app.tsx                # Entrada principal de Inertia + React
│   └── views/
│       └── app.blade.php          # Plantilla HTML raíz para Inertia
├── routes/
│   ├── web.php                    # Rutas web (Inertia)
│   ├── api.php                    # Rutas API externas
│   └── tenant.php                 # Rutas multi-tenant por subdominio
├── storage/                       # Logs, uploads y caché
├── tests/                         # Pruebas unitarias e integración
├── .env.example                   # Plantilla de variables de entorno
├── docker-compose.yml             # Orquestación de contenedores
├── phpstan.neon                   # Configuración PHPStan / Larastan
├── pint.json                      # Configuración Laravel Pint
├── tailwind.config.js             # Configuración Tailwind CSS
└── tsconfig.json                  # Configuración TypeScript
```

---

## Arquitectura y funcionamiento

### Visión general

```
┌─────────────┐     HTTP     ┌──────────────┐     SQL      ┌─────────────┐
│   React     │ ◄──────────► │   Laravel    │ ◄──────────► │  PostgreSQL │
│  (Inertia)  │   Inertia    │   (Módulos)  │              │             │
└─────────────┘              └──────┬───────┘              └─────────────┘
                                    │
                                    ▼
                             Multi-tenant
                          (subdominio → tenant)
```

1. El usuario accede a http://localhost:8080 (Nginx → PHP-FPM).
2. **Laravel** identifica el tenant según el subdominio (cuando Tenancy esté configurado).
3. **Inertia.js** conecta Laravel con **React** sin API REST tradicional para el frontend interno.
4. **Redis** gestiona caché y colas de trabajo.
5. **Vite** (contenedor `node`) compila el frontend con hot reload en el puerto 5173.
6. Cada módulo encapsula su propia lógica de negocio.

### Capas dentro de un módulo (ejemplo: Authentication)

| Carpeta | Responsabilidad |
|---------|-----------------|
| `Controllers/` | Recibe peticiones HTTP y devuelve respuestas |
| `Requests/` | Validación de datos entrantes (Form Requests) |
| `DTOs/` | Objetos de transferencia de datos entre capas |
| `Services/` | Lógica de negocio |
| `Models/` | Modelos Eloquent del módulo |
| `Repositories/` | Acceso a datos (opcional) |

### Flujo de una petición típica

```
Ruta (web.php / tenant.php)
    → Controller del módulo
        → Request (validación)
            → Service (lógica de negocio)
                → Model / Repository (datos)
                    → Respuesta Inertia → Página React
```

---

## Módulos del sistema

| Módulo | Descripción |
|--------|-------------|
| `Authentication` | Login, registro, recuperación de contraseña |
| `Users` | Gestión de usuarios |
| `Roles` | Roles y permisos |
| `Companies` | Empresas / configuración multi-tenant |
| `Inventory` | Control de inventario |
| `Purchases` | Compras |
| `Sales` | Ventas |
| `Reports` | Reportes |
| `Billing` | Facturación electrónica |

Cada módulo nuevo debe seguir la misma estructura interna que `Authentication`.

---

## Frontend (React + Inertia)

| Carpeta | Uso |
|---------|-----|
| `resources/js/Pages/` | Una página por vista (Inertia renderiza desde Laravel) |
| `resources/js/Components/` | Componentes reutilizables (tablas, modales, botones) |
| `resources/js/Layouts/` | Layouts compartidos (sidebar, navbar) |
| `resources/js/Types/` | Tipos e interfaces TypeScript |
| `resources/css/` | Estilos globales con Tailwind |
| `resources/views/app.blade.php` | HTML base que monta la app React |

Vite corre en el contenedor `saas_node` y expone el dev server en http://localhost:5173.

---

## Base de datos y multi-tenant

### Migraciones centrales vs tenant

| Ubicación | Propósito |
|-----------|-----------|
| `database/migrations/` | Tablas del sistema central (tenants, dominios, usuarios globales) |
| `database/migrations/tenant/` | Tablas que se crean **por cada cliente/empresa** |

### Comandos (dentro del contenedor)

```bash
docker exec -it saas_app php artisan migrate           # Migraciones centrales
docker exec -it saas_app php artisan tenants:migrate   # Migraciones por tenant
docker exec -it saas_app php artisan db:seed           # Seeders
```

---

## Comandos útiles

### Docker

```bash
docker compose up -d
docker compose down
docker compose logs -f
docker exec -it saas_app bash
```

### Laravel (vía contenedor)

```bash
docker exec -it saas_app php artisan migrate
docker exec -it saas_app php artisan route:list
docker exec -it saas_app php artisan make:model NombreModelo
docker exec -it saas_app composer require paquete/nombre
docker exec -it saas_app ./vendor/bin/pint
docker exec -it saas_app ./vendor/bin/phpstan analyse
```

### Frontend

```bash
# Instalar dependencias (una vez)
docker run --rm -v "${PWD}:/var/www" -w /var/www node:20-alpine npm install

# El contenedor saas_node ya ejecuta npm run dev automáticamente
# Para rebuild manual:
docker compose restart node
```

---

## Flujo de trabajo en equipo

### Primer día (nuevo desarrollador)

1. Clonar el repo
2. Copiar `.env.example` → `.env` y configurar credenciales Docker
3. `docker compose up -d --build`
4. `docker exec -it saas_app composer install`
5. Instalar dependencias npm (ver Paso 4 de la guía)
6. `docker exec -it saas_app php artisan key:generate`
7. `docker exec -it saas_app php artisan migrate`
8. `docker compose up -d`
9. Abrir http://localhost:8080 y http://localhost:5050 (pgAdmin)

### Qué se comparte por Git

| Sí se comparte | No se comparte |
|----------------|----------------|
| Código fuente | Datos de la BD local |
| `docker-compose.yml` | Archivo `.env` (secretos) |
| `docker/` (Dockerfile, nginx) | Volúmenes Docker locales |
| `.env.example` | Imágenes Docker (se descargan solas) |
| Migraciones y seeders | |

### Ramas (recomendado)

- `main` → código estable en producción
- `develop` → integración de features
- `feature/nombre-modulo` → trabajo individual por módulo

---

## Herramientas de calidad de código

| Herramienta | Archivo | Propósito |
|-------------|---------|-----------|
| Laravel Pint | `pint.json` | Formato y estilo PHP |
| PHPStan / Larastan | `phpstan.neon` | Análisis estático de tipos |
| TypeScript | `tsconfig.json` | Tipado en el frontend |
| Tailwind CSS | `tailwind.config.js` | Configuración de estilos |

Ejecutar antes de hacer commit:

```bash
docker exec -it saas_app ./vendor/bin/pint
docker exec -it saas_app ./vendor/bin/phpstan analyse
docker run --rm -v "${PWD}:/var/www" -w /var/www node:20-alpine npm run build
```

---

## Solución de problemas

### Error: `failed to connect to the docker API`

Docker Desktop no está en ejecución. Abre Docker Desktop y espera a que inicie completamente.

### Error: `Connection refused` en puerto 5433 desde pgAdmin

Desde pgAdmin usa host `postgres_db` y puerto **5432**, no 5433.

### La app no carga en http://localhost:8080

Verifica que los contenedores estén corriendo:

```bash
docker ps
```

Deben aparecer `saas_webserver` y `saas_app`.

### Vite no compila / cambios de frontend no se ven

```bash
docker compose restart node
docker compose logs -f node
```

### Los cambios en `docker-compose.yml` no se aplican

```bash
docker compose up -d --build --force-recreate
```

### Quiero empezar con una base de datos limpia

```bash
docker compose down -v
docker compose up -d --build
docker exec -it saas_app php artisan migrate
```

---

## Próximos pasos del proyecto

- [x] Instalar Laravel
- [x] Configurar contenedores PHP, Nginx, Redis y Node en Docker
- [ ] Integrar Inertia.js + React + Tailwind
- [ ] Configurar Stancl Tenancy (multi-tenant)
- [ ] Implementar módulo `Authentication`
- [ ] Definir migraciones centrales y tenant
- [ ] Configurar CI/CD en `.github/`
- [x] Actualizar `.env.example` con valores Docker por defecto

---

## Licencia

Pendiente de definir.
