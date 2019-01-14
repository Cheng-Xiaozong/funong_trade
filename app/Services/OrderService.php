<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/12
 * Time: 11:12
 */
namespace App\Services;

use App\GoodsOffer;
use App\Order;
use Illuminate\Support\Facades\DB;


class OrderService
{


    /**
     * 创建订单
     * @param $data
     * @return object
     */
    public static function create($data)
    {
        return Order::create($data);
    }


    /**
     * 通过员工id获取订单
     * @param $employee_id
     * @param $order_status
     * @param $operation_status
     * @param $page_size
     * @return mixed
     */
    public static function getOrderByAccountEmployeeIdAndType($employee_id, $page_size)
    {

        return Order::where('account_employee_id',$employee_id)
                    ->whereIn('order_status',[0,1,3])
                    ->where('operation_status','==',Order::OPERATION_STATUS['unconfirm'])
                    ->orderBy('updated_at','AESC')
                    ->orderBy('order_status','AESC')
                    ->paginate($page_size);
    }


    /**
     * 通过买家id获取订单
     * @param $buyer_id
     * @param $order_status
     * @param $operation_status
     * @param $page_size
     * @return mixed
     */
    public static function getOrderByBuyerIdAndType($buyer_id, $page_size)
    {

        return Order::where('account_buyer_id',$buyer_id)
                    ->whereIn('order_status',[0,1,3])
                    ->where('operation_status','==',Order::OPERATION_STATUS['unconfirm'])
                    ->orderBy('updated_at','AESC')
                    ->orderBy('order_status','AESC')
                    ->paginate($page_size);
    }


    /**
     * 通过id获取订单
     * @param $id
     * @return mixed
     */
    public static function getOrderById($id)
    {
        return Order::find($id);
    }


    /**
     * 计算当日下单总量
     * @param $buyer_business_id
     * @param $beginToday
     * @param $endToday
     * @return mixed
     */
    public static function getUserTodayOrderNum($buyer_business_id, $beginToday, $endToday)
    {
        return Order::where('buyer_business_id',$buyer_business_id)
                    ->whereBetween('created_at',[$beginToday,$endToday])
                    ->sum('num');
    }


    /**
     * 获得最近一笔订单
     * @param $buyer_business_id
     * @return mixed
     */
    public static function getLastOrderByBuyerBunsinessId($buyer_business_id)
    {
        return Order::where('buyer_business_id',$buyer_business_id)->orderBy('created_at','desc')->first();
    }


    /**
     * 通过订单编号获取订单
     * @param $order_number
     * @return mixed
     */
    public static function getOrderByOrderNumber($order_number)
    {
        return Order::where('order_number',$order_number)->first();
    }


    /**
     * 通过id和员工id获取订单
     * @param $id
     * @param $emoloyee_id
     * @return mixed
     */
    public static function getOrderByIdAndEmployeeId($id, $emoloyee_id)
    {
        return Order::where('id',$id)
            ->where('account_employee_id',$emoloyee_id)
            ->first();
    }


    /**
     * 通过id和买家id获取订单
     * @param $id
     * @param $buyer_id
     * @return mixed
     */
    public static function getOrderByIdAndBuyerId($id, $buyer_id)
    {
        return Order::where('id',$id)
            ->where('account_buyer_id',$buyer_id)
            ->first();
    }


    /**
     * 通过id和麦家id获取订单
     * @param $id
     * @param $seller_id
     * @return mixed
     */
    public static function getOrderByIdAndSellerId($id, $seller_id)
    {
        return Order::where('id',$id)
            ->where('account_seller_id',$seller_id)
            ->first();
    }


    /**
     * 删除
     * @param $id
     * @return int
     */
    public static function delete($id)
    {
        return Order::destroy($id);
    }


    /**
     * 更新订单
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updateOrder($id, $data)
    {
        return Order::find($id)->update($data);
    }


    /**
     * 通过订单编号更新订单
     * @param $order_number
     * @param $data
     * @return mixed
     */
    public static function updateOrderByOrderNumber($order_number, $data)
    {
        return Order::where('order_number',$order_number)->update($data);
    }


    /**
     * 获得订单列表
     * @param $page_size
     * @return mixed
     */
    public static function orderList($page_size)
    {
        return Order::orderBy('updated_at', 'desc')->with('AccountBusiness')->paginate($page_size);
    }


    /**
     * 通过企业id获取订单
     * @param $business_id
     * @param $page_size
     * @return mixed
     */
    public static function getOrderListByBusinessId($business_id, $page_size)
    {
        return Order::whereIn('account_seller_id',$business_id)->with('AccountBusiness')->orderBy('order_status', 'asc')->orderBy('updated_at', 'desc')->paginate($page_size);
    }


    /**
     * 统计合同
     * @param $business_id
     * @return mixed
     */
    public static function countOrder($business_id)
    {
        $result['waiting'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['waiting'])->count();
        $result['unfinished'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['unfinished'])->where('operation_status',Order::OPERATION_STATUS['unconfirm'])->count();
        $result['draft'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['unfinished'])->where('operation_status',Order::OPERATION_STATUS['draft'])->count();
        $result['wait_cash'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['unfinished'])->where('operation_status',Order::OPERATION_STATUS['cashing'])->count();
        $result['ordering'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['unfinished'])->where('operation_status',Order::OPERATION_STATUS['running'])->count();
        $result['finished'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['finished'])->count();
        $result['disable'] = Order::whereIn('account_seller_id',$business_id)->where('order_status',Order::ORDER_STATUS['disable'])->count();
        return $result;
    }

    /**
     * 搜索报价
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function SearchOrderList($where,$page_size)
    {

        $query=Order::select('*');
        //卖家名称
        if(isset($where['seller_name']))
        {
            $query=$query->where('seller_name','like','%'.$where['seller_name'].'%');
            unset($where['seller_name']);
        }
        //买家名称
        if(isset($where['buyer_name']))
        {
            $query=$query->where('buyer_name','like','%'.$where['buyer_name'].'%');
            unset($where['buyer_name']);
        }
        //商品名称
        if(isset($where['goods_name']))
        {
            $query=$query->where('goods_name','like','%'.$where['goods_name'].'%');
            unset($where['goods_name']);
        }

        $query = $query->where($where)->orderBy('updated_at', 'desc')->paginate($page_size);

        return $query;
    }


    /**
     * 通过企业id搜索订单
     * @param $business_id
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function SearchOrderListByBusinessId($business_id, $where, $page_size)
    {

        $query=Order::select('*');
        //卖家名称
        if(isset($where['seller_name']))
        {
            $query=$query->where('seller_name','like','%'.$where['seller_name'].'%');
            unset($where['seller_name']);
        }
        //买家名称
        if(isset($where['buyer_name']))
        {
            $query=$query->where('buyer_name','like','%'.$where['buyer_name'].'%');
            unset($where['buyer_name']);
        }
        //商品名称
        if(isset($where['goods_name']))
        {
            $query=$query->where('goods_name','like','%'.$where['goods_name'].'%');
            unset($where['goods_name']);
        }

        $query = $query->whereIn('account_seller_id',$business_id)->with('AccountBusiness')->where($where)->orderBy('updated_at', 'desc')->paginate($page_size);

        return $query;
    }


    /**
     * 订单列表字段描述
     * @return mixed
     */
    public static function AllOrderListDescribe()
    {
        $data['order_status']=Order::ORDER_STATUS_DESCRIBE;
        $data['operation_status']=Order::OPERATION_STATUS_DESCRIBE;
        return $data;
    }


    /**
     * 通过卖家id获取订单
     * @param $account_seller_id
     * @param $page_size
     * @return mixed
     */
    public static function getOrderByAccountSellerId($account_seller_id, $page_size,$order_status)
    {
        if($order_status == 4){
            return Order::where('account_seller_id',$account_seller_id)
                        ->whereIn('order_status',[0,1,3])
                        ->where('operation_status','==',Order::OPERATION_STATUS['unconfirm'])
                        ->orderBy('updated_at','AESC')
                        ->paginate($page_size);
        }else{
            return Order::where('account_seller_id',$account_seller_id)
                        ->where('order_status',$order_status)
                        ->where('operation_status','==',Order::OPERATION_STATUS['unconfirm'])
                        ->orderBy('updated_at','AESC')
                        ->paginate($page_size);
        }
    }


    /**
     * 通过卖家id获取所有订单（ERP）
     * @param $seller_id
     * @param $page_size
     * @return mixed
     */
    public static function getOrderByAccountSellerIds($seller_id, $page_size)
    {

        return Order::whereIn('account_seller_id',$seller_id)
            ->orderBy('updated_at','AESC')
            ->paginate($page_size);
    }


    /**
     * 通过买家id计算数量
     * @param $buyer_id
     * @return mixed
     */
    public static function countOrderByBuyerId($buyer_id)
    {

        return Order::where('account_buyer_id',$buyer_id)
            ->whereIn('order_status',[0,1,3])
            ->where('operation_status','==',Order::OPERATION_STATUS['unconfirm'])
            ->count();
    }


    /**
     * 通过卖家id计算数量
     * @param $seller_id
     * @return mixed
     */
    public static function countOrderBySellerId($seller_id)
    {

        return Order::where('account_seller_id',$seller_id)
            ->whereIn('order_status',[0,1,3])
            ->where('operation_status','==',Order::OPERATION_STATUS['unconfirm'])
            ->count();
    }


    /**
     * 获得待处理订单
     * @return mixed
     */
    public static function getWaitingOrder()
    {
        return Order::where('order_status',Order::ORDER_STATUS['waiting'])->get();
    }

    /**
     * 统计订单状态
     */
    public static function getOrderStatus($start_time, $end_time)
    {
        return Order::select('id', 'seller_name', 'goods_name', 'goods_info', 'offer_name', 'category_name',
            'order_status', 'operation_status', 'created_at', 'source', 'num')
            ->whereBetween('created_at', [$start_time, $end_time])->get();
    }

}