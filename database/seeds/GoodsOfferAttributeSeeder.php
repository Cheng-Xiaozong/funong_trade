<?php

use Illuminate\Database\Seeder;

class GoodsOfferAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::insert('INSERT INTO `db_goods_offer_attributes` VALUES (\'44\', \'3\', \'主力合约\', \'1\', \'DSM\', null, \'1\', \'\', \'string\', \'20\', \'1\', \'2018-02-10 22:47:51\', \'2018-02-10 22:47:51\')
');
    }
}
