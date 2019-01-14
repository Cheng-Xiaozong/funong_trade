<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         $this->call(AreaInfoSeeder::class);
         $this->call(GoodsCategorySeeder::class);
         $this->call(GoodsCategoryAttributeSeeder::class);
         $this->call(GoodsCategoryTradeSeeder::class);
         $this->call(GoodsOfferAttributeSeeder::class);
         $this->call(GoodsOfferPatternSeeder::class);
    }
}
