<?php

use Illuminate\Database\Seeder;

class GoodsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::insert(' INSERT INTO `db_goods_categories` VALUES (\'1\', \'玉米\', \'PL201802101831231001\', \'1,3\', \'1\', \'2018-02-09-17-46-37-5a7d6dfd423df.jpg\', \'0\', \'1\', \'2018-02-22 14:36:48\', \'2018-01-08 11:03:26\'),
 (\'2\', \'豆粕\', \'PL201802101831231002\', \'1,2,3\', \'1\', null, \'1\', \'1\', \'2018-02-10 22:25:05\', \'2018-01-08 13:42:25\'),
 (\'3\', \'小麦\', \'PL201802101831231003\', \'1,2,3\', \'1\', null, \'1\', \'1\', \'2018-01-16 15:30:44\', \'2018-01-16 15:30:41\')');
    }
}
