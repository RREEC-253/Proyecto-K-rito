# Proyecto Facturación (SaaS)

Sistema SaaS de facturación electrónica con arquitectura modular, multi-tenant y stack **Laravel + React (Inertia.js) + Tailwind CSS**, containerizado con Docker.

Este documento es la guía principal del equipo: estructura del proyecto, funcionamiento y pasos para levantar el entorno local.

---

## Tabla de contenidos

1. [Stack tecnológico](#stack-tecnológico)
2. [Requisitos previos](#requisitos-previos)
3. [Inicio rápido](#inicio-rápido)
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

---

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Backend | Laravel (PHP) |
| Frontend | React + Inertia.js + TypeScript |
| Estilos | Tailwind CSS |
| Base de datos | PostgreSQL 16 |
| Admin BD | pgAdmin 4 |
| Contenedores | Docker + Docker Compose |
| Multi-tenant | Stancl Tenancy (por subdominio) |
| Calidad | Laravel Pint, PHPStan (Larastan) |

---

## Requisitos previos

Antes de empezar, cada desarrollador debe tener instalado:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (en ejecución)
- [Git](https://git-scm.com/)
- Editor de código (recomendado: VS Code / Cursor)

> **Nota:** No es necesario instalar PostgreSQL ni pgAdmin en el sistema operativo. Todo corre dentro de Docker.

---

## Inicio rápido

### 1. Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd proyecto001
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
```

Edita `.env` con los valores de conexión (ver sección [Conexión a la base de datos](#conexión-a-la-base-de-datos)).

### 3. Levantar los contenedores

```bash
docker compose up -d
```

Docker descargará las imágenes (solo la primera vez), creará la red, los volúmenes y levantará los servicios definidos en `docker-compose.yml`.

### 4. Verificar que todo esté corriendo

```bash
docker ps
```

Deberías ver al menos:

- `mi_proyecto_db` (PostgreSQL)
- `mi_proyecto_pgadmin` (pgAdmin)

### 5. Instalar dependencias de la aplicación (cuando Laravel esté configurado)

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
```

---

## Servicios Docker

El archivo `docker-compose.yml` orquesta el entorno local:

| Servicio | Contenedor | Imagen | Puerto (host) | Descripción |
|----------|------------|--------|---------------|-------------|
| `postgres_db` | `mi_proyecto_db` | `postgres:16` | `5433` | Base de datos principal |
| `pgadmin` | `mi_proyecto_pgadmin` | `dpage/pgadmin4` | `80` | Interfaz web para administrar PostgreSQL |

### Comandos Docker frecuentes

```bash
# Levantar servicios en segundo plano
docker compose up -d

# Ver logs
docker compose logs -f

# Detener servicios
docker compose down

# Detener y eliminar volúmenes (⚠️ borra los datos de la BD)
docker compose down -v

# Recrear contenedores tras cambios en docker-compose.yml
docker compose up -d
```

### Volúmenes persistentes

```yaml
volumes:
  postgres_data:   # Datos de PostgreSQL
  pgadmin_data:    # Configuración de pgAdmin
```

Los volúmenes **persisten** aunque hagas `docker compose down`. Solo se eliminan con `docker compose down -v`.

---

## Conexión a la base de datos

### Mapeo de puertos (importante)

En `docker-compose.yml`:

```yaml
ports:
  - "5433:5432"
```

- **5433** → puerto en tu PC (Windows/macOS/Linux)
- **5432** → puerto interno de PostgreSQL dentro del contenedor

### Credenciales actuales

| Campo | Valor |
|-------|-------|
| Usuario | `root` |
| Contraseña | `root` |
| Base de datos | `root` |

### Desde pgAdmin (navegador → http://localhost)

**Login en pgAdmin:**

| Campo | Valor |
|-------|-------|
| Email | `admin@admin.com` |
| Contraseña | `saraulitape` |

**Registrar servidor PostgreSQL en pgAdmin:**

| Campo | Valor |
|-------|-------|
| Host | `postgres_db` |
| Puerto | `5432` |
| Usuario | `root` |
| Contraseña | `root` |
| Base de datos | `root` |

> Usa el **nombre del servicio** (`postgres_db`) y puerto **5432** porque pgAdmin corre dentro de la misma red Docker.

### Desde tu máquina (DBeaver, Laravel `.env`, etc.)

| Campo | Valor |
|-------|-------|
| Host | `localhost` |
| Puerto | `5433` |
| Usuario | `root` |
| Contraseña | `root` |
| Base de datos | `root` |

### Ejemplo de `.env` para Laravel

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5433
DB_DATABASE=root
DB_USERNAME=root
DB_PASSWORD=root
```

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
│   └── Modules/                   # Arquitectura modular del proyecto
│       ├── Authentication/
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
│   ├── nginx/                     # Configuración de Nginx
│   ├── php/                       # Dockerfile e ini de PHP
│   └── postgres/                  # Scripts iniciales de BD (opcional)
├── resources/                     # Frontend (React + Inertia + Tailwind)
│   ├── css/
│   ├── js/
│   │   ├── Components/            # Componentes reutilizables (botones, tablas, modales)
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
┌─────────────┐     HTTP      ┌──────────────┐     SQL      ┌─────────────┐
│   React     │ ◄──────────► │   Laravel    │ ◄──────────► │  PostgreSQL │
│  (Inertia)  │   Inertia    │   (Módulos)  │              │             │
└─────────────┘              └──────────────┘              └─────────────┘
                                    │
                                    ▼
                             Multi-tenant
                          (subdominio → tenant)
```

1. El usuario accede a la aplicación web (ej. `empresa1.tudominio.com`).
2. **Laravel** identifica el tenant (empresa/cliente) según el subdominio.
3. Las rutas en `tenant.php` sirven la lógica específica de cada cliente.
4. **Inertia.js** conecta Laravel con **React** sin API REST tradicional para el frontend interno.
5. Cada módulo encapsula su propia lógica de negocio.

### Capas dentro de un módulo (ejemplo: Authentication)

| Carpeta | Responsabilidad |
|---------|-----------------|
| `Controllers/` | Recibe peticiones HTTP y devuelve respuestas |
| `Requests/` | Validación de datos entrantes (Form Requests) |
| `DTOs/` | Objetos de transferencia de datos entre capas |
| `Services/` | Lógica de negocio |
| `Models/` | Modelos Eloquent del módulo |
| `Repositories/` | Acceso a datos (opcional, cuando se necesite abstraer queries) |

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

Cada módulo nuevo debe seguir la misma estructura interna que `Authentication` (`Controllers`, `Services`, `Models`, etc.).

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

### Convención de páginas

Las páginas en `Pages/` deben reflejar los módulos del backend:

- `Pages/Auth/` → pantallas de autenticación
- `Pages/Inventory/` → vistas de inventario
- `Pages/Sales/` → vistas de ventas

---

## Base de datos y multi-tenant

### Migraciones centrales vs tenant

| Ubicación | Propósito |
|-----------|-----------|
| `database/migrations/` | Tablas del sistema central (tenants, dominios, usuarios globales) |
| `database/migrations/tenant/` | Tablas que se crean **por cada cliente/empresa** |

### Seeders

Los seeders en `database/seeders/` cargan datos iniciales:

- Roles y permisos base
- Usuario administrador
- Configuración mínima del sistema

### Comandos (cuando Laravel + Tenancy estén instalados)

```bash
# Migraciones centrales
php artisan migrate

# Migraciones para todos los tenants
php artisan tenants:migrate

# Seeders
php artisan db:seed
```

---

## Comandos útiles

### Docker

```bash
docker compose up -d          # Levantar entorno
docker compose down           # Detener entorno
docker compose logs postgres_db   # Logs de PostgreSQL
docker compose logs pgadmin       # Logs de pgAdmin
```

### Laravel (backend)

```bash
php artisan serve             # Servidor de desarrollo
php artisan migrate           # Ejecutar migraciones
php artisan make:model        # Crear modelo
php artisan route:list        # Listar rutas
./vendor/bin/pint             # Formatear código PHP
./vendor/bin/phpstan analyse  # Análisis estático
```

### Frontend

```bash
npm install                   # Instalar dependencias
npm run dev                   # Servidor Vite en desarrollo
npm run build                 # Build de producción
```

---

## Flujo de trabajo en equipo

### Primer día (nuevo desarrollador)

1. Clonar el repo
2. Copiar `.env.example` → `.env`
3. Ejecutar `docker compose up -d`
4. Instalar dependencias (`composer install`, `npm install`)
5. Ejecutar migraciones y seeders
6. Abrir pgAdmin en http://localhost y verificar conexión a la BD
7. Iniciar la app (`npm run dev` + `php artisan serve` o contenedor PHP/Nginx cuando esté configurado)

### Al modificar `docker-compose.yml`

```bash
docker compose up -d
```

No hace falta `down` en la mayoría de casos. Usa `docker compose down -v` **solo** si quieres resetear la base de datos por completo.

### Qué se comparte por Git

| Sí se comparte | No se comparte |
|----------------|----------------|
| Código fuente | Datos de la BD local |
| `docker-compose.yml` | Archivo `.env` (secretos) |
| `.env.example` | Volúmenes Docker locales |
| Migraciones y seeders | Imágenes Docker (se descargan solas) |

### Ramas (recomendado)

- `main` → código estable en producción
- `develop` → integración de features
- `feature/nombre-modulo` → trabajo individual por módulo o funcionalidad

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
./vendor/bin/pint
./vendor/bin/phpstan analyse
npm run build
```

---

## Solución de problemas

### Error: `failed to connect to the docker API`

Docker Desktop no está en ejecución. Abre Docker Desktop y espera a que inicie completamente.

### Error: `Connection refused` en puerto 5433 desde pgAdmin

Estás usando el puerto incorrecto. Desde pgAdmin usa host `postgres_db` y puerto **5432**.

### Los cambios en `docker-compose.yml` no se aplican

```bash
docker compose up -d --force-recreate
```

### Quiero empezar con una base de datos limpia

```bash
docker compose down -v
docker compose up -d
```

---

## Próximos pasos del proyecto

- [ ] Instalar Laravel en la raíz del proyecto
- [ ] Configurar contenedores PHP y Nginx en `docker/`
- [ ] Integrar Inertia.js + React + Tailwind
- [ ] Configurar Stancl Tenancy (multi-tenant)
- [ ] Implementar módulo `Authentication`
- [ ] Definir migraciones centrales y tenant
- [ ] Configurar CI/CD en `.github/`

---

## Licencia

Pendiente de definir.
