<?php

namespace App\Services;

class HostnameManager
{
    private $ssh;

    public function __construct(SSHManager $ssh)
    {
        $this->ssh = $ssh;
    }

    public function changeHostname(string $newHostname, string $macFilter): bool
    {
        // 1. Verificar MAC (revisar todas las interfaces: eth0, wlan0, etc)
        $output = $this->ssh->execute("cat /sys/class/net/*/address");
        $macs = array_map('trim', explode("\n", trim($output)));

        $found = false;
        $cleanFilter = strtolower(trim($macFilter));

        foreach ($macs as $m) {
            if (strtolower($m) === $cleanFilter) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception("La MAC no coincide. No se encuentra la MAC {$macFilter} en esta Raspberry.");
        }

        // 2. Cambiar hostname
        $this->ssh->execute("sudo hostnamectl set-hostname {$newHostname}");
        $this->ssh->execute("sudo sed -i 's/127.0.1.1.*/127.0.1.1\t{$newHostname}/' /etc/hosts");

        return true;
    }
}
