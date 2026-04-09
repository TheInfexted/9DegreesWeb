<?php

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\JWT as JWTConfig;

class JWTHandler
{
    private string $secret;
    private int $expiry;
    private string $algorithm = 'HS256';

    public function __construct()
    {
        $config        = new JWTConfig();
        $this->secret  = $config->secret;
        $this->expiry  = $config->expiry;
    }

    public function encode(array $payload): string
    {
        $now     = time();
        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $this->expiry,
        ]);

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }

    public function getExpiresAt(): int
    {
        return time() + $this->expiry;
    }
}
