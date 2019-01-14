<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Order;
use App\Services\OrderService;;
use App\Services\OfferService;
use Illuminate\Support\Facades\DB;

class clearOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每日凌晨1点作废订单，清空锁定量';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $orders = OrderService::getWaitingOrder();

        // 多少个任务
        $bar = $this->output->createProgressBar(count($orders));

        foreach ($orders as $v) {

            $order_data['order_status'] = Order::ORDER_STATUS['disable'];
            $offer = OfferService::getGoodsOfferById($v->goods_offer_id);
            $offer_data['lock_number'] = 0;
            $offer_data['updated_at'] = $offer->updated_at;

            //更新订单
            OrderService::updateOrder($v->id,$order_data);
            //更新报价
            OfferService::updateOfferById($offer->id,$offer_data);

            $bar->advance();
        }

        $bar->finish();
    }
}
