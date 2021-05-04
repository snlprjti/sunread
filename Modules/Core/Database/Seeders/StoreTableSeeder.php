<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StoreTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('stores')->insert([
           [
               "currency" => "USD",
               "name" => "International Store",
               "slug" => "international-store",
               "locale" => "en",
               "position" => 1,
               "created_at" => now(),
               "updated_at" => now()
           ],
            [
                "currency" => "EUR",
                "name" => "English Store",
                "slug" => "english-store",
                "locale" => "en",
                "position" => 2,
                "created_at" => now(),
                "updated_at" => now()
            ]
        ]);
    }
}
