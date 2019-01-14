<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/8/24
 * Time: 11:12
 */
namespace App\Services;


use App\Goods;
use App\GoodsOfferAttribute;
use Faker\Provider\Address;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\GoodsDeliveryAddress;
use Illuminate\Support\Facades\DB;


class AddressService
{

    /**
     * 添加提货地址
     * @param $data
     * @return mixed
     */
    public static function create($data)
    {
        return GoodsDeliveryAddress::create($data);
    }


    /**
     * 通过id更新
     * @param $address_id
     * @param $data
     * @return mixed
     */
    public static function update($address_id, $data)
    {
        return GoodsDeliveryAddress::where('id',$address_id)->update($data);
    }


    /**
     * 检查地址是否归属卖家
     * @param $address_id
     * @param $seller_id
     * @return mixed
     */
    public static function findAddressByAddressIdAndSellerId($address_id, $seller_id)
    {
        return GoodsDeliveryAddress::where('id',$address_id)
                                   ->where('seller_id',$seller_id)->first();
    }

    /**
     * 通过员工id获取提货地址
     * @param $address_id
     * @param $employee_id
     * @return mixed
     */
    public static function findAddressByAddressIdAndEmployeeId($address_id, $employee_id)
    {
        return GoodsDeliveryAddress::where('id',$address_id)
            ->where('account_employee_id',$employee_id)->first();
    }

    /**
     * 删除地址
     * @param $address_id
     * @return int
     */
    public static function deleteAddressById($address_id)
    {
        return GoodsDeliveryAddress::where('id',$address_id)->update(['delete_status'=>GoodsDeliveryAddress::DELETE_STATUS['disable']]);
    }

    /**
     * 获得地址详情
     * @return mixed
     */
    public static function getDefaultAddressBySellerIdId()
    {
        return GoodsDeliveryAddress::where('seller_id',0)->get();
    }

    /**
     * 通过城市获取地址
     * @param $city_id
     * @return mixed
     */
    public static function getAddressByCityId($city_id)
    {
        return GoodsDeliveryAddress::where('city',$city_id)->get();
    }

    /**
     * 通过id获取地址
     * @param $id
     * @return mixed
     */
    public static function getAddressById($id)
    {
        return GoodsDeliveryAddress::find($id);
    }

    /**
     * 通过账户查找地址
     * @param $account_id
     * @return mixed
     */
    public static function getAddressBySellerId($seller_id)
    {
        return GoodsDeliveryAddress::where('seller_id',$seller_id)
                                   ->where('delete_status',GoodsDeliveryAddress::DELETE_STATUS['enable'])
                                   ->get();
    }

    /**
     * 根据ID列表获取地址
     * @param $ids
     * @return mixed
     */
    public static function getAddressByIds($ids)
    {
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }

        return GoodsDeliveryAddress::with('province')->with('city')->find($ids);
    }


    /**
     * 商品地址列表字段描述
     * @return mixed
     */
    public static function addressAttributesListFieldDescribe()
    {
        $data['status']=GoodsDeliveryAddress::STATUS_DESCRIBE;
        return $data;
    }


    /**
     * 获得地址列表
     * @param $page_size
     * @return mixed
     */
    public static function addressList($page_size)
    {
        return GoodsDeliveryAddress::with('province')->with('city')->WithOnly('businesses',['name'])->paginate($page_size);
    }


    /**
     * 通过卖家id获取提货地址
     * @param $page_size
     * @param $seller_id
     * @return mixed
     */
    public static function getAddressListBySellerId($page_size, $seller_id)
    {
        return GoodsDeliveryAddress::whereIn('seller_id',$seller_id)->with('province')->with('city')->WithOnly('businesses',['name'])->paginate($page_size);
    }

    /**
     * @param $page_size
     * @param $seller_id
     * @return mixed
     */
    public static function getAddressesBySellerId($page_size, $seller_id)
    {
        return GoodsDeliveryAddress::where('seller_id',$seller_id)->with('province')->with('city')->WithOnly('businesses',['name'])->paginate($page_size);
    }

    /**
     *  获取公共的地址列表
     * @param $input
     * @return array
     */
    public static function getAllAddressApi($input)
    {
        $query=GoodsDeliveryAddress::select('*');

        if($input['action']=="enable")
        {
            $query=$query->where('status',GoodsDeliveryAddress::STATUS['enable']);
        }

        if($input['action']=="disable")
        {
            $query=$query->where('status',GoodsDeliveryAddress::STATUS['disable']);
        }
        if(isset($input['seller_id']))
        {

            $query=$query->where('seller_id',$input['seller_id']);
        }


        return $query->orWhere('seller_id',0)->get();
    }



    /**
     * 根据提货地址ID获取商品是否存在此提货地址
     * @param $id int
     * @return mixed
     */
    public static function getGoodsByAddress($id)
    {
        return Goods::where('delivery_address_id','like','%'.$id.'%')->get()->count();
    }


    public static function getAllAddress()
    {
        return GoodsDeliveryAddress::all();
    }


    /**
     * 通过地址id删除地址
     * @param $id
     * @return int
     */
    public static function deleteGoodsAddressById($id)
    {
        return GoodsDeliveryAddress::destroy($id);
    }

    /**
     * 搜索地址列表
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchDeliveryAddress($where,$page_size)
    {
        $query=GoodsDeliveryAddress::select('*');

        //名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->where($where)->with('province')->with('city')->WithOnly('businesses',['name'])->paginate($page_size);
    }


    /**
     * 通过卖家id搜索地址
     * @param $where
     * @param $page_size
     * @param $seller_id
     * @return mixed
     */
    public static function searchDeliveryAddressBysellerId($where, $page_size, $seller_id)
    {
        $query=GoodsDeliveryAddress::select('*');

        //名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->whereIn('seller_id',$seller_id)->where($where)->with('province')->with('city')->WithOnly('businesses',['name'])->paginate($page_size);
    }

}