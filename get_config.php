<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/SSHManager.php';

use App\Services\SSHManager;

$host = '192.168.23.164';
$user = 'pi';
$pass = 'raspberry';

try {
    $ssh = new SSHManager($host, $user, $pass);
    echo "--- defaultPage line ---\n";
    echo $ssh->execute("grep 'defaultPage=' /home/pi/Documents/rpi_6664.sh") . "\n";
    echo "--- autostart file ---\n";
    echo $ssh->execute("cat /etc/xdg/autostart/autostartPi.desktop") . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
