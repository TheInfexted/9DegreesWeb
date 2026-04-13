<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Commission extends BaseConfig
{
    /** Total Table sale commission pool (% of gross), split between ambassador and owner. */
    public float $tableCommissionPoolPercent = 12.0;
}
