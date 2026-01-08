<?php

namespace App\Services;

use App\Config\Config;
use Exception;

class TokenManager
{
    private $config;
    private $loginUrl = "https://supertex.ltlabs.co/msv/global-admin/api/v1/company/userlogin";

    public function __construct()
    {
        $this->config = Config::getInstance();
    }

    /**
     * Obtiene un token fresco realizando login
     * @return string
     * @throws Exception
     */
    public function getFreshToken(): string
    {
        $username = $this->config->get('ltlabs_user');
        $password = $this->config->get('ltlabs_password');

        if (!$username || !$password) {
            throw new Exception("Credenciales no configuradas en config.json");
        }

        $payload = [
            "companyCode" => "SPTX_COL",
            "username" => $username,
            "password" => $password
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Expect:' // Eliminar Expect: 100-continue que causa resets
        ];

        $maxRetries = 2;
        $attempt = 0;
        $response = false;
        $error = '';
        $httpCode = 0;

        // TÉCNICA 1: cURL ultra-compatible
        while ($attempt < $maxRetries && $response === false) {
            $attempt++;
            $ch = curl_init($this->loginUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            // Bajar nivel de seguridad para permitir handshakes difíciles
            curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode === 200)
                break;
            if ($attempt < $maxRetries)
                sleep(1);
        }

        // TÉCNICA 2: Fallback a PHP Native Streams (si cURL falla por SYSCALL)
        if ($response === false) {
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: Mozilla/5.0\r\n",
                    'content' => json_encode($payload),
                    'timeout' => 20,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($this->loginUrl, false, $context);

            if ($response !== false) {
                $httpCode = 200; // Asumimos OK si hay respuesta (los errores los capturamos después)
                $error = '';
            }
        }

        if ($response === false) {
            throw new Exception("Fallo total de conexión (cURL + Streams). El servidor LTLabs no responde. Detalle: $error");
        }

        if ($httpCode !== 200) {
            throw new Exception("Error en login. Código HTTP: " . $httpCode . ". Respuesta: " . $response);
        }

        $data = json_decode($response, true);
        $token = $data['accessToken'] ?? $data['token'] ?? $data['access_token'] ?? null;

        if (!$token) {
            throw new Exception("No se recibió token en la respuesta de login: " . $response);
        }

        return $token;
    }
}
