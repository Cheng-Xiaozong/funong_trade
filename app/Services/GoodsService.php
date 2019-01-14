<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/12
 * Time: 11:12
 */
namespace App\Services;

use App\GoodsCategory;
use App\GoodsCategoryAttribute;
use App\Goods;
use App\GoodsCategoryTrade;
use App\GoodsDeliveryAddress;
use App\GoodsOfferAttribute;

class GoodsService
{

    /**
     * 通过id获取商品品类
     * @param $id
     * @return mixed
     */
    public static function getCategoryById($id)
    {
        return GoodsCategory::find($id);
    }

    /**
     * 获取商品品类集合
     * @return mixed
     */
    public static function getGoodsCategories()
    {
        return GoodsCategory::where('status',GoodsCategory::STATUS['enable'])->get()->toArray();
    }

    /**
     * 通过品类id获取品类属性
     * @param $categort_id
     * @return mixed
     */
    public static function getGoodsCategoriesByCategoryId($categort_id)
    {
        return GoodsCategoryAttribute::where('category_id',$categort_id)
                                     ->get()
                                     ->toArray();
    }


    /**
     * 通过id获取品类属性
     * @param $category_id
     * @return mixed
     */
    public static function getAttrsByCategoryId($category_id)
    {
        return GoodsCategoryAttribute::where('category_id',$category_id)->orderBy('sort')->get();
    }

    /**
     * 新增商品
     * @param $data
     * @return object
     */
    public static function createGoods($data)
    {
        return Goods::create($data);
    }

    /**
     * 新增商品品类
     * @param $data
     * @return object
     */
    public static function createGoodsCategory($data)
    {
        return GoodsCategory::create($data);
    }

    /**
     * 根据名称获取
     * @param $name
     */
    public static function getGoodsCategoryByName($name)
    {
        return GoodsCategory::where('name',$name)->get();
    }


    /**
     * 新增商品品类属性
     * @param $data
     * @return object
     */
    public static function createGoodsCategoryAttribute($data)
    {
        return GoodsCategoryAttribute::create($data);
    }

    /**
     * 新增商品品类交易日期
     * @param $data
     * @return object
     */
    public static function createGoodsCategoryTrade($data)
    {
        return GoodsCategoryTrade::create($data);
    }

    /**
     * 通过id更新商品分类
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updateGoodsCategoryById($id, $data)
    {
        return GoodsCategory::where('id',$id)->update($data);
    }


    /**
     * 通过分类ID更新交易时间
     * @param $category_id
     * @param $data
     * @return mixed
     */
    public static function updateGoodsCategoryTradeByCategoryId($category_id, $data)
    {
        return GoodsCategoryTrade::where('category_id',$category_id)->update($data);
    }

    /**
     * 根据商品分类ID删除分类属性
     * @param $category_id
     * @return object
     */
    public static function deleteGoodsCategoryAttributeByGoodsCategoryId($category_id)
    {
        return GoodsCategoryAttribute::where('category_id',$category_id)->delete();
    }

    /**
     * 通过分类ID删除交易时间
     * @param $category_id
     * @return mixed
     */
    public static function deleteGoodsCategoryTradeByCategoryId($category_id)
    {
        return GoodsCategoryTrade::where('category_id',$category_id)->delete();
    }


    /**
     * 通过id更新商品
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updateGoodsById($id, $data)
    {
        return Goods::where('id',$id)->update($data);
    }

    /**
     * 通过卖家id获取商品列表
     * @param $seller_id
     * @return mixed
     */
    public static function getGoodsListsBySellerId($seller_id)
    {

        return Goods::where('seller_id',$seller_id)
                    ->orderBy('updated_at','desc')
                    ->get();
    }

    /**
     * 通过员工id获取商品
     * @param $employee_id
     * @return mixed
     */
    public static function getGoodsListsByAccountEmployeeId($employee_id)
    {

        return Goods::where('account_employee_id',$employee_id)
            ->orderBy('updated_at','desc')
            ->get();
    }

    /**
     * 通过id获取商品
     * @param $goods_id
     * @return mixed
     */
    public static function getGoodsById($goods_id)
    {
        return Goods::find($goods_id);
    }

    /**
     * 通过id删除商品
     * @param $id
     * @return int
     */
    public static function deleteGoodsById($id)
    {
        return Goods::destroy($id);
    }


    /**
     * 获取全部商品
     * @param $page_size
     * @return mixed
     */
    public static function getAllGoods($page_size)
    {
        return Goods::with(['AccountBusiness' => function ($query) {
            $query->select('id','name');
        }])->with(['GoodsCategory' => function ($query) {
            $query->select('id','name');
        }])->paginate($page_size);
    }


    /**
     * 获取全部审核通过
     * @param $page_size
     * @return mixed
     */
    public static function getAllPassedGoods($page_size,$where=null)
    {
        $query = Goods::where('status',Goods::STATUS['enable'])
                      ->where('review_status',Goods::REVIEW_STATUS['passed']);

        if($where){
            $query = $query->where($where);
        }
        return $query->with('category')
                     ->paginate($page_size);

    }


    /**
     * 搜索商品
     * @param $page_size
     * @return mixed
     */
    public static function searchGoods($where,$page_size)
    {
        $query=Goods::select('*')->with(['AccountBusiness' => function ($query) {
            $query->select('id','name');
        }])->with(['GoodsCategory' => function ($query) {
            $query->select('id','name');
        }]);
        //商品名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->where($where)->with('category')->paginate($page_size);
    }

    /**
     * 商品列表字段描述
     * @return mixed
     */
    public static function goodsListFieldDescribe()
    {
        $data['status']=Goods::STATUS_DESCRIBE;
        $data['review_status']=Goods::REVIEW_STATUS_DESCRIBE;
        return $data;
    }

    /**
     * 商品列表字段
     * @return mixed
     */
    public static function goodsFieldDescribe()
    {
        $data['status']=Goods::STATUS_DESCRIBE;
        $data['review_status']=Goods::REVIEW_STATUS_DESCRIBE;
        $data['delivery_address']['status']=GoodsDeliveryAddress::STATUS_DESCRIBE;
        $data['delivery_address']['delete_status']=GoodsDeliveryAddress::DELETE_STATUS_DESCRIBE;
        return $data;
    }

    /**
     * 商品模板字段描述
     * @return mixed
     */
    public static function goodsTemplateFieldDescribe()
    {
        $data['is_necessary']=GoodsCategoryAttribute::IS_NECESSARY_DESCRIBE;
        $data['control_type']=GoodsCategoryAttribute::CONTROL_TYPE_DESCRIBE;
        return $data;
    }

    /**
     * 是否上传视频字段描述
     * @return mixed
     */
    public static function goodsIsUploadVedioDescribe()
    {
        $data['is_upload_vedio']=GoodsCategory::IS_UPLOAD_VEDIO_DESCRIBE;
        return $data;
    }
    /**
     * 是否上传图片字段描述
     * @return mixed
     */
    public static function goodsIsUploadImageDescribe()
    {
        $data['is_upload_image']=GoodsCategory::IS_UPLOAD_IMAGE_DESCRIBE;
        return $data;
    }

    /**
     * 品类列表字段描述
     * @return mixed
     */
    public static function goodsCategorysDescribe()
    {
        $data['status']=GoodsCategory::STATUS_DESCRIBE;
        $data['is_upload_vedio']=GoodsCategory::IS_UPLOAD_VEDIO_DESCRIBE;
        $data['is_upload_image']=GoodsCategory::IS_UPLOAD_IMAGE_DESCRIBE;
        return $data;
    }

    /**
     * 品类字段描述
     * @return mixed
     */
    public static function goodsCategoryDescribe()
    {
        $data['status']=GoodsCategory::STATUS_DESCRIBE;
        $data['is_upload_vedio']=GoodsCategory::IS_UPLOAD_VEDIO_DESCRIBE;
        $data['is_upload_image']=GoodsCategory::IS_UPLOAD_IMAGE_DESCRIBE;
        $data['goods_category_attribute']['is_necessary']=GoodsCategoryAttribute::IS_NECESSARY_DESCRIBE;
        $data['goods_category_attribute']['control_type']=GoodsCategoryAttribute::CONTROL_TYPE_DESCRIBE;
        $data['goods_category_trade']['status']=GoodsCategoryTrade::STATUS_DESCRIBE;
        return $data;
    }


    /**
     * 通过品类id获取品类交易时间
     * @param $category_id
     * @return mixed
     */
    public static function getGoodsCategoryTradeByCategoryId($category_id)
    {
        return GoodsCategoryTrade::where('category_id',$category_id)->first();
    }


    /**
     * 获取所有
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAllGoodsCategoryTrade()
    {
        return GoodsCategoryTrade::all();
    }

    /**
     * 通过id删除商品分类
     * @param $id
     * @return int
     */
    public static function deleteGoodsCategoryById($id)
    {
        return GoodsCategory::destroy($id);
    }


    /**
     * 获取所有品类
     * @param $page_size
     * @return mixed
     */
    public static function getAllGoodsCategory($page_size)
    {
        return GoodsCategory::paginate($page_size);
    }

    /**
     * 获取商品分类对外接口
     * @param $action
     * @return mixed
     */
    public static function geGoodsCategoryApi($action)
    {
        $query=GoodsCategory::select('id','name');
        if($action=="enabled")
        {
            $query->where('status',GoodsCategory::STATUS['enable']);
        }

        if($action=="disable")
        {
            $query->where('status',GoodsCategory::STATUS['disable']);
        }
        return $query->get();
    }

    /**
     * 搜索品类
     * @param $page_size
     * @return mixed
     */
    public static function searchGoodsCategory($where,$page_size)
    {
        $query=GoodsCategory::select('*');
        //商品名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->where($where)->paginate($page_size);
    }


    /**
     * 通过企业id获取商品
     * @param $business_id
     * @param $page_size
     * @return mixed
     */
    public static function getGoodsByBusinessId($business_id, $page_size)
    {
        return Goods::whereIn('account_business_id',$business_id)->paginate($page_size);
    }


    /**
     * 搜索商品(商卖通)
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchTradeGoods($where, $page_size,$business_id)
    {
        $query=Goods::select('*')->with(['AccountBusiness' => function ($query) {
            $query->select('id','name');
        }])->with(['GoodsCategory' => function ($query) {
            $query->select('id','name');
        }]);
        //商品名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->whereIn('account_business_id',$business_id)->where($where)->with('category')->paginate($page_size);
    }
}