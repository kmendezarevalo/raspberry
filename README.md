# LTLabs Raspberry Manager (PHP Edition)

Este proyecto es la versión web profesional del script `pruebaByron.py`. Ofrece una interfaz moderna, gestión automática de tokens y una arquitectura escalable.

## 🚀 Requisitos

- **PHP 8.2+**
- **Composer** (para dependencias)
- **Extensión cURL** de PHP habilitada
- **Extensión OpenSSL** de PHP habilitada

## 📦 Instalación

1. Clona o copia la carpeta `php_version` en tu servidor web.
2. Abre una terminal en la carpeta `php_version`.
3. Instala las dependencias:
   ```bash
   composer install
   ```

## ⚙️ Configuración

Edita el archivo `config/config.json` con tus credenciales de LTLabs:

```json
{
  "ltlabs_user": "superadmin",
  "ltlabs_password": "welcome1"
}
```

## 🏃 Ejecución (Desarrollo)

Puedes iniciar un servidor de desarrollo rápido desde la terminal:

```bash
php -S localhost:8000 -t public
```

Luego accede a `http://localhost:8000` en tu navegador.

## 📁 Caracteristicas Profesionales

- **Arquitectura MVC**: Separación de lógica, datos y vista.
- **Auto-Login**: Genera un token fresco de LTLabs para cada operación.
- **SSH Seguro**: Utiliza `phpseclib` para conexiones robustas.
- **GLPI Verified**: Incluye la lógica de verificación v4.6.
- **UI Premium**: Diseño moderno con Glassmorphism y animaciones.

## 🛠️ Estructura del Código

- `src/Config`: Gestión de configuración.
- `src/Services/SSHManager`: Conectividad SSH.
- `src/Services/TokenManager`: Login y tokens de LTLabs.
- `src/Services/RaspberryManager`: Orquestador principal.
- `public/`: Archivos accesibles vía web (Frontend).
