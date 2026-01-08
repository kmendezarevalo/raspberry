<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/SSHManager.php';

use App\Services\SSHManager;
use App\Services\DashboardManager;
use App\Config\Config;

$host = '192.168.23.164';
$user = 'pi';
$pass = 'raspberry';

// Token de respaldo (Fallback) - Se usará si falla la auto-renovación
// Nota: Este token expira en 2025/2026, pero el script Python intentará obtener uno nuevo al arrancar.
$fallbackToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55Q29kZSI6IlNQVFhfQ09MIiwiZXhwIjoxNzY2MTk2NzgzLCJzdWIiOjExNTYsInVzZXJJZCI6MTE1NiwidXNlcm5hbWUiOiJzdXBlcmFkbWluIiwiZmFjdG9yeUlkIjo0MSwiZmFjdG9yeSI6IlNQVFhfU0xWXzAxIiwidHlwZSI6InRva2VuIiwiaWF0IjoxNzY2MTUzNTgzfQ.-Ouh93IjJd1EDeH5C4kskYeq5WOCwVar1Vu-6VrLUnU';

try {
    $ssh = new SSHManager($host, $user, $pass);
    $config = Config::getInstance();

    // Instanciamos el Gestor de Dashboard (el "Cerebro")
    // Le pasamos el token de respaldo y la configuración (user/pass) para que la Pi pueda loguearse sola.
    $dashboard = new DashboardManager($ssh, $fallbackToken, $config);

    echo "--- APLICANDO ACTUALIZACIÓN INTELIGENTE (AUTO-RENEW) ---\n";
    echo "Objetivo: $host\n";

    // "SPTX_SLV_CH-VICENZA" es la línea que estaba en la URL original
    if ($dashboard->updateDashboard("SPTX_SLV_CH-VICENZA")) {
        echo "✓ Dashboard actualizado y Auto-Renovación activada.\n";

        echo "--- VERIFICANDO AGENTE GLPI ---\n";
        $glpi = new \App\Services\GLPIManager($ssh);
        if ($glpi->install()) {
            echo "✓ Agente GLPI verificado y optimizado (Versión Smart-Wait).\n";
        }

        echo "¡Éxito! La Raspberry Pi ha sido actualizada.\n";
        echo "Ahora cuenta con el sistema 'refresh_lt.py' que:\n";
        echo "1. Obtiene un nuevo token automáticamente al arrancar.\n";
        echo "2. Se renueva cada 6 horas.\n";
        echo "3. Usa las credenciales guardadas en config.json.\n";

        echo "\nReiniciando servicio de dashboard para probar...\n";
        // Forzamos la ejecución inmediata del script Python para verificar
        $ssh->execute("export DISPLAY=:0 && python3 /home/pi/refresh_lt.py --boot > /dev/null 2>&1 &");
        echo "Comando de arranque enviado. Revisa la pantalla.\n";
    } else {
        echo "Hubo un problema al aplicar la actualización.\n";
    }

} catch (Exception $e) {
    echo "Error CRÍTICO: " . $e->getMessage() . "\n";
}
