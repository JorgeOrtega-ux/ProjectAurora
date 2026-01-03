# Project Aurora

Project Aurora es una aplicación web modular construida con **PHP nativo** y **JavaScript**, diseñada con una arquitectura tipo SPA (Single Page Application) sin frameworks pesados. Cuenta con un sistema de autenticación robusto, gestión de perfiles, seguridad avanzada (2FA, detección de dispositivos) y una interfaz moderna y responsiva.

## 📋 Características Principales

* **Autenticación Completa:** Login, Registro, Recuperación de contraseña y Bloqueo por intentos fallidos.
* **Seguridad Avanzada:**
    * Autenticación de Dos Factores (2FA) compatible con Google Authenticator/Authy.
    * Protección contra fuerza bruta y CSRF (Cross-Site Request Forgery).
    * Integración con Cloudflare Turnstile (Captcha invisible).
    * Gestión de sesiones activas (ver y revocar dispositivos).
* **Perfil de Usuario:**
    * Avatar personalizado (subida de imágenes con validación) o generado automáticamente (UI Avatars).
    * Cambio de datos personales y contraseña.
    * Preferencias de usuario (Tema Claro/Oscuro, Idioma, Comportamiento de enlaces).
* **Arquitectura:**
    * Ruteo amigable (Clean URLs) mediante `.htaccess`.
    * Carga de contenido asíncrona (Fetch API + `loader.php`).
    * Sistema de internacionalización (i18n) JSON.

## 🛠️ Requisitos del Sistema

* **PHP:** 7.4 o superior (Recomendado 8.0+).
* **Base de Datos:** MySQL 5.7+ o MariaDB 10.3+.
* **Servidor Web:** Apache (con `mod_rewrite` habilitado).
* **Extensiones PHP:** `pdo_mysql`, `gd` (para imágenes), `json`, `mbstring`.

---

## 🚀 Instalación (Entorno Local)

1.  **Clonar el repositorio:**
    ```bash
    git clone [https://github.com/tu-usuario/project-aurora.git](https://github.com/tu-usuario/project-aurora.git)
    cd project-aurora
    ```

2.  **Base de Datos:**
    * Crea una base de datos vacía (ej. `project_aurora_db`).
    * Importa el archivo `bd.sql` ubicado en la raíz del proyecto.

3.  **Configuración de Entorno:**
    * En la raíz del proyecto, crea un archivo llamado `.env` (puedes basarte en el ejemplo de abajo).
    * Configura las credenciales de tu base de datos local.

    ```ini
    # Archivo .env
    DB_HOST=localhost
    DB_NAME=project_aurora_db
    DB_USER=root
    DB_PASS=

    # Keys de prueba para local (Siempre pasan)
    TURNSTILE_SITE_KEY=1x00000000000000000000BB
    TURNSTILE_SECRET_KEY=1x00000000000000000000BB
    ```

4.  **Permisos:**
    Asegúrate de que la carpeta de almacenamiento tenga permisos de escritura:
    ```bash
    chmod -R 755 storage/
    ```

5.  **Acceso:**
    Accede a través de tu navegador (ej. `http://localhost/ProjectAurora/`).
    *Nota: Si la carpeta del proyecto no se llama `ProjectAurora`, ajusta la variable `$basePath` en `config/routers/router.php`, `public/loader.php` y el `RewriteBase` en `public/.htaccess`.*

---

## 🔒 Guía de Puesta en Producción

Sigue estos pasos estrictamente para desplegar la aplicación en un servidor real (VPS, Hosting compartido, etc.).

### 1. Forzar HTTPS (SSL)
El sistema de login utiliza cookies con el atributo `Secure` y `SameSite=Strict`. **La aplicación no funcionará correctamente sin HTTPS.**
* Instala un certificado SSL (Let's Encrypt es gratuito).
* Asegúrate de que tu servidor redirija todo el tráfico HTTP a HTTPS.

### 2. Configuración de Base de Datos (Seguridad)
Nunca uses el usuario `root` en producción.

1.  Crea un usuario específico para la aplicación en MySQL:
    ```sql
    CREATE USER 'aurora_user'@'localhost' IDENTIFIED BY 'Tu_Contraseña_Ultra_Segura_!23';
    ```
2.  Otorga **solo los permisos necesarios** (Principio de menor privilegio):
    ```sql
    GRANT SELECT, INSERT, UPDATE, DELETE ON project_aurora_db.* TO 'aurora_user'@'localhost';
    FLUSH PRIVILEGES;
    ```
    *Nota: La aplicación no necesita permisos de `DROP`, `ALTER` o `CREATE` en producción una vez importada la tabla.*

3.  Actualiza tu archivo `.env` en el servidor con este nuevo usuario.

### 3. Cloudflare Turnstile (Captcha Real)
Las claves que vienen en el código son de prueba (siempre aprueban). Para evitar bots reales:

1.  Crea una cuenta en [Cloudflare Dashboard](https://dash.cloudflare.com/).
2.  Ve a "Turnstile" > "Add Site".
3.  Obtén tu **Site Key** y **Secret Key**.
4.  Reemplázalas en tu archivo `.env` de producción:
    ```ini
    TURNSTILE_SITE_KEY=0x4AAAAAA... (Tu clave real)
    TURNSTILE_SECRET_KEY=0x4AAAAAA... (Tu secreto real)
    ```

### 4. Permisos de Archivos
Asegura los permisos para evitar modificaciones no autorizadas, pero permitiendo la subida de avatares.

* **Archivos generales (PHP, JS, CSS):** `644` (Lectura/Escritura solo dueño, Lectura otros).
* **Directorios:** `755`.
* **Carpeta de Avatares:** El servidor web (`www-data` o similar) necesita escribir aquí.
    ```bash
    chown -R www-data:www-data storage/profilePicture/
    chmod -R 755 storage/profilePicture/
    ```

### 5. Configuración de Rutas (.htaccess)
Si cambias el nombre de la carpeta en producción (o si lo subes a la raíz del dominio `public_html`), debes editar el archivo `public/.htaccess`:

Si está en la raíz (`tudominio.com`):
```apache
RewriteBase /public/