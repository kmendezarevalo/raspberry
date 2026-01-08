<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use Exception;

class SSHManager
{
    private $ssh;
    private $host;
    private $user;
    private $password;

    public function __construct(string $host, string $user = 'pi', string $password = '')
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Establece conexión SSH
     * @throws Exception
     */
    public function connect(): bool
    {
        try {
            // timeout de 10 segundos para la conexión inicial
            $this->ssh = new SSH2($this->host, 22, 10);
            if (!$this->ssh->login($this->user, $this->password)) {
                throw new Exception("Fallo de login SSH en " . $this->host . ". Revisa que el usuario '{$this->user}' y el password sean correctos.");
            }
            // Timeout de ejecución de comandos (30s)
            $this->ssh->setTimeout(30);
            return true;
        } catch (Exception $e) {
            throw new Exception("Error al conectar por SSH a {$this->host}: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta un comando y lanza excepción si el exit status no es 0
     * @param string $command
     * @return string
     * @throws Exception
     */
    public function execute(string $command): string
    {
        try {
            if (!$this->ssh || !$this->ssh->isConnected()) {
                $this->connect();
            }

            $output = $this->ssh->exec($command);
            $exitStatus = $this->ssh->getExitStatus();

            // Si el comando falló (exec devuelve false)
            if ($output === false) {
                $this->disconnect(); // Limpiar estado si hubo timeout
                throw new Exception("Timeout o error de comunicación SSH");
            }

            if ($exitStatus !== 0 && $exitStatus !== false) {
                throw new Exception("El comando falló con código $exitStatus. Comando: $command. Salida: $output");
            }

            return (string) $output;
        } catch (Exception $e) {
            // Error crítico de phpseclib: 'Please close the channel (1) before trying to open it again'
            if (str_contains($e->getMessage(), 'channel (1)')) {
                $this->disconnect();
                $this->connect(); // Reconectar de cero
                return $this->ssh->exec($command); // Segundo intento con canal limpio
            }
            throw $e;
        }
    }

    public function disconnect()
    {
        if ($this->ssh) {
            $this->ssh->disconnect();
        }
    }
}
