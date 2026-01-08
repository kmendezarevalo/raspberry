<?php

namespace App\Services;

class DashboardManager
{
    private $ssh;
    private $token;
    private $config;

    public function __construct(SSHManager $ssh, string $token, \App\Config\Config $config)
    {
        $this->ssh = $ssh;
        $this->token = $token;
        $this->config = $config;
    }

    public function updateDashboard(string $mfgLine): bool
    {
        $baseUrl = "https://supertex.ltlabs.co/dashboard/endline/?factory=SPTX_SLV_01&companyCode=SPTX_COL";
        $extras = "&factoryId=41&userId=1156&language=null";

        $lineName = str_starts_with($mfgLine, "SPTX") ? $mfgLine : "SPTX_SLV_CH-" . $mfgLine;
        $targetUrl = "{$baseUrl}&mfgLine={$lineName}&accessToken={$this->token}{$extras}";

        // 0. Preparar entorno
        $this->ssh->execute("sudo apt-get install -y xdotool python3-requests -qq || true");
        $this->ssh->execute("mkdir -p ~/.config/autostart");
        $this->ssh->execute("sudo rm -f /etc/xdg/autostart/autostartPi.desktop");
        // Limpiamos logs viejos para tener un diagnóstico fresco
        $this->ssh->execute("rm -f ~/boot_debug.log ~/refresh_error.log");

        // Asegurar que existan las carpetas donde guardamos los scripts
        $this->ssh->execute("mkdir -p ~/Documents ~/Music");
        // Limpiar archivos previos que podrían pertenecer a root
        $this->ssh->execute("sudo rm -f ~/Documents/rpi_6664.sh ~/Music/rpi_6664.sh");

        // 1. EL SCRIPT DE ARRANQUE AHORA ES SOLO UN DISPARADOR
        // Simplemente llama al "Cerebro" (Python) y lo deja trabajar.
        $triggerCmd = "cat << 'STARTUP_EOF' > ~/Documents/rpi_6664.sh\n" .
            "#!/bin/bash\n" .
            "## Disparador LTLabs - Llama al script Maestro de Python\n" .
            "python3 ~/refresh_lt.py --boot > ~/boot_debug.log 2>&1 &\n" .
            "STARTUP_EOF\n" .
            "cp ~/Documents/rpi_6664.sh ~/Music/rpi_6664.sh && " .
            "chmod +x ~/Documents/rpi_6664.sh ~/Music/rpi_6664.sh";
        $this->ssh->execute($triggerCmd);

        // 2. TRIGGER DE AUTOSTART (User level)
        $cmdDesktop = "cat << 'DESKTOP_EOF' > ~/.config/autostart/ltlabs.desktop\n" .
            "[Desktop Entry]\n" .
            "Name=LTLabs Dashboard\n" .
            "Type=Application\n" .
            "Exec=bash -c 'bash ~/Documents/rpi_6664.sh'\n" .
            "Terminal=false\n" .
            "DESKTOP_EOF";
        $this->ssh->execute($cmdDesktop);

        // 3. EL CEREBRO MAESTRO (Python)
        // Centraliza todo el ciclo de vida del navegador: Red, Token y Ejecución.
        $username = $this->config->get('ltlabs_user');
        $password = $this->config->get('ltlabs_password');

        $refreshScript = <<<PYTHON
#!/usr/bin/python3
import requests, os, time, subprocess, sys

def run_browser(url):
    print(f"Abriendo Chromium con URL: {url}")
    os.environ["DISPLAY"] = ":0"
    os.environ["XAUTHORITY"] = os.path.expanduser("~/.Xauthority")
    # Cerramos instancias viejas para evitar bloqueos de perfil
    subprocess.run("pkill -9 chromium", shell=True)
    time.sleep(2)
    cmd = [
        "chromium-browser",
        "--start-fullscreen",
        "--kiosk",
        "--disable-session-crashed-bubble",
        "--disable-infobars",
        "--no-first-run",
        "--overscroll-history-navigation=0",
        url
    ]
    # Abrir como proceso independiente
    subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)

def main():
    is_boot = "--boot" in sys.argv
    url_login = "https://supertex.ltlabs.co/msv/global-admin/api/v1/company/userlogin"
    payload = {"companyCode": "SPTX_COL", "username": "{$username}", "password": "{$password}"}
    
    if is_boot:
        print("Modo Arranque: Esperando 30s a que el sistema cargue...")
        time.sleep(30)
    
    try:
        # Intentar obtener token fresco de LTLabs
        resp = requests.post(url_login, json=payload, timeout=25)
        resp.raise_for_status()
        token = resp.json().get("accessToken")
        
        if token:
            target_url = f"https://supertex.ltlabs.co/dashboard/endline/?factory=SPTX_SLV_01&companyCode=SPTX_COL&mfgLine={$lineName}&accessToken={token}&factoryId=41&userId=1156&language=null"
            run_browser(target_url)
        else:
            run_browser("{$targetUrl}")
            
    except Exception as e:
        print(f"Internet no disponible o error API: {e}. Usando URL de respaldo.")
        run_browser("{$targetUrl}")

if __name__ == "__main__":
    main()
PYTHON;

        $escapedPython = str_replace("'", "'\\''", $refreshScript);
        $cmdPersist = "echo '{$escapedPython}' > /tmp/refresh_lt.py && sudo mv /tmp/refresh_lt.py ~/refresh_lt.py && sudo chmod +x ~/refresh_lt.py && sudo chown \$USER:\$USER ~/refresh_lt.py";
        $this->ssh->execute($cmdPersist);

        // 4. CRONTAB LIMPIO (Sin @reboot para evitar dobles arranques)
        $cronCmd = '(crontab -l 2>/dev/null | grep -v "refresh_lt.py"; echo "0 */6 * * * python3 ~/refresh_lt.py") | crontab -';
        $this->ssh->execute($cronCmd);

        return true;
    }
}
