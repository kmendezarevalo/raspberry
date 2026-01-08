<?php

namespace App\Config;

class Config
{
    private static $instance = null;
    private $data = [];

    private function __construct()
    {
        $configPath = dirname(__DIR__, 2) . '/config/config.json';
        if (file_exists($configPath)) {
            $this->data = json_decode(file_get_contents($configPath), true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function getAll()
    {
        return $this->data;
    }
}
