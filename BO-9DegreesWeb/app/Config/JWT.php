<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class JWT extends BaseConfig
{
    public string $secret;
    public int $expiry;

    public function __construct()
    {
        parent::__construct();
        $secret = env('JWT_SECRET');
        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET must be set in .env');
        }
        $this->secret = $secret;
        $this->expiry = (int) env('JWT_EXPIRY', 2592000); // 30 days
    }
}
