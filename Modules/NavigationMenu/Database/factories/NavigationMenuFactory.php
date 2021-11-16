<?php
namespace Modules\NavigationMenu\Database\factories;

use Illuminate\Support\Arr;
use Modules\Core\Entities\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

class NavigationMenuFactory extends Factory
{
    protected $model = \Modules\NavigationMenu\Entities\NavigationMenu::class;

    public function definition(): array
    {
        $location = ["footer", "primary", "full_screen"];
        return [
            "title" => $this->faker->name(),
            "slug" => $this->faker->unique()->slug(),
            "status" => 1,
            "location" => Arr::random($location),
            "website_id" => Website::factory()->create()->id,
        ];
    }
}

