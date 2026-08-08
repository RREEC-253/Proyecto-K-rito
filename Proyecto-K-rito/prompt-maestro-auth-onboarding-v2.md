# Plan de Implementación: Autenticación & Onboarding Multiempresa
## Prompt Maestro para Agente AI / Desarrollador — CORE v1.1 (sincronizado con DB real)

**Stack:** Laravel 13 | PostgreSQL 15+ | Docker & Docker Compose | Patrón MVVM
**Objetivo:** Implementar el Módulo de Autenticación, Onboarding de Empresa (Tenant), RBAC básico,
gestión de sesiones/tokens y auditoría, sobre el esquema de base de datos **ya migrado**.

> Este documento reemplaza a la v1.0. Se corrigieron nombres de columnas, se documentaron todas las
> tablas ya existentes en la DB, y se resolvieron ambigüedades de arquitectura (identificación de
> tenant en login, mecanismo de tokens, aislamiento multiempresa).

---

## 1. Contexto y Alcance

Este módulo es la base del sistema SaaS multiempresa. Permite que una empresa (tenant) se registre
junto a su primer usuario administrador, y provee: login seguro con protección de fuerza bruta,
refresh de sesión, logout, recuperación de contraseña, verificación de email, aislamiento estricto
por `company_id`, y auditoría de eventos sensibles.

**Importante:** las migraciones abajo ya están aplicadas en la base de datos. El agente **no debe
crear nuevas migraciones que dupliquen o alteren estas tablas** salvo que se indique explícitamente
(ver sección 3.1, decisión pendiente sobre `slug`).

---

## 2. Esquema de Base de Datos (estado real, ya migrado)

### 2.1 `companies`
| Campo | Tipo | Notas |
|---|---|---|
| id | UUID PK | `gen_random_uuid()` |
| name | VARCHAR(150) | not null |
| business_name | VARCHAR(200) | nullable |
| document_type | VARCHAR(10) | nullable |
| document_number | VARCHAR(20) | nullable, indexado |
| email | VARCHAR(150) | nullable |
| phone | VARCHAR(20) | nullable |
| address | TEXT | nullable |
| logo | TEXT | nullable |
| timezone | VARCHAR(50) | default `America/Lima` |
| settings | JSONB | nullable |
| status | VARCHAR(20) | default `active` (`active\|trial\|suspended\|cancelled`), indexado |
| deleted_at | TIMESTAMP | soft delete |
| created_at / updated_at | TIMESTAMP | |

### 2.2 `users`
| Campo | Tipo | Notas |
|---|---|---|
| id | UUID PK | `gen_random_uuid()` |
| company_id | UUID FK → companies.id | cascade on delete, indexado |
| username | VARCHAR(50) | |
| email | VARCHAR(150) | |
| **password** | TEXT | ⚠️ el campo real se llama `password`, **no** `password_hash` (compatibilidad con `Auth`/`Hash` nativo de Laravel) |
| first_name / last_name | VARCHAR(100) | nullable |
| phone | VARCHAR(20) | nullable |
| avatar | TEXT | nullable |
| status | BOOLEAN | default true |
| email_verified | BOOLEAN | default false |
| must_change_password | BOOLEAN | default false |
| failed_login_attempts | SMALLINT | default 0 |
| locked_until | TIMESTAMP | nullable |
| last_login | TIMESTAMP | nullable |
| deleted_at | TIMESTAMP | soft delete |
| created_at / updated_at | TIMESTAMP | |
| **Índices únicos** | | `(company_id, username)`, `(company_id, email)` — **el email NO es único globalmente**, solo dentro del tenant. Ver sección 3.1. |

### 2.3 `roles`
`id` UUID PK · `company_id` UUID FK nullable (nullable = rol global de sistema, ej. SuperAdmin) ·
`name` · `description` · `is_system` bool · `is_editable` bool · `is_deletable` bool · timestamps.
Índices: `company_id`, `(company_id, name)`.

### 2.4 `permissions`
`id` UUID PK · `module` · `action` · `code` (unique) · `description`. Índice: `(module, action)`.

### 2.5 `role_permissions` (pivot)
`role_id` FK · `permission_id` FK · `created_at`. PK compuesta.

### 2.6 `user_roles` (pivot)
`user_id` FK · `role_id` FK · `created_at`. PK compuesta.

### 2.7 `sessions`
`id` UUID PK · `user_id` FK · `refresh_token_hash` TEXT · `ip_address` · `user_agent` ·
`last_activity_at` · `expires_at` · `revoked_at` nullable · `created_at`.
→ Confirma que el mecanismo de sesión es **token propio con refresh (hash guardado en DB)**,
no Laravel Sanctum estándar ni JWT stateless puro. Ver sección 3.2.

### 2.8 `user_tokens` (tabla unificada)
`id` UUID PK · `user_id` FK · `type` (`password_reset\|email_verification\|invite`) ·
`token_hash` TEXT · `expires_at` · `used_at` nullable · `created_at`.
→ Ya soporta reset de contraseña, verificación de email e invitaciones. El prompt v1.0 no
contemplaba estos flujos; ahora son tareas explícitas (5, 6, 7).

### 2.9 `audit_logs`
`id` UUID PK · `company_id` FK nullable (set null) · `user_id` FK nullable (set null) ·
`module` · `action` · `entity` · `entity_id` UUID · `old_values` JSONB · `new_values` JSONB ·
`ip_address` · `user_agent` · `created_at`.

---

## 3. Decisiones de Arquitectura (antes ambiguas, ahora resueltas)

### 3.1 Identificación del tenant en login ⚠️ requiere una migración adicional pequeña
Como `email` es único **por empresa** y no global, el login no puede resolver el usuario con solo
`email + password`. Se define el siguiente contrato:

- Agregar campo `slug` VARCHAR(60) UNIQUE NOT NULL a `companies` (una migración adicional,
  autogenerado a partir de `name` en el registro).
- El login recibe `company_slug`, `identifier` (username o email) y `password`.
- Alternativa aceptable si el frontend usa subdominios (`empresa.tuapp.com`): resolver el tenant
  por el subdominio en middleware y no pedir `company_slug` en el body. **El agente debe preguntar
  cuál de las dos estrategias usa el frontend antes de implementar**, ya que cambia el contrato de
  la API. Por defecto, si no hay indicación, implementar la variante de `company_slug` en el body
  (más simple para MVP sin infraestructura de subdominios).

### 3.2 Mecanismo de tokens
Confirmado por la tabla `sessions`: se usa un **access token de corta duración (JWT o token opaco,
firmado, ~15 min) + refresh token de larga duración persistido con hash en `sessions.refresh_token_hash`**.
- El refresh token nunca se guarda en texto plano (usar `hash('sha256', $token)` antes de persistir).
- `POST /api/v1/auth/refresh` valida el hash, `expires_at > now()` y `revoked_at IS NULL`, y rota el
  refresh token (invalida el anterior, emite uno nuevo) para mitigar reuso de tokens robados.

### 3.3 Aislamiento multiempresa: Global Scope, no `where` manual
La regla del prompt v1.0 (`User::where('company_id', ...)`) es frágil porque depende de que cada
desarrollador la repita en cada query. Reemplazar por un **Global Scope de Eloquent**:

```php
// app/Models/Concerns/BelongsToCompany.php
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where($builder->getModel()->getTable() . '.company_id', auth()->user()->company_id);
            }
        });

        static::creating(function ($model) {
            if (auth()->check() && empty($model->company_id)) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
```

Aplicar este trait a **todos los modelos con `company_id`** (`User`, `Role` cuando no es global, etc.).
Esto hace el aislamiento automático e imposible de olvidar por accidente.

---

## 4. Estructura de Carpetas (Laravel 13 por defecto, sin paquetes de arquitectura adicionales)

El proyecto usa el **skeleton estándar generado por `laravel new`**, sin Repositorios, DDD ni
paquetes de arquitectura de terceros. Laravel no define por defecto dónde van los Services, así
que se fija explícitamente aquí para que el agente no improvise una carpeta distinta en cada corrida:

```
app/
  Http/
    Controllers/
      Api/V1/
        AuthController.php
        OnboardingController.php
    Requests/
      Auth/
        LoginRequest.php
        RefreshTokenRequest.php
        ForgotPasswordRequest.php
        ResetPasswordRequest.php
      Onboarding/
        RegisterCompanyRequest.php
    Resources/
      UserResource.php
      CompanyResource.php
    Middleware/
      EnsureCompanyIsActive.php
  Models/
    Company.php
    User.php
    Role.php
    Permission.php
    Session.php
    UserToken.php
    AuditLog.php
    Concerns/
      BelongsToCompany.php      // trait del Global Scope (sección 3.3)
  Services/
    AuthService.php
    OnboardingService.php
    TokenService.php
    AuditService.php
database/
  migrations/                   // ya existen 9 migraciones, ver sección 2
  seeders/
    RolesTableSeeder.php        // roles de sistema (SuperAdmin, Owner)
    PermissionsTableSeeder.php
routes/
  api.php                       // grupo prefix('v1'), sin archivos separados por módulo
```

Reglas para el agente:
- **`app/Services` no existe por defecto en Laravel 13 — créala.** Ahí van las clases de lógica
  de negocio (`AuthService`, `OnboardingService`, etc.), inyectadas por constructor en los
  controladores. Los controladores solo deben orquestar: validar (via FormRequest), llamar al
  Service, devolver el Resource.
- Usar `FormRequest` para **toda** validación de entrada — no validar inline con `$request->validate()`
  dentro del controlador.
- Usar `API Resource` (`JsonResource`) para **toda** respuesta que devuelva datos de modelo —
  no serializar el modelo Eloquent directamente (evita filtrar `password`, `deleted_at`, etc.).
- Rutas de este módulo van en `routes/api.php` bajo `Route::prefix('v1')->group(...)`, no en
  archivos nuevos como `routes/auth.php` a menos que se indique lo contrario.
- No crear carpetas `app/Repositories`, `app/Domain` ni patrones adicionales — mantener el
  skeleton por defecto de Laravel 13 con la única adición de `app/Services`.

---

## 5. Arquitectura MVVM (sin cambios respecto a v1.0)

- **Model (Backend):** Modelos Eloquent con `HasUuids`, `SoftDeletes`, trait `BelongsToCompany`
  donde aplique. Servicios: `AuthService`, `OnboardingService`, `TokenService`, `AuditService`.
  Usar `FormRequest` para validación de entrada y `API Resource` para dar forma a las respuestas
  (mantiene el ViewModel del frontend desacoplado del esquema de DB).
- **ViewModel (Frontend):** Store reactivo (Pinia/Vuex/composables) con credenciales, errores de
  validación, access token, estado de sesión y perfil del usuario autenticado.
- **View (Frontend UI):** Formularios de login, registro de empresa, reseteo de contraseña,
  verificación de email — enlace bidireccional puro, sin lógica de negocio.

---

## 6. Especificaciones de Tareas

### Tarea 1 — Onboarding y Registro Transaccional
`POST /api/v1/onboarding/register`, dentro de `DB::transaction()`:
1. Generar `slug` único a partir de `name` (ver 3.1).
2. Crear `companies` con `status = 'active'`.
3. Crear `users` (admin) asociado al `company_id`, `password` hasheado con `Hash::make()`,
   `email` y `username` **normalizados a minúsculas**.
4. Asignar rol de administrador vía `user_roles`, usando un rol `is_system = true` predefinido
   (sembrado por seeder) o creando el rol "Owner" del tenant si el modelo de negocio requiere
   roles custom por empresa. **No condicionar esto a "si existe la tabla": la tabla ya existe y
   debe usarse siempre.**
5. Registrar evento en `audit_logs` (`module: onboarding`, `action: company_registered`).
6. Validar unicidad de `(company_id, email)` y `(company_id, username)` — ya reforzada por índice
   de DB, pero debe devolverse un error 422 legible, no un 500 por violación de constraint.

### Tarea 2 — Login Seguro
`POST /api/v1/auth/login`, manejado por `AuthService`:
1. Resolver tenant por `company_slug` (o subdominio, según 3.1).
2. Buscar usuario por `(company_id, username_o_email normalizado)`.
3. Validar en orden: `deleted_at IS NULL` → `company.status = 'active'` → `user.status = true` →
   `locked_until` (si `> now()`, responder `429 Too Many Requests` con `retry_after`).
4. Si `locked_until` ya pasó, **resetear `failed_login_attempts = 0`** antes de evaluar la
   contraseña (evita que el contador siga sumando indefinidamente tras expirar el bloqueo).
5. Verificar contraseña con `Hash::check()`.
   - Si falla: incrementar `failed_login_attempts` **usando `lockForUpdate()`** dentro de una
     transacción corta, para evitar condiciones de carrera en intentos concurrentes. Si llega a
     5, setear `locked_until = now()->addMinutes(15)`. Registrar intento fallido en `audit_logs`.
   - Si es correcto: `failed_login_attempts = 0`, `locked_until = null`, `last_login = now()`.
     Emitir access token + refresh token (crear registro en `sessions`). Registrar login exitoso
     en `audit_logs`.
6. Respuesta nunca debe revelar si falló por usuario inexistente o contraseña incorrecta
   (mismo mensaje genérico) para evitar enumeración de usuarios.

### Tarea 3 — Refresh y Logout
- `POST /api/v1/auth/refresh`: valida `refresh_token_hash`, `expires_at`, `revoked_at IS NULL`;
  rota el token (revoca el actual, crea uno nuevo). Devuelve nuevo access + refresh token.
- `POST /api/v1/auth/logout`: setea `revoked_at = now()` en la sesión actual.
- `POST /api/v1/auth/logout-all` (opcional pero recomendado): revoca todas las sesiones activas
  del usuario — útil tras cambio de contraseña o sospecha de compromiso.

### Tarea 4 — Middleware / Global Scope de Aislamiento Multiempresa
Implementar según sección 3.3. Además, agregar un middleware `EnsureCompanyIsActive` que corte
con `403` cualquier request si `auth()->user()->company->status !== 'active'`, incluso con token
válido (cubre el caso de una empresa suspendida después de que sus usuarios ya iniciaron sesión).

### Tarea 5 — Recuperación de Contraseña
- `POST /api/v1/auth/forgot-password`: genera token en `user_tokens` (`type: password_reset`),
  hash del token guardado, expiración corta (ej. 30 min). Enviar por correo (fuera de alcance de
  este módulo el proveedor de email, pero el hook debe quedar listo).
- `POST /api/v1/auth/reset-password`: valida `token_hash`, `expires_at`, `used_at IS NULL`;
  actualiza `password`, marca token como usado, revoca todas las sesiones activas del usuario.

### Tarea 6 — Verificación de Email
- `POST /api/v1/auth/send-verification`: genera token `type: email_verification` en `user_tokens`.
- `POST /api/v1/auth/verify-email`: valida token, setea `users.email_verified = true`.

### Tarea 7 — Auditoría
Todo evento de: registro de empresa, login exitoso/fallido, bloqueo por fuerza bruta, cambio de
contraseña, revocación de sesión, debe insertarse en `audit_logs` con `ip_address` y `user_agent`
del request.

---

## 7. Contrato de API (resumen)

```
POST /api/v1/onboarding/register
  body: { company: {name, business_name?, document_type?, document_number?, email?, phone?},
          admin: {username, email, password, first_name?, last_name?} }
  201 -> { company: {...}, user: {...} }
  422 -> { errors: {...} }

POST /api/v1/auth/login
  body: { company_slug, identifier, password }
  200 -> { access_token, refresh_token, expires_in, user: {...} }
  401 -> { message: "Credenciales inválidas" }
  429 -> { message: "Cuenta bloqueada", retry_after: <segundos> }
  403 -> { message: "Empresa suspendida" }

POST /api/v1/auth/refresh
  body: { refresh_token }
  200 -> { access_token, refresh_token, expires_in }
  401 -> { message: "Token inválido o expirado" }

POST /api/v1/auth/logout
  headers: Authorization: Bearer <access_token>
  204

POST /api/v1/auth/forgot-password
  body: { company_slug, email }
  200 -> { message: "Si el correo existe, se enviará un enlace" }  // no revelar existencia

POST /api/v1/auth/reset-password
  body: { token, password }
  200 -> { message: "Contraseña actualizada" }
  422 -> { message: "Token inválido o expirado" }
```

---

## 8. Notas para el Agente AI

- Ejecutar todo dentro de contenedores: `docker compose exec app ...`.
- **No** crear migraciones que dupliquen las tablas de la sección 2; solo la migración adicional
  de `slug` en `companies` (sección 3.1) requiere confirmación antes de crearse.
- Usar `gen_random_uuid()` nativo de PostgreSQL para nuevas tablas, consistente con lo ya migrado.
- Usar `FormRequest` para toda validación de entrada y `API Resource` para toda respuesta —
  mantiene el contrato de la sección 6 estable aunque cambie el esquema interno.
- Antes de implementar el login, **confirmar con el equipo si el tenant se resuelve por
  `company_slug` en el body o por subdominio** (sección 3.1) — esto cambia el contrato de la API
  y no debe asumirse silenciosamente.
