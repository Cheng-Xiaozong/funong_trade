<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/12
 * Time: 11:12
 */
namespace App\Services;

use App\AccountBusiness;
use App\GoodsCategory;
use App\GoodsDeliveryAddress;
use App\GoodsOffer;
use App\GoodsOfferAttribute;
use App\GoodsOfferCategory;
use App\GoodsOfferPattern;

use App\Goods;
use App\Http\Controllers\Home\GoodsController;
use function Symfony\Component\Console\Tests\Command\createClosure;

class OfferService
{


    /**
     * 通过id获取报价
     * @param $offer_id
     * @return mixed
     */
    public static function getOfferById($offer_id)
    {
        return GoodsOffer::find($offer_id);
    }


    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAllOffer()
    {
        return GoodsOffer::all()->toArray();
    }


    /**
     * 获得报价列表
     * @param $page_size
     * @return mixed
     */
    public static function offerList($page_size)
    {
        return GoodsOffer::select('id','seller_id','account_businesses_id','goods_id','goods_name','offer_pattern_id',
            'delivery_address_id','order_unit','price',
            'offer_starttime','offer_endtime',
            'status','review_status','created_at')->with('GoodsOfferPattern')
            ->WithOnly('businesses',['name'])->with('address')->orderBy('id', 'desc')->paginate($page_size);
    }


    /**
     * 获得报价模式列表
     * @return mixed
     */
    public static function getOfferPattern()
    {
        return GoodsOfferPattern::where('status',GoodsOfferPattern::STATUS['enable'])
                                ->select('id', 'name')
            ->orderBy('id', 'desc')->get()->toArray();
    }


    /**
     * @param $id
     * @return mixed
     */
    public static function getOfferPatternByOfferId($id)
    {
        if(!is_array($id)){
            $id = explode(',',$id);
        }

        return GoodsOfferPattern::whereIn('id',$id)
            ->where('status',GoodsOfferPattern::STATUS['enable'])
            ->select('id', 'name')
            ->get();
    }

    /**
     * 通过id获取商品品类
     * @param $id
     * @return mixed
     */
    public static function getOfferPatternById($id)
    {
        return GoodsOfferPattern::where('id',$id)
                                ->where('status',GoodsOfferPattern::STATUS['enable'])
                                ->select('id', 'name')
                                ->first()->toArray();
    }


    /**
     * 获得报价属性
     * @param $pattern_id
     * @return mixed
     */
    public static function getOfferAttrsByPatternId($pattern_id)
    {
        return GoodsOfferAttribute::where('pattern_id',$pattern_id)->get();
    }

    /**
     * 新增报价
     * @param $data
     * @return object
     */
    public static function createOffer($data)
    {
        return GoodsOffer::create($data);
    }

    /**
     * 通过id更新报价
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updateOfferById($id, $data)
    {
        return GoodsOffer::where('id',$id)->update($data);
    }


    /**
     * @param $data
     * @return bool|int
     */
    public static function updateDeliveryTime($data)
    {
        return GoodsOffer::orderBy('id','asc')->update($data);
    }


    /**
     * 通过id获取商品报价
     * @param $id
     * @return mixed
     */
    public static function getGoodsOfferById($id)
    {
        return GoodsOffer::with('good.category')->with('pattern.attribute')->with('address')->with('businesses')->find($id);
    }

    /**
     * 获取卖家报价列表
     * @param $seller_id
     * @return mixed
     */
    public static function getGoodsOfferListBySellerId($seller_id,$type)
    {
        $goods_offer = GoodsOffer::where('seller_id',$seller_id);
        if($type == GoodsOffer::TYPE['waiting']){
            $goods_offer->where('review_status',GoodsOffer::REVIEW_STATUS['waiting'])
//                        ->orwhere('review_status',GoodsOffer::REVIEW_STATUS['failed'])
                        ->where('status',GoodsOffer::STATUS['enable']);
        }

        if($type == GoodsOffer::TYPE['selling']){
            $goods_offer->where('review_status',GoodsOffer::REVIEW_STATUS['passed'])
                        ->where('status',GoodsOffer::STATUS['enable']);
        }

        if($type == GoodsOffer::TYPE['off_sell']){
            $goods_offer->where('status',GoodsOffer::STATUS['disable']);
        }

        return $goods_offer->paginate(GoodsOffer::PAGE_NUM);
    }


    /**
     * 获取员工报价列表
     * @param $account_employee_id
     * @return mixed
     */
    public static function getGoodsOfferListByAccountEmployeeId($account_employee_id,$type)
    {
        $goods_offer = GoodsOffer::where('account_employee_id',$account_employee_id);
        if($type == GoodsOffer::TYPE['waiting']){
            $goods_offer->where('review_status',GoodsOffer::REVIEW_STATUS['waiting'])
                ->orwhere('review_status',GoodsOffer::REVIEW_STATUS['failed'])
                ->where('status',GoodsOffer::STATUS['enable']);
        }

        if($type == GoodsOffer::TYPE['selling']){
            $goods_offer->where('review_status',GoodsOffer::REVIEW_STATUS['passed'])
                ->where('status',GoodsOffer::STATUS['enable']);
        }

        if($type == GoodsOffer::TYPE['off_sell']){
            $goods_offer->where('status',GoodsOffer::STATUS['disable']);
        }

        return $goods_offer->paginate(GoodsOffer::PAGE_NUM);
    }

    /**
     * 通过报价id删除报价
     * @param $id
     * @return int
     */
    public static function deleteGoodsOfferById($id)
    {
        return GoodsOffer::destroy($id);
    }


    /**
     * 软删除
     * @param $id
     * @return bool|null
     */
    public static function softDeleteGoodsOfferById($id)
    {
        return GoodsOffer::find($id)->delete();
    }



    /**
     * 通过报价id删除报价模式
     * @param $id
     * @return int
     */
    public static function deleteGoodsOfferPatternsById($id)
    {
        return GoodsOfferPattern::where('id',$id)->where('is_deleted',GoodsOfferPattern::DELETED['enable'])->delete();
    }


    /**
     * 获取推荐报价
     * @param null $search_keywords
     * @return mixed
     */
    public static function getAectiveGoodsOffer($page_size,$search_data=null)
    {

        $goods_offer = GoodsOffer::where('review_status',GoodsOffer::REVIEW_STATUS['passed'])
                                 ->where('status',GoodsOffer::STATUS['enable']);

        if($search_data){
            $goods_offer = $goods_offer->where($search_data);
        }

        return $goods_offer->paginate(100);
    }

    /**
     * 获取有效报价
     * @param $search_keywords
     * @return mixed
     */
    public static function getAectiveGoodsOfferByNameOrKeyWords($page_size,$search_keywords)
    {

        $goods_offer = GoodsOffer::where('review_status',GoodsOffer::REVIEW_STATUS['passed'])
                                 ->where('status',GoodsOffer::STATUS['enable']);

        if($search_keywords){
            $goods_offer = $goods_offer->where('search_keywords', 'like','%'.$search_keywords . '%')
//                                       ->orwhere('goods_name', 'like','%'.$search_keywords . '%')
//                                       ->orwhere('offer_pattern_name', 'like','%'.$search_keywords . '%')
//                                       ->orwhere('brand_name', 'like','%'.$search_keywords . '%')
//                                       ->orwhere('category_name', 'like','%'.$search_keywords . '%')
//                                       ->orwhere('product_area', 'like','%'.$search_keywords . '%');
                ->where('goods_name', 'like','%'.$search_keywords . '%')
                ->orwhere('offer_pattern_name', 'like','%'.$search_keywords . '%')
                ->orwhere('brand_name', 'like','%'.$search_keywords . '%')
                ->orwhere('category_name', 'like','%'.$search_keywords . '%')
                ->orwhere('product_area', 'like','%'.$search_keywords . '%');
        }

        return $goods_offer->orderBy('updated_at','desc')->paginate($page_size);
    }


    /**
     * 获取可用的商品
     * @param $input array
     * @return mixed
     */
    public static function offerGoods($input)
    {
        $query=Goods::select('id','name','category_id','seller_id','status','delivery_address_id');

        if($input['action']=="enabled")
        {
            $query->where('status',Goods::STATUS['enable']);
        }

        if($input['action']=="disable")
        {
            $query->where('status',Goods::STATUS['disable']);
        }

        if(!empty($input['name']))
        {
            $query->where('name','like','%'.$input['name'].'%');
        }

        $query=$query->where('seller_id',$input['seller_id'])->with('category')->get();

        return $query;

    }

    /**
     * 获取可用的报价模式
     * @param $input array
     * @return mixed
     */
    public static function offerPattern($input)
    {
        $query=GoodsOfferPattern::select('*');

        if($input['action']=="enabled")
        {
            $query->where('status',Goods::STATUS['enable']);
        }

        if($input['action']=="disable")
        {
            $query->where('status',Goods::STATUS['disable']);
        }

        if(!empty($input['name']))
        {
            $query->where('name','like','%'.$input['name'].'%');
        }

        $query=$query->whereIn('id',$input['pattern_list'])->with('attribute')->get();

        return $query;

    }


    /**
     * 获取地址
     * @param $input array
     * @return mixed
     */
    public static function offerAddress($input)
    {
        $query=GoodsDeliveryAddress::select('*');
        if($input['action']=="enabled")
        {
            $query->where('status',Goods::STATUS['enable']);
        }

        if($input['action']=="disable")
        {
            $query->where('status',Goods::STATUS['disable']);
        }

        if(!empty($input['name']))
        {
            $query->where('name','like','%'.$input['name'].'%');
        }

        $query=$query->whereIn('id',$input['address_list'])->get();

        return $query;

    }


    /**
     * 搜索报价
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchOffer($where,$page_size)
    {

        $query=GoodsOffer::select('id','account_businesses_id','goods_id','seller_id','goods_name','offer_pattern_id',
            'delivery_address_id','order_unit','price',
            'offer_starttime','offer_endtime',
            'status','review_status','created_at')->WithOnly('businesses',['name'])->with('GoodsOfferPattern')->with('address');
        //商品名称
        if(isset($where['goods_name']))
        {
            $query->where('goods_name','like','%'.$where['goods_name'].'%');
            unset($where['goods_name']);
        }
        return $query->where($where)->paginate($page_size);
    }


    /**
     * 新增报价属性
     * @param $data
     * @return object
     */
    public static function createOfferAttribute($data)
    {
        return GoodsOfferAttribute::create($data);
    }


    /**
     * 新增报价模式
     * @param $data
     * @return object
     */
    public static function addOfferPatterns($data)
    {
        return GoodsOfferPattern::create($data);
    }

    /**
     * 修改报价属性
     * @param $data array
     * @param $pattern_id int
     * @return mixed
     */
    public static function updateOfferAttribute(array $data,$pattern_id=0)
    {
        unset($data['pattern_id']);
        return $admin = GoodsOfferAttribute::where('pattern_id', $pattern_id)->update($data);
    }


    /**
     * 修改报价模式属性
     * @param $data array
     * @param $patterns_id int
     * @return mixed
     */
    public static function updateOfferPatterns(array $data,$patterns_id=0)
    {
        unset($data['patterns_id']);
        return $admin = GoodsOfferPattern::where('id', $patterns_id)->update($data);
    }


    /**
     * 获得报价列表
     * @param $page_size
     * @return mixed
     */
    public static function OfferAttributeList($page_size)
    {
        return GoodsOfferAttribute::paginate($page_size);
    }


    /**
     * 获得报价模式列表
     * @param $page_size
     * @return mixed
     */
    public static function OfferPatternsList($page_size)
    {
        return GoodsOfferPattern::paginate($page_size);
    }


    /**
     * 搜索报价模式列表
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchOfferPatterns($where,$page_size)
    {
        $query=GoodsOfferPattern::select('*');

        //名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->where($where)->paginate($page_size);
    }


    /**
     * 报价列表字段描述
     * @return mixed
     */
    public static function offerListFieldDescribe()
    {
        $data['status']=GoodsOffer::STATUS_DESCRIBE;
        $data['review_status']=GoodsOffer::REVIEW_STATUS_DESCRIBE;
        return $data;
    }


    /**
     * 报价模式列表字段描述
     * @return mixed
     */
    public static function offerPatternsListFieldDescribe()
    {
        $data['status']=GoodsOfferPattern::STATUS_DESCRIBE;
        $data['pattern_status']=GoodsOfferPattern::OFFER_PATTERN_DESCRIBE;
        return $data;
    }


    /**
     * 报价模式属性列表字段描述
     * @return mixed
     */
    public static function offerAttributesListFieldDescribe()
    {
        $data['control_type']=GoodsOfferAttribute::CONTROL_TYPE_DESCRIBE;
        $data['is_necessary']=GoodsOfferAttribute::IS_NECESSARY_DESCRIBE;
        return $data;
    }

    /**
     * 根据ID获取报价模式详情
     * @param $id int
     * @return mixed
     */
    public static function getOfferPatternsById($id)
    {
        return GoodsOfferPattern::with('attribute')->find($id);
    }


    /**
     * 根据报价ID获取报价模式属性详情
     * @param $pattern_id int
     * @return mixed
     */
    public static function getOfferPatternsAttributeById($pattern_id)
    {
        return GoodsOfferAttribute::where('pattern_id',$pattern_id)->first();
    }



    /**
     * 根据报价模式ID获取报价表是否存在此报价模式
     * @param $id int
     * @return mixed
     */
    public static function getGoodsOfferByPatterns($id)
    {
        return GoodsOffer::where('offer_pattern_id',$id)->get()->count();
    }


    /**
     * 根据报价模式ID获取报价模式属性表是否存在此报价模式
     * @param $id int
     * @return mixed
     */
    public static function getGoodsOfferByAttributes($id)
    {
        return GoodsOfferAttribute::where('pattern_id',$id)->get()->count();
    }


    /**
     * 根据报价模式ID获取商品品类是否存在此报价模式
     * @param $id int
     * @return mixed
     */
    public static function getGoodsByCategories($id)
    {
        return GoodsCategory::where('offer_type','like','%'.$id.'%')->get()->count();
    }


    /**
     * 通过卖家id获取报价
     * @param $seller_id
     * @param $page_size
     * @return mixed
     */
    public static function getGoodsOfferListsBySellerId($seller_id, $page_size)
    {

        return GoodsOffer::where('seller_id',$seller_id)
            ->orderBy('updated_at','desc')
            ->paginate($page_size);
    }


    /**
     * 根据报价分类ID删除属性
     * @param $pattern_id
     * @return object
     */
    public static function deleteOfferPatternsAttributeById($pattern_id)
    {
        return GoodsOfferAttribute::where('pattern_id',$pattern_id)->delete();
    }



    /**
     * 校验报价名称是否重复
     * @param $name string
     * @param $offer_id int
     * @return mixed
     */
    public static function uniqueOfferName($name,$offer_id)
    {
        return GoodsOfferPattern::where('id','<>',$offer_id)->where('name',$name)->first();
    }


    /**
     * 商贸通获取报价
     * @param $business_id
     * @param $page_size
     * @return mixed
     */
    public static function getOfferListById($business_id, $page_size)
    {
        return GoodsOffer::select('*')->whereIn('account_businesses_id',$business_id)->with('GoodsOfferPattern')
            ->WithOnly('businesses',['name'])->with('address')->with('goods')->orderBy('offer_starttime', 'desc')->paginate($page_size);
    }


    /**
     * 通过企业id获取报价
     * @param $business_id
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getOfferByBusinessId($business_id,$beginToday,$endToday)
    {
        return GoodsOffer::whereIn('account_businesses_id',$business_id)
             ->whereBetween('updated_at',[$beginToday, $endToday])
             ->where('offer_pattern_name','!=',GoodsOfferPattern::OFFER_PATTERN['basis_price'])
             ->get();
    }

    /**
     * 通过企业查找报价
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchOfferByBusinessId($business_id,$where, $page_size)
    {

        if(!is_array($business_id)){
            $business_id = explode(',',$business_id);
        }
        $query=GoodsOffer::select('*')->whereIn('account_businesses_id',$business_id)->WithOnly('businesses',['name'])->with('GoodsOfferPattern')->with('address');
        //商品名称
        if(isset($where['goods_name']))
        {
            $query->where('goods_name','like','%'.$where['goods_name'].'%');
            unset($where['goods_name']);
        }
        return $query->where($where)->paginate($page_size);
    }


    /**
     * @return mixed
     */
    public static function searchDistinctParam()
    {
        $data['offer_pattern_name'] = GoodsOffer::select('offer_pattern_name')->distinct('offer_pattern_name')->get();
        $data['brand_name'] = GoodsOffer::select('brand_name')->distinct('brand_name')->get();
        $data['category_name'] = GoodsOffer::select('category_name')->distinct('category_name')->get();
        $data['product_area'] = GoodsOffer::select('product_area')->distinct('product_area')->get();
        return $data;
    }

}