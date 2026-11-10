# Futuro Digital

Sistema académico-administrativo para las sedes de **Sincelejo** y **Corozal**, que operan sobre una única base de datos institucional.

El proyecto implementa los requisitos funcionales (RF-01 a RF-24), las reglas de negocio (RN-01 a RN-19), los casos de uso (CU-01 a CU-26) y los requisitos no funcionales definidos en la propuesta académica. La interfaz está en español, es responsive y usa la zona horaria `America/Bogota`.

## Stack

- PHP 8.1+ con PDO y sentencias preparadas.
- MySQL 8 / MariaDB 10.4+.
- HTML5, CSS3 y Bootstrap 5 (assets locales, sin CDN).
- Arquitectura por capas: `Core`, `Services`, `Controllers`, `Views`.
- Sin frameworks pesados ni dependencias de Composer para ejecutar la aplicación.

## Módulos principales

| Módulo | Contenido |
| --- | --- |
| Seguridad | Login por número de documento, bloqueo por intentos, sesión con expiración, cambio obligatorio de contraseña, CSRF, permisos por rol y alcance por sede. |
| Personas | Estudiantes, docentes, acudientes y autorización de datos. |
| Oferta académica | Programas, plan de estudios, cursos, prerrequisitos, periodos, aulas, grupos y horarios. |
| Matrícula | Matrículas, inscripciones a grupos, cambio de grupo y cancelación con reglas de cupo y fechas. |
| Notas | Evaluaciones ponderadas, planillas, nota final y bajo rendimiento. |
| Asistencia | Sesiones, registro de asistencia, alertas y pérdida por inasistencia. |
| Cartera | Conceptos, tarifas, cargos, pagos, aplicaciones, comprobantes y estados de cuenta. |
| Alertas e informes | Alertas académicas y administrativas, informes en HTML/CSV. |
| Administración | Usuarios, roles, matriz de permisos, parámetros y auditoría. |
| Portal | Consulta del estudiante: matrícula, notas, asistencia y estado de cuenta. |

## Estructura del proyecto

```text
bootstrap/            Arranque, autoload y configuración inicial
config/               Configuración de la aplicación
database/             Esquema, datos base, migraciones y datos demo
docs/                 Documentación técnica
public/               Front controller, router y assets
routes/               Definición de rutas y permisos
src/Core/             Núcleo: sesión, auth, CSRF, router, auditoría, helpers
src/Services/         Lógica de negocio y reglas RN/RF
src/Controllers/      Controladores HTTP
storage/              Logs, caché y cargas
tests/                Pruebas unitarias e integración
views/                Plantillas PHP
```

## Requisitos

- PHP 8.1 o superior con extensiones `pdo_mysql`, `mbstring` y `openssl`.
- MySQL 8 o MariaDB 10.4 o superior.
- Apache con `mod_rewrite` (o el servidor embebido de PHP para desarrollo).

## Instalación

1. Clonar el repositorio y entrar en la carpeta:

   ```bash
   git clone https://github.com/JuanHerrera28/FUTURO_DIGITAL.git
   cd FUTURO_DIGITAL
   ```

2. Crear el archivo de entorno:

   ```bash
   copy .env.example .env
   ```

   Ajustar `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` según el servidor local. Para XAMPP suele funcionar `root` sin contraseña.

3. Crear la base de datos y cargar el esquema y los datos base:

   ```bash
   mysql -u root -p -e "CREATE DATABASE futuro_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p futuro_digital < database/schema.sql
   mysql -u root -p futuro_digital < database/seed.sql
   ```

   Las migraciones equivalentes están en `database/migrations/`:
   - `001_initial_schema.sql`
   - `002_base_data.sql`

4. (Opcional) Cargar datos de demostración:

   ```bash
   php database/demo.php
   ```

   Para reiniciar los datos académicos de demostración:

   ```bash
   php database/demo.php --fresh
   ```

5. Iniciar el servidor de desarrollo:

   ```bash
   php -S localhost:8000 -t public public/router.php
   ```

   Abrir <http://localhost:8000>.

## Inicio rápido en Windows

Si trabaja en Windows con XAMPP, puede arrancar todo con el script `iniciar.bat`:

```bat
iniciar.bat
```

También puede hacer doble clic sobre el archivo. El script:

1. Crea `.env` con una configuración local si aún no existe.
2. Inicia MariaDB/MySQL si no está en ejecución.
3. Crea la base de datos y carga `database/schema.sql` y `database/seed.sql` si es la primera vez.
4. Levanta el servidor embebido de PHP en el puerto configurado en `APP_URL`.
5. Abre el navegador en la aplicación.

Para detener el proyecto, cierre la ventana **Futuro Digital - Servidor PHP**.

Opciones disponibles:

| Opción | Descripción |
| --- | --- |
| `--demo` | Carga los datos de demostración si aún no existen. |
| `--fresh-demo` | Reinicia y vuelve a cargar los datos de demostración. |
| `--no-browser` | No abre el navegador al terminar. |
| `--no-db` | No intenta iniciar MariaDB/MySQL automáticamente. |
| `--no-pause` | No espera una tecla al finalizar (útil para automatización). |
| `--help` | Muestra la ayuda. |

Por defecto busca XAMPP en `C:\xampp`. Si está instalado en otra ruta, defina las variables `PHP_EXE`, `MYSQL_CLI`, `MYSQLD_EXE` y `MYSQL_INI` antes de ejecutar el script. También puede ajustar las credenciales de la base de datos en `.env`.

> El script está pensado para desarrollo local. En producción use Apache/Nginx, un usuario de base de datos dedicado y `APP_ENV=production` con `APP_DEBUG=false`.

## Credenciales

### Administrador inicial

| Usuario | Contraseña | Nota |
| --- | --- | --- |
| `admin` | `Admin123*` | Obliga cambio de contraseña en el primer ingreso. |

### Datos de demostración

Todos los usuarios demo usan la contraseña `Demo123*` y no obligan cambio.

| Rol | Usuario | Sede |
| --- | --- | --- |
| Docente | `2000000001` | Sincelejo |
| Docente | `2000000002` | Corozal |
| Docente | `2000000003` | Sincelejo |
| Estudiante | `3000000001` | Sincelejo |
| Secretaría | `4000000001` | Sincelejo |
| Secretaría | `4000000002` | Corozal |
| Coordinador Académico | `4000000003` | Global |
| Coordinador Administrativo | `4000000004` | Global |
| Director | `4000000005` | Global |

> Las credenciales de demostración son solo para entornos locales. No deben usarse en producción.

## Seguridad

- Contraseñas con `password_hash()` y verificación con `password_verify()`.
- Token CSRF obligatorio en cada petición `POST`, `PUT`, `PATCH` y `DELETE`.
- Escape de salida y validación del lado del servidor.
- Control de permisos por rol y aislamiento por sede (RN-19).
- Sesión con expiración por inactividad de 30 minutos.
- Bloqueo de cuenta tras 5 intentos fallidos.
- Sin registro público de usuarios.
- Contraseñas temporales aleatorias mostradas una sola vez.
- Auditoría de cambios críticos con valores anteriores y nuevos.
- Transacciones y bloqueos `SELECT ... FOR UPDATE` en cupos y pagos.
- Sin eliminación física de datos académicos o financieros.

## Reglas de negocio destacadas

- Una persona es la entidad base; estudiantes y docentes son especializaciones 1:1.
- La matrícula hereda la sede del estudiante y solo permite grupos de la misma sede y periodo.
- Solo puede existir un periodo con matrícula abierta a la vez.
- El cupo de un grupo se valida dentro de una transacción con bloqueo de fila.
- La nota final se calcula con evaluaciones ponderadas; los pesos deben sumar 100 %.
- La inasistencia genera alerta al 20 % y pérdida al superar el 25 %.
- Los pagos se aplican a cargos y pueden ser parciales; los comprobantes se numeran y se anulan por estado.
- Las moras y restricciones usan umbrales configurables en la tabla `parametros`.

## Pruebas

La suite usa PHPUnit 11 y una base de datos dedicada `futuro_digital_test`, que se crea y reinicia automáticamente desde `database/schema.sql` y `database/seed.sql`. Las pruebas nunca operan sobre la base configurada en `.env`. El nombre puede cambiarse con la variable de entorno `TEST_DB_DATABASE`.

Requisitos: PHP 8.1+ con PDO MySQL y MariaDB/MySQL en ejecución.

```powershell
# Instalar PHPUnit local (la carpeta /tools/ no se versiona)
Invoke-WebRequest https://phar.phpunit.de/phpunit-11.phar -OutFile tools/phpunit.phar

# Suite completa
C:\xampp\php\php.exe tools\phpunit.phar --no-coverage

# Solo unitarias o solo integración
C:\xampp\php\php.exe tools\phpunit.phar --testsuite Unit --no-coverage
C:\xampp\php\php.exe tools\phpunit.phar --testsuite Integration --no-coverage
```

La batería cubre las reglas de matrícula e inscripción, cupos, horarios, calificaciones, asistencia, pagos, periodos y seguridad. Incluye una prueba de concurrencia que lanza dos procesos PHP para comprobar que solo una inscripción obtiene el último cupo (RN-03) mediante `SELECT ... FOR UPDATE`.

## Documentación

La documentación técnica (modelo de datos, ambigüedades resueltas, trazabilidad RF/RN/CU y plan de trabajo) se encuentra en `docs/`.

## Estado

Proyecto en desarrollo activo. La aplicación base, los módulos académicos, financieros y administrativos, y los datos de demostración ya están implementados y verificados localmente.

## Autor

**Juan Herrera** — [@JuanHerrera28](https://github.com/JuanHerrera28)
