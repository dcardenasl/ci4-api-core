<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Each consumer app declares an `App\Config\Scaffolding` class that extends
 * this base and returns its conventions from `build()`. The package's spark
 * commands resolve it via CI4's `config()` helper:
 *
 *   $config = config('Scaffolding')->build();
 *
 * The decision to require the consumer to ship a class (rather than reading
 * a flat config array) buys IDE-level type safety end-to-end: rename a
 * property in `ScaffoldingConfig` and the consumer's editor flags the call
 * site immediately.
 *
 * Trivial consumer template:
 *
 *   namespace Config;
 *
 *   use dcardenasl\CI4ApiCrudMaker\Config\BaseScaffoldingConfig;
 *   use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
 *
 *   class Scaffolding extends BaseScaffoldingConfig
 *   {
 *       public function build(): ScaffoldingConfig
 *       {
 *           return ScaffoldingConfig::defaults();
 *       }
 *   }
 */
abstract class BaseScaffoldingConfig extends BaseConfig
{
    abstract public function build(): ScaffoldingConfig;
}
