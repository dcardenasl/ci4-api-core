<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

class FeatureFlags extends BaseConfig
{
    public bool $monitoringEnabled = true;
    public bool $metricsEnabled = true;

    public function isEnabled(string $flag): bool
    {
        return match ($flag) {
            'monitoring' => $this->monitoringEnabled,
            'metrics'    => $this->metricsEnabled,
            default      => true,
        };
    }
}
