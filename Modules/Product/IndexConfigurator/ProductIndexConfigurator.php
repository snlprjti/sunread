<?php

namespace Modules\Product\IndexConfigurator;

use ScoutElastic\IndexConfigurator;
use ScoutElastic\Migratable;

class ProductIndexConfigurator extends IndexConfigurator
{
    use Migratable;

    protected $name = 'sailracing';
    /**
     * @var array
     */
    protected $settings = [
        "index.mapping.total_fields.limit" => 10000
    ];
}