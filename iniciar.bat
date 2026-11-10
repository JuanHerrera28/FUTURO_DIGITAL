@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul 2>&1
title Futuro Digital - Inicio
cd /d "%~dp0"

rem ============================================================
rem  Futuro Digital - arranque automatico en Windows
rem  Inicia MariaDB/MySQL si hace falta, prepara la base de
rem  datos y levanta el servidor embebido de PHP.
rem ============================================================

set "ENV_FILE=.env"
set "APP_HOST=localhost"
set "APP_PORT=8000"
set "APP_ENV=local"
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_DATABASE=futuro_digital"
set "DB_USERNAME=root"
set "DB_PASSWORD="
set "START_DB=1"
set "OPEN_BROWSER=1"
set "RUN_DEMO=0"
set "FRESH_DEMO=0"
set "NO_PAUSE=0"
if not defined TEMP set "TEMP=%CD%\storage"

:parse_args
if "%~1"=="" goto args_done
if /i "%~1"=="--help" goto usage
if /i "%~1"=="-h" goto usage
if /i "%~1"=="--no-browser" set "OPEN_BROWSER=0"
if /i "%~1"=="--no-db" set "START_DB=0"
if /i "%~1"=="--no-pause" set "NO_PAUSE=1"
if /i "%~1"=="--demo" set "RUN_DEMO=1"
if /i "%~1"=="--fresh-demo" (
    set "RUN_DEMO=1"
    set "FRESH_DEMO=1"
)
shift
goto parse_args
:args_done

rem ------------------------------------------------------------
rem  Archivo .env
rem ------------------------------------------------------------
if not exist "%ENV_FILE%" (
    echo [1/6] No existe .env. Creando configuracion local...
    (
        echo # Futuro Digital - configuracion local generada por iniciar.bat
        echo APP_NAME="Futuro Digital"
        echo APP_ENV=local
        echo APP_DEBUG=true
        echo APP_URL=http://localhost:8000
        echo APP_TIMEZONE=America/Bogota
        echo.
        echo DB_HOST=127.0.0.1
        echo DB_PORT=3306
        echo DB_DATABASE=futuro_digital
        echo DB_USERNAME=root
        echo DB_PASSWORD=
        echo.
        echo SESSION_NAME=futuro_digital_sid
        echo SESSION_LIFETIME=30
        echo SESSION_SECURE=false
    ) > "%ENV_FILE%"
) else (
    echo [1/6] Leyendo configuracion de .env...
)

for /f "usebackq tokens=1,* delims==" %%A in ("%ENV_FILE%") do (
    set "ENV_KEY=%%A"
    set "ENV_VALUE=%%B"
    if defined ENV_VALUE set "ENV_VALUE=!ENV_VALUE:"=!"
    if /i "!ENV_KEY!"=="APP_URL" set "APP_URL=!ENV_VALUE!"
    if /i "!ENV_KEY!"=="APP_ENV" set "APP_ENV=!ENV_VALUE!"
    if /i "!ENV_KEY!"=="DB_HOST" set "DB_HOST=!ENV_VALUE!"
    if /i "!ENV_KEY!"=="DB_PORT" set "DB_PORT=!ENV_VALUE!"
    if /i "!ENV_KEY!"=="DB_DATABASE" set "DB_DATABASE=!ENV_VALUE!"
    if /i "!ENV_KEY!"=="DB_USERNAME" set "DB_USERNAME=!ENV_VALUE!"
    if /i "!ENV_KEY!"=="DB_PASSWORD" set "DB_PASSWORD=!ENV_VALUE!"
)

rem El puerto se toma de APP_URL para que los enlaces internos
rem sigan apuntando al servidor correcto.
if defined APP_URL (
    set "URL_TMP=!APP_URL:http://=!"
    set "URL_TMP=!URL_TMP:https://=!"
    for /f "tokens=1,2 delims=:/" %%A in ("!URL_TMP!") do (
        if not "%%B"=="" set "APP_PORT=%%B"
    )
)

if /i not "!APP_ENV!"=="local" (
    echo        ADVERTENCIA: APP_ENV=!APP_ENV!. Revise APP_URL en .env si es un entorno local.
)

rem ------------------------------------------------------------
rem  Localizar PHP y las herramientas de MySQL/MariaDB
rem ------------------------------------------------------------
if not defined PHP_EXE (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_EXE=C:\xampp\php\php.exe"
    ) else (
        for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
    )
)
if not defined PHP_EXE goto php_missing

"%PHP_EXE%" -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);" >nul 2>&1
if errorlevel 1 echo        ADVERTENCIA: PHP no tiene habilitada la extension pdo_mysql.

if not defined MYSQL_CLI (
    if exist "C:\xampp\mysql\bin\mysql.exe" (
        set "MYSQL_CLI=C:\xampp\mysql\bin\mysql.exe"
    ) else (
        for /f "delims=" %%P in ('where mysql 2^>nul') do if not defined MYSQL_CLI set "MYSQL_CLI=%%P"
    )
)
if not defined MYSQL_CLI goto mysql_missing

if not defined MYSQLD_EXE (
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        set "MYSQLD_EXE=C:\xampp\mysql\bin\mysqld.exe"
    ) else (
        for /f "delims=" %%P in ('where mysqld 2^>nul') do if not defined MYSQLD_EXE set "MYSQLD_EXE=%%P"
    )
)

if not defined MYSQL_INI (
    if exist "C:\xampp\mysql\bin\my.ini" set "MYSQL_INI=C:\xampp\mysql\bin\my.ini"
)

rem ------------------------------------------------------------
rem  MariaDB / MySQL
rem ------------------------------------------------------------
echo [2/6] Verificando el motor de base de datos...
if "!START_DB!"=="1" (
    set "DB_RUNNING=0"
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul && set "DB_RUNNING=1"
    if "!DB_RUNNING!"=="0" tasklist /FI "IMAGENAME eq mariadbd.exe" 2>nul | find /I "mariadbd.exe" >nul && set "DB_RUNNING=1"
    if "!DB_RUNNING!"=="0" (
        if not defined MYSQLD_EXE goto mysqld_missing
        echo        Iniciando MariaDB/MySQL...
        if defined MYSQL_INI (
            start "Futuro Digital - MariaDB" /MIN "%MYSQLD_EXE%" --defaults-file="%MYSQL_INI%" --standalone
        ) else (
            start "Futuro Digital - MariaDB" /MIN "%MYSQLD_EXE%" --standalone
        )
    ) else (
        echo        El motor ya esta en ejecucion.
    )
) else (
    echo        Inicio automatico del motor omitido: --no-db.
)

set "MYSQL_AUTH=--default-character-set=utf8mb4 --host=%DB_HOST% --port=%DB_PORT% --user=%DB_USERNAME%"
set "MYSQL_PWD=%DB_PASSWORD%"

echo [3/6] Esperando la base de datos...
set /a DB_TRIES=0
:wait_db
set /a DB_TRIES+=1
"%MYSQL_CLI%" %MYSQL_AUTH% --connect-timeout=2 -e "SELECT 1;" >nul 2>&1
if not errorlevel 1 goto db_ready
if !DB_TRIES! GEQ 30 goto db_error
ping -n 2 127.0.0.1 >nul 2>&1
goto wait_db
:db_ready

rem ------------------------------------------------------------
rem  Base de datos y estructura
rem ------------------------------------------------------------
echo [4/6] Verificando la base de datos %DB_DATABASE%...
"%MYSQL_CLI%" %MYSQL_AUTH% -e "CREATE DATABASE IF NOT EXISTS `%DB_DATABASE%` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
if errorlevel 1 (
    "%MYSQL_CLI%" %MYSQL_AUTH% --database="%DB_DATABASE%" -e "SELECT 1;" >nul 2>&1
    if errorlevel 1 goto db_error
)

set "HAS_TABLES=0"
"%MYSQL_CLI%" %MYSQL_AUTH% --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='%DB_DATABASE%' AND table_name='usuarios';" > "%TEMP%\fd_has_tables.txt" 2>nul
for /f "usebackq delims=" %%C in ("%TEMP%\fd_has_tables.txt") do set "HAS_TABLES=%%C"
del "%TEMP%\fd_has_tables.txt" >nul 2>&1

if "%HAS_TABLES%"=="0" (
    echo        Creando estructura y datos base...
    "%MYSQL_CLI%" %MYSQL_AUTH% --database="%DB_DATABASE%" < "database\schema.sql"
    if errorlevel 1 goto db_error
    "%MYSQL_CLI%" %MYSQL_AUTH% --database="%DB_DATABASE%" < "database\seed.sql"
    if errorlevel 1 goto db_error
    echo        Base de datos preparada.
) else (
    echo        La estructura ya existe.
)

rem ------------------------------------------------------------
rem  Datos de demostracion opcionales
rem ------------------------------------------------------------
echo [5/6] Preparando datos de demostracion...
if "!RUN_DEMO!"=="1" (
    if "!FRESH_DEMO!"=="1" (
        "%PHP_EXE%" database\demo.php --fresh
    ) else (
        "%PHP_EXE%" database\demo.php
    )
    if errorlevel 1 echo        ADVERTENCIA: no se pudieron cargar los datos demo.
) else (
    echo        Omitidos. Use --demo para cargarlos.
)

rem ------------------------------------------------------------
rem  Servidor web
rem ------------------------------------------------------------
echo [6/6] Iniciando el servidor web...
netstat -ano | findstr /R /C:":%APP_PORT% .*LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo        El puerto %APP_PORT% ya esta en uso.
    echo        Si el proyecto ya estaba iniciado, se abrira el navegador.
    if "!OPEN_BROWSER!"=="1" start "" "http://%APP_HOST%:%APP_PORT%"
    goto done
)

start "Futuro Digital - Servidor PHP" /D "%CD%" "%PHP_EXE%" -S %APP_HOST%:%APP_PORT% -t public public/router.php

set /a WEB_TRIES=0
:wait_web
set /a WEB_TRIES+=1
netstat -ano | findstr /R /C:":%APP_PORT% .*LISTENING" >nul 2>&1
if not errorlevel 1 goto web_ready
if !WEB_TRIES! GEQ 20 goto web_error
ping -n 2 127.0.0.1 >nul 2>&1
goto wait_web
:web_ready

echo        Servidor listo en http://%APP_HOST%:%APP_PORT%
if "!OPEN_BROWSER!"=="1" start "" "http://%APP_HOST%:%APP_PORT%"

:done
echo.
echo Futuro Digital esta en marcha.
echo Para detenerlo, cierre la ventana "Futuro Digital - Servidor PHP".
echo.
if "!NO_PAUSE!"=="0" (
    echo %cmdcmdline% | find /I "%~nx0" >nul
    if not errorlevel 1 (
        echo Presione una tecla para cerrar esta ventana...
        pause >nul
    )
)
endlocal
exit /b 0

rem ------------------------------------------------------------
rem  Mensajes de error y ayuda
rem ------------------------------------------------------------
:usage
echo.
echo Uso: iniciar.bat [opciones]
echo.
echo   --demo        Carga los datos de demostracion (si aun no existen).
echo   --fresh-demo  Reinicia y vuelve a cargar los datos de demostracion.
echo   --no-browser  No abre el navegador al terminar.
echo   --no-db       No intenta iniciar MariaDB/MySQL automaticamente.
echo   --no-pause    No espera una tecla al finalizar (para automatizacion).
echo   --help        Muestra esta ayuda.
echo.
echo Variables opcionales: PHP_EXE, MYSQL_CLI, MYSQLD_EXE, MYSQL_INI.
echo.
endlocal
exit /b 0

:php_missing
echo.
echo ERROR: No se encontro PHP.
echo Instale XAMPP o agregue php.exe al PATH, o defina PHP_EXE.
goto error_exit

:mysql_missing
echo.
echo ERROR: No se encontro el cliente de MySQL/MariaDB (mysql.exe).
echo Instale XAMPP o agregue mysql.exe al PATH, o defina MYSQL_CLI.
goto error_exit

:mysqld_missing
echo.
echo ERROR: MariaDB/MySQL no esta en ejecucion y no se encontro mysqld.exe.
echo Inicie el motor manualmente o defina MYSQLD_EXE.
goto error_exit

:db_error
echo.
echo ERROR: No fue posible conectar o preparar la base de datos.
echo Revise que MariaDB/MySQL este iniciado y los datos DB_* de .env.
goto error_exit

:web_error
echo.
echo ERROR: El servidor PHP no respondio en el puerto %APP_PORT%.
echo Revise la ventana "Futuro Digital - Servidor PHP".
goto error_exit

:error_exit
echo.
echo No se pudo iniciar Futuro Digital.
echo.
if "!NO_PAUSE!"=="0" (
    echo %cmdcmdline% | find /I "%~nx0" >nul
    if not errorlevel 1 pause >nul
)
endlocal
exit /b 1
