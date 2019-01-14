<?php

use Illuminate\Database\Seeder;

class GoodsOfferPatternSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::insert('
            INSERT INTO `db_goods_offer_patterns` VALUES (\'1\', \'现货价\', \'1\', \'1\', \'2018-01-08 11:34:40\', \'2018-02-10 22:47:07\'),
(\'2\', \'暂定价\', \'1\', \'1\', \'2018-01-08 11:34:52\', \'2018-02-10 22:46:53\'),
(\'3\', \'基差价\', \'1\', \'1\', \'2018-01-08 11:53:22\', \'2018-02-10 22:47:51\')
');
    }
}
