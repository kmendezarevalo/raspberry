<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/SSHManager.php';

use App\Services\SSHManager;

$host = '192.168.23.164';
$user = 'pi';
$pass = 'raspberry';

try {
    $ssh = new SSHManager($host, $user, $pass);
    echo "--- LINE 11 START ---\n";
    echo $ssh->execute("sed -n '11p' /home/pi/Documents/rpi_6664.sh") . "\n";
    echo "--- LINE 11 END ---\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
