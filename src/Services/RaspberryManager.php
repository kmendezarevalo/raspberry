<?php

namespace App\Services;

class RaspberryManager
{
    private $ssh;
    private $tokenManager;

    public function __construct(string $ip, string $password = '')
    {
        $this->ssh = new SSHManager($ip, 'pi', $password);
        $this->tokenManager = new TokenManager();
    }

    public function process(array $config): array
    {
        $results = [];
        try {
            $this->ssh->connect();

            // 1. Dashboard
            if (!empty($config['mfgLine'])) {
                $token = $this->tokenManager->getFreshToken();
                $dashboard = new DashboardManager($this->ssh, $token, \App\Config\Config::getInstance());
                $dashboard->updateDashboard($config['mfgLine']);
                $results[] = "✓ Dashboard actualizado y Auto-Renovación activada";
            }

            // 2. Hostname
            if (!empty($config['newHostname']) && !empty($config['macFilter'])) {
                $hostname = new HostnameManager($this->ssh);
                $hostname->changeHostname($config['newHostname'], $config['macFilter']);
                $results[] = "✓ Hostname cambiado";
            }

            // 3. GLPI
            if ($config['installGlpi'] ?? false) {
                $glpi = new GLPIManager($this->ssh);
                $glpi->install();
                $results[] = "✓ GLPI instalado y verificado";
            }

            // 4. Limpiar y Reiniciar si hubo cambios
            if (!empty($results)) {
                // Usamos un pequeño delay y disparar al fondo para que SSH no se cierre antes de que PHP reciba el OK
                $cmdCleanup = "sudo killall -9 chromium-browser 2>/dev/null; sudo rm -rf ~/.config/chromium/Singleton* 2>/dev/null; sync; (sleep 2 && sudo reboot) > /dev/null 2>&1 &";
                $this->ssh->execute($cmdCleanup);
                $results[] = "⚡ Reiniciando Raspberry para aplicar cambios...";
            }

            return [
                'success' => true,
                'messages' => $results
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            $this->ssh->disconnect();
        }
    }
}
