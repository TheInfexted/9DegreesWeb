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
        $this->secret = env('JWT_SECRET', 'change-me-in-production');
        $this->expiry = (int) env('JWT_EXPIRY', 2592000); // 30 days
    }
}
