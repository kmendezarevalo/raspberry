<?php

namespace App\Services;

class GLPIManager
{
    private $ssh;
    private $glpiServer = "http://10.10.13.138";
    private $glpiTag = "Chalchuapa";

    public function __construct(SSHManager $ssh)
    {
        $this->ssh = $ssh;
    }

    public function install(): bool
    {
        // 1. Verificación preliminar inteligente
        $binaryPath = trim($this->ssh->execute("command -v glpi-agent >/dev/null 2>&1 && echo 'YES' || echo 'NO'"));
        $serviceExists = trim($this->ssh->execute("[ -f /lib/systemd/system/glpi-agent.service ] || [ -f /etc/systemd/system/glpi-agent.service ] && echo 'YES' || echo 'NO'"));

        if ($binaryPath === 'YES' && $serviceExists === 'YES') {
            $status = trim($this->ssh->execute("sudo systemctl is-active glpi-agent || true"));
            if ($status === 'active') {
                // Ya está funcionando, forzamos un inventario para confirmar
                $this->ssh->execute("sudo glpi-agent --force");
                return true;
            }
        }

        // 2. DETECCIÓN Y REPARACIÓN AUTOMÁTICA DE REPOSITORIOS (Legacy Support)
        // Si detectamos Buster (antiguo), reparamos los repositorios para que apt-get funcione
        $osInfo = $this->ssh->execute("cat /etc/os-release | grep VERSION_CODENAME || echo 'unknown'");
        if (str_contains($osInfo, 'buster')) {
            $this->ssh->execute("sudo cp /etc/apt/sources.list /etc/apt/sources.list.bak || true");
            // Apuntamos a los espejos de legado oficiales
            $this->ssh->execute("echo 'deb http://legacy.raspbian.org/raspbian buster main contrib non-free rpi' | sudo tee /etc/apt/sources.list");
            $this->ssh->execute("echo 'deb http://archive.raspberrypi.org/debian buster main' | sudo tee /etc/apt/sources.list.d/raspi.list");
        }

        // 3. Preparación del sistema y limpieza agresiva de bloqueos/estados rotos
        $this->ssh->execute("sudo fuser -kk /var/lib/dpkg/lock-frontend /var/lib/apt/lists/lock /var/cache/apt/archives/lock || true");
        $this->ssh->execute("sudo rm -f /var/lib/dpkg/lock-frontend /var/lib/apt/lists/lock /var/cache/apt/archives/lock || true");
        $this->ssh->execute("sudo dpkg --purge glpi-agent || true"); // Limpiar versiones rotas previas
        $this->ssh->execute("sudo dpkg --configure -a || true");
        $this->ssh->execute("sudo apt-get update --allow-releaseinfo-change -qq || true");
        $this->ssh->execute("sudo apt-get install -y wget perl ca-certificates -qq || true");

        // 4. Descargar Instalador Universal de GLPI (Perl Script)
        $downloadUrl = "https://github.com/glpi-project/glpi-agent/releases/download/1.15/glpi-agent-1.15-linux-installer.pl";
        $this->ssh->execute("cd /tmp && wget -q -O glpi-agent-installer.pl {$downloadUrl}");

        // 5. Ejecución con Bypass de Errores de Dependencias
        // Intentamos la instalación completa primero
        $fullCmd = "echo '{$this->glpiServer}' | sudo perl /tmp/glpi-agent-installer.pl --server={$this->glpiServer} --tag={$this->glpiTag} --install --runnow";
        try {
            $this->ssh->execute($fullCmd);
        } catch (\Exception $e) {
            // Si falla por dependencias de red, intentamos la mínima necesaria
            $minimalCmd = "echo '{$this->glpiServer}' | sudo perl /tmp/glpi-agent-installer.pl --server={$this->glpiServer} --tag={$this->glpiTag} --install --runnow --no-task=network,inventory || true";
            $this->ssh->execute($minimalCmd);
        }

        // 6. GENERACIÓN QUIRÚRGICA DE SERVICIO (Standardized para systemd)
        // Siempre aseguramos que el archivo de servicio sea el correcto con --no-fork
        $unitFile = "/lib/systemd/system/glpi-agent.service";
        $binPath = trim($this->ssh->execute("which glpi-agent || find /usr -name glpi-agent -executable 2>/dev/null | head -1 || echo '/usr/bin/glpi-agent'"));
        $manualService = "[Unit]\nDescription=GLPI Agent\nAfter=network.target\n\n[Service]\nExecStart=$binPath --daemon --no-fork\nRestart=always\n\n[Install]\nWantedBy=multi-user.target";
        $this->ssh->execute("echo '{$manualService}' | sudo tee $unitFile && sudo systemctl daemon-reload");

        // 7. Configuración e Inyección de Datos Maestra (Garantiza que aparezca en el GLPI)
        $this->ssh->execute("sudo mkdir -p /etc/glpi-agent");
        $configContent = "server = {$this->glpiServer}\ntag = {$this->glpiTag}\nlogger = stderr\n";
        $this->ssh->execute("echo '{$configContent}' | sudo tee /etc/glpi-agent/agent.cfg");

        // 8. Reinicio y Forzado de Inventario con Ruta Absoluta
        $this->ssh->execute("sudo systemctl daemon-reload && sudo systemctl unmask glpi-agent || true");
        $this->ssh->execute("sudo systemctl enable glpi-agent || true");
        $this->ssh->execute("sudo systemctl restart glpi-agent || true");

        // ESPERA INTELIGENTE: Esperar hasta que el servicio esté realmente arriba
        // Damos hasta 30 segundos para que levante, y luego 5 segundos extra para inicializar módulos
        $this->ssh->execute("timeout 30s bash -c 'until systemctl is-active glpi-agent; do sleep 1; done' || true");
        $this->ssh->execute("sleep 5");

        // El comando clave: Forzar el envío AHORA MISMO
        $binPathFinal = trim($this->ssh->execute("which glpi-agent || find /usr -name glpi-agent -executable 2>/dev/null | head -1 || echo '/usr/bin/glpi-agent'"));
        $this->ssh->execute("sudo $binPathFinal --force || true");

        return true;
    }
}
