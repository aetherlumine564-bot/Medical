# MedCRM – Sistema de Administración Médica

## Descargar y ejecutar desde GitHub

Este repositorio contiene una aplicación PHP con MySQL. GitHub almacena el código;
GitHub Pages no ejecuta PHP ni proporciona la base MySQL de esta aplicación.

1. Instala PHP con `pdo_mysql` y un servidor MySQL.
2. Copia `config/config.example.php` como `config/config.php` y
   `config/oauth.example.php` como `config/oauth.php`.
3. Ajusta host, puerto, usuario y contraseña MySQL en `config/config.php`.
4. Para una base local nueva o vacía, ejecuta `php database/create_local.php`.
   El importador se detiene si `medical_crm` ya contiene tablas.
5. Ejecuta `php -S 127.0.0.1:8000 -t . database/local-router.php`.
6. Abre http://127.0.0.1:8000/.

La base incluida contiene datos ficticios y los roles Administrador, Doctor y
Paciente. Las cuentas de demostración se describen abajo; cambia sus contraseñas
antes de publicarlas en internet. La configuración real de cada instalación y
los avatares subidos por usuarios están excluidos del repositorio.

---

**Versión:** 1.0.0 &nbsp;|&nbsp; **Stack:** PHP 7.1+ · MySQL 5.7+ · HTML5 · CSS3 · JavaScript

Sistema CRM médico completo con gestión de pacientes, doctores, citas, horarios y reportes.
Los pacientes pueden registrarse desde la web pública y agendar citas directamente.

---

## ✅ Módulos incluidos

| Módulo | Funcionalidades |
|--------|----------------|
| **Registro público** | Pacientes se registran solos y agendan su primera cita |
| **Auth** | Login seguro, sesiones PHP, logout, CSRF tokens |
| **Roles y permisos** | CRUD completo por módulo (admin / doctor / paciente) |
| **Pacientes** | CRUD con tabs: cuenta / datos personales / info médica |
| **Doctores** | CRUD + toggle activo/inactivo + especialidades |
| **Citas** | Creación, confirmación, cancelación, completar; selección de slots disponibles |
| **Horarios** | Gestión semanal por doctor con duración de turnos |
| **Historial clínico** | Registro de consultas completadas por paciente |
| **Reportes** | Estadísticas, gráficos por mes y por especialidad |
| **Calendarios** | Vista mensual para admin y doctores |
| **Dashboards** | Panel personalizado por rol con estadísticas en tiempo real |

---

## 🚀 Instalación local (XAMPP / WAMP / Laragon)

### Requisitos
- PHP **7.1** o superior con extensiones: `pdo`, `pdo_mysql`, `mbstring`
- MySQL 5.7 / MariaDB 10.4 o superior
- Apache con `mod_rewrite` activado

### Pasos

1. **Copia la carpeta** `medical-crm/` dentro de `htdocs/` (XAMPP) o `www/` (WAMP).

2. **Abre el instalador** en tu navegador:
   ```
   http://localhost/medical-crm/install.php
   ```

3. **Sigue los 4 pasos** del asistente:
   - Paso 1: Verifica requisitos del servidor.
   - Paso 2: Ingresa los datos de conexión MySQL (`host`, `usuario`, `contraseña`, `nombre de BD`).
   - Paso 3: Haz clic en **"Instalar ahora"** — se crean las tablas y datos de ejemplo.
   - Paso 4: Define el correo y contraseña del administrador y confirma la URL base.

4. El sistema queda en:
   ```
   http://localhost/medical-crm
   ```

5. **Elimina `install.php`** cuando termines (el sistema te lo recuerda).

---

## 🌐 Subir a Hostinger (servidor público)

### Requisitos en Hostinger
- Plan Business o superior (PHP 7.4+ disponible).
- Acceso a **hPanel** (panel de control de Hostinger).

---

### Paso 1 – Crear la base de datos en hPanel

1. Ingresa a **hPanel → Bases de datos → Administrador de bases de datos MySQL**.
2. Haz clic en **"Crear nueva base de datos"**.
3. Anota el **nombre de la base de datos**, el **usuario** y la **contraseña** que generaste. Los necesitarás en el instalador.

> ⚠️ En Hostinger el nombre de la base de datos suele tener el prefijo de tu cuenta, por ejemplo: `u123456789_medical_crm`.

---

### Paso 2 – Subir los archivos por FTP

Puedes usar **FileZilla** (gratis) o el **Administrador de archivos** del hPanel.

**Con FileZilla:**

1. En hPanel → **FTP Accounts**, crea una cuenta FTP o usa la principal.
2. En FileZilla:
   - Host: `ftp.tudominio.com`
   - Usuario y contraseña: los de tu cuenta FTP
   - Puerto: `21`
3. Navega a `public_html/` en el servidor (columna derecha).
4. **Opción A – raíz del dominio:** Sube el *contenido* de la carpeta `medical-crm/` directamente a `public_html/`.
5. **Opción B – subdirectorio:** Sube la carpeta completa como `public_html/medcrm/`.

**Con el Administrador de archivos de hPanel:**

1. hPanel → **Administrador de archivos → public_html**.
2. Comprime `medical-crm/` en un `.zip` en tu computadora.
3. Sube el `.zip` y usa "Extraer" para descomprimirlo en `public_html/`.

---

### Paso 3 – Configurar el subdominio (opcional)

Si quieres acceder como `medcrm.tudominio.com`:

1. hPanel → **Subdominios → Crear subdominio**.
2. Subdominio: `medcrm`, Dominio: `tudominio.com`.
3. Raíz del documento: `public_html/medcrm` (o donde subiste los archivos).

---

### Paso 4 – Ejecutar el instalador web

En tu navegador abre:

```
https://tudominio.com/medcrm/install.php
```
*(o `https://medcrm.tudominio.com/install.php` si creaste subdominio)*

Sigue los 4 pasos del asistente:

| Paso | Qué hacer |
|------|-----------|
| 1 | Verificar requisitos — todo debe estar en verde. |
| 2 | Ingresar datos MySQL de Hostinger (host: `localhost`, nombre, usuario y contraseña de cPanel). |
| 3 | Clic en "Instalar ahora" — importa tablas y datos. |
| 4 | Definir admin, contraseña y **URL base** correcta (ej: `https://tudominio.com/medcrm`). |

---

### Paso 5 – Activar HTTPS

1. En hPanel → **SSL → Instalar SSL** → selecciona tu dominio y activa **Let's Encrypt** (gratis).
2. Edita `.htaccess` y descomenta las líneas de redirección HTTP→HTTPS:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

### Paso 6 – Eliminar el instalador

Después de instalar, elimina `install.php` desde el Administrador de archivos de hPanel.
El sistema también tiene un botón para hacerlo automáticamente al final del proceso.

---

### Posibles problemas en Hostinger

| Problema | Solución |
|----------|----------|
| Error 500 al abrir | Verifica que el `.htaccess` esté en la raíz del proyecto |
| Imágenes o CSS no cargan | Revisa que `APP_URL` en `config/config.php` tenga la URL correcta sin barra final |
| No conecta a la BD | Confirma usuario/contraseña/nombre de BD en hPanel → MySQL |
| "CREATE DATABASE" denegado | Normal en hosting compartido. Crea la BD manualmente desde hPanel antes del instalador |
| Error de permisos en `assets/img/avatars/` | En hPanel → Administrador de archivos → clic derecho en la carpeta → `chmod 755` |
| Sesión caduca rápido | En `config/config.php` ajusta `SESSION_LIFETIME` a más segundos |

---

## 📁 Estructura del proyecto

```
medical-crm/
├── admin/
│   ├── dashboard.php
│   ├── calendar.php
│   ├── reports.php
│   ├── roles/           (CRUD de roles y permisos)
│   ├── users/           (CRUD de usuarios)
│   ├── patients/        (CRUD de pacientes)
│   ├── doctors/         (CRUD de doctores)
│   ├── appointments/    (CRUD de citas)
│   └── schedules/       (Gestión de horarios)
├── doctor/
│   ├── dashboard.php
│   ├── appointments.php
│   ├── patients.php
│   ├── schedules.php
│   └── calendar.php
├── patient/
│   ├── dashboard.php
│   ├── history.php
│   └── appointments/
│       ├── index.php    (lista de citas)
│       ├── create.php   (nueva cita)
│       └── cancel.php   (cancelar cita)
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   ├── auth.php
│   └── functions.php
├── config/
│   └── config.php       (TODA la configuración: DB + APP_URL + sesión)
├── assets/
│   ├── css/style.css
│   ├── js/main.js
│   └── img/avatars/
├── database/
│   └── medical_crm.sql  (Schema + datos iniciales)
├── register.php          (Registro público de pacientes)
├── login.php
├── logout.php
├── index.php
├── profile.php
├── install.php           (Eliminar después de instalar)
├── delete_installer.php  (Eliminar después de instalar)
└── .htaccess
```

---

## 🔑 Credenciales de demostración

| Rol | Correo | Contraseña |
|-----|--------|-----------|
| Administrador | admin@medcrm.com | password |
| Doctor | doctor@medcrm.com | password |
| Paciente | paciente@medcrm.com | password |

> Los pacientes también pueden **auto-registrarse** en `register.php` y quedan activos de inmediato.

---

## 🔒 Seguridad implementada

- Tokens CSRF en todos los formularios POST
- Contraseñas hasheadas con `bcrypt`
- Sesiones seguras (`httponly`, `samesite=Strict`)
- Escape de output con `htmlspecialchars` en cada salida
- Control de acceso por rol y permiso en cada módulo
- Consultas con PDO preparadas (protección total contra SQL injection)
- `.htaccess` bloquea acceso a archivos `.sql`, `.log`, `.env`

---

## 📦 Librerías externas (CDN, sin instalación)

- **Font Awesome 6.5** – Íconos
- **DataTables 1.13** – Tablas interactivas con búsqueda y paginación
- **SweetAlert2 11** – Confirmaciones elegantes
- **Google Fonts Inter** – Tipografía moderna

---

## ⚙️ Configuración manual (sin instalador)

Si prefieres configurar sin el asistente:

1. Importa `database/medical_crm.sql` en tu base de datos.
2. Edita `config/config.php`:

```php
define('DB_HOST',    'localhost');
define('DB_PORT',    '3308');
define('DB_USER',    'tu_usuario');
define('DB_PASS',    'tu_contraseña');
define('DB_NAME',    'tu_base_de_datos');

define('APP_URL',    'https://tudominio.com/medcrm');  // sin barra final
```

3. Asegúrate de que `assets/img/avatars/` tenga permisos de escritura (`chmod 755`).

---

*MedCRM 1.0 — Sistema CRM médico modular desarrollado en PHP puro, sin frameworks.*
