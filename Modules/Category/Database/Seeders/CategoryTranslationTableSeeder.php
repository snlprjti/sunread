<?php

namespace Modules\Category\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryTranslationTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('category_translations')->insert([
            [
                "category_id" => 1,
                "store_id" => 1,
                "name" => "Root"
            ]
        ]);
    }
}