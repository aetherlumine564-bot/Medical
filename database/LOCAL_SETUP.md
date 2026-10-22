# Base local para MySQL Workbench

Conexión identificada: Local instance MySQL80, localhost:3307.
Base del proyecto: medical_crm. La conexión de PHP está en config/config.php.

## Importación sin reemplazar datos

Ejecutar desde la raíz del proyecto: `php database/create_local.php`.
El importador usa las credenciales de config/config.php. También admite
MEDCRM_DB_USER y MEDCRM_DB_PASSWORD como variables de entorno; no pasar
contraseñas como argumentos. Tras una importación correcta guarda las credenciales
en config/config.php. No compartir ese archivo si contiene una contraseña real.

El proceso se detiene si medical_crm contiene tablas. Si una importación falla
parcialmente, revisar las tablas antes de volver a ejecutar: no las borra ni repara
automáticamente. El archivo medical_crm.sql original sí contiene DROP TABLE;
no ejecutarlo sobre una base con información que se deba conservar.

Después de importar, actualizar Schemas en Workbench para ver medical_crm.

## Roles de la aplicación incluidos

- Administrador: acceso total; 22 permisos; admin@medcrm.com.
- Doctor: pacientes (ver), citas (ver/crear/editar), horarios (ver/gestionar);
  6 permisos; doctor@medcrm.com y laura@medcrm.com.
- Paciente: citas (ver/crear); 2 permisos;
  paciente@medcrm.com y roberto@medcrm.com.

Son roles de la aplicación, almacenados en roles y role_permissions.
Las cuentas de demostración usan la contraseña `password`, definida por el
proyecto original. Los registros públicos reciben el rol Paciente.
Las cuentas sin rol válido no pueden iniciar sesión. Los roles personalizados
solo reciben los permisos asignados explícitamente.

La URL APP_URL existente debe ajustarse al servidor web donde se publique esta
carpeta; crear la base no inicia Apache ni publica el sitio.