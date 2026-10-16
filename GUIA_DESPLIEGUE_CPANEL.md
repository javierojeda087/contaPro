# Guía de Despliegue en Hosting cPanel — ContaPro SaaS

Esta guía detalla los pasos exactos para poner en producción **ContaPro** (`www.contapro.com.py`) en cualquier servidor con **cPanel (Apache + PHP 8.1/8.2/8.3 + MySQL/MariaDB)**.

---

## 1. Crear la Base de Datos en cPanel

1. Ingrese a su panel **cPanel** y abra **Bases de datos MySQL®** (*MySQL Databases*).
2. Cree una nueva base de datos, por ejemplo: `tuusuario_contapro`.
3. En la sección **Usuarios de MySQL**, cree un usuario (ej. `tuusuario_admin`) con una contraseña segura.
4. En **Añadir usuario a la base de datos**, asigne ese usuario a `tuusuario_contapro` marcando **TODOS LOS PRIVILEGIOS** (*ALL PRIVILEGES*).

---

## 2. Importar el Backup SQL Completo

1. En cPanel, abra **phpMyAdmin**.
2. Seleccione a la izquierda la base de datos recién creada (`tuusuario_contapro`).
3. Haga clic en la pestaña **Importar** (*Import*).
4. Seleccione el archivo **`database/contapro_mysql.sql`** incluido en el proyecto y presione **Continuar / Importar**.
   - *Nota*: El archivo SQL ya está en formato `utf8mb4_unicode_ci` sin sentencias `CREATE DATABASE` forzadas, por lo que es 100% compatible con MySQL 8 y MariaDB 10.x en cPanel.
   - Incluye las **34 tablas** exclusivas del módulo Contable (`CONTA`) + las tablas Multi-Propietario (`planes`, `propietarios`, `pagos_propietarios`) con control de vencimientos y suscripciones.

---

## 3. Subir los Archivos al Servidor (`public_html`)

1. En cPanel, abra el **Administrador de archivos** (*File Manager*) e ingrese a la carpeta **`public_html`**.
2. Suba el paquete **`ContaPro_cPanel_Deploy.zip`** (o todos los archivos del proyecto).
3. Haga clic derecho sobre `ContaPro_cPanel_Deploy.zip` y elija **Extraer** (*Extract*) directamente dentro de `public_html`.
4. Verifique que el archivo **`.htaccess`** esté presente en `public_html/.htaccess` (en el Administrador de archivos de cPanel, active *"Mostrar archivos ocultos / Show Hidden Files"* en Configuración arriba a la derecha).

---

## 4. Configurar las Credenciales de Base de Datos

Edite el archivo **`public_html/config/database.php`** desde el Administrador de archivos de cPanel y coloque los datos creados en el Paso 1:

```php
<?php
return [
    'host'     => 'localhost',
    'port'     => '3306',
    'dbname'   => 'tuusuario_contapro', // Nombre real de la BD en cPanel
    'username' => 'tuusuario_admin',    // Usuario MySQL creado en cPanel
    'password' => 'TU_CLAVE_MYSQL',     // Contraseña del usuario MySQL
    'charset'  => 'utf8mb4',
];
```

---

## 5. Verificar Versión de PHP en cPanel

1. En cPanel, abra **MultiPHP Manager** (o *Seleccionar versión de PHP*).
2. Asegúrese de que el dominio tenga asignado **PHP 8.1, 8.2 u 8.3**.
3. Verifique que estén activas las extensiones estándar: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `curl`.

---

## 6. Accesos y Rutas del Sistema en Producción

- **Página Web Comercial (Landing Page)**: `https://tudominio.com.py/`
- **Acceso al Sistema Contable (Login)**: `https://tudominio.com.py/login`
- **Panel SuperAdmin (Planes, Propietarios, Vencimientos y Pagos)**: `https://tudominio.com.py/app/superadmin`
