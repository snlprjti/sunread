<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Entities\Currency;
use Modules\Core\Tests\BaseTestCase;

class CurrencyTest extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
        $this->model = Currency::class;
        $this->model_name = "Currency";
        $this->route_prefix = "admin.currencies";
        $this->default_resource_id = Currency::latest()->first()->id;
        $this->fake_resource_id = 0;
        $this->filter = [
            "sort_by" => "id",
            "sort_order" => "asc"
        ];
    }

    public function getCreateData(): array
    {
        return $this->model::factory()->make()->toArray();
    }

    public function getInvalidCreateData(): array
    {
        return array_merge($this->getCreateData(), [
            "code" => null
        ]);
    }

    public function getUpdateData(): array
    {
        return $this->model::factory()->make()->toArray();
    }

    public function getNonMandodtaryUpdateData(): array
    {
        return array_merge($this->getUpdateData(), [
            "name" => null
        ]);
    }

    public function getInvalidUpdateData(): array
    {
        return array_merge($this->getUpdateData(), [
            "code" => null
        ]);
    }

}
