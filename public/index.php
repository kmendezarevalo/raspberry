<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\RaspberryManager;

// Ruteo simple mejorado para subcarpetas
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Detectar si termina en api/sync
$isSyncApi = str_ends_with($path, '/api/sync');

if ($isSyncApi) {
    // Configuración para la API
    header('Content-Type: application/json');
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    set_time_limit(600); // 10 minutos para instalaciones muy lentas
    ini_set('memory_limit', '256M');

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        if (!isset($input['ip'])) {
            throw new \Exception("Falta la IP de la Raspberry");
        }

        $manager = new RaspberryManager($input['ip'], $input['password'] ?? '');
        $result = $manager->process($input);
        echo json_encode($result);
    } catch (\Throwable $e) {
        // Capturamos cualquier error (incluso fatales) y devolvemos JSON
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);
    }
    exit;
}

// Frontend HTML
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTLabs | Raspberry Manager Professional</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="glass-container">
        <header>
            <div class="branding-unified">
                <img src="assets/img/supertex.png" class="brand-logo supertex" alt="Supertex">
                <div class="brand-divider"></div>
                <div class="brand-text">LTLabs <span>Admin</span></div>
            </div>
            <div class="status-badge">Professional Edition</div>
        </header>

        <main>
            <div class="card">
                <h1>Sincronización Raspberry</h1>
                <form id="syncForm">
                    <div class="form-group">
                        <label>IP de la Raspberry</label>
                        <input type="text" id="ip" placeholder="192.168..." required>
                    </div>

                    <div class="grid">
                        <div class="form-group">
                            <label>Celda (Dashboard)</label>
                            <input type="text" id="celda" placeholder="VICENZA">
                        </div>
                    </div>

                    <div class="collapsible">
                        <button type="button" class="collapsible-trigger">Opciones Avanzadas (Protegido)</button>
                        <div class="collapsible-content">
                            <div class="form-group checkbox-group">
                                <label class="switch">
                                    <input type="checkbox" id="glpi">
                                    <span class="slider round"></span>
                                </label>
                                <span>Instalar / Verificar GLPI</span>
                            </div>

                            <div class="form-group">
                                <label>Nuevo Hostname</label>
                                <input type="text" id="hostname" placeholder="EC-XXXX">
                            </div>
                            <div class="form-group">
                                <label>MAC Address para Filtro</label>
                                <input type="text" id="mac" placeholder="2c:cf:...">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" id="btnSync">
                        <span class="btn-text">SINCRONIZAR</span>
                        <div class="loader-inner" style="display: none;"></div>
                    </button>
                </form>
            </div>

            <div class="logs-panel">
                <div class="panel-header">
                    <span>Logs de Operación</span>
                    <button id="clearLogs">Limpiar</button>
                </div>
                <div id="logContent" class="log-content">
                    Esperando actividad...
                </div>
            </div>
        </main>

        <footer>
        </footer>
    </div>

    <script src="assets/js/main.js?v=2.3"></script>
</body>

</html>