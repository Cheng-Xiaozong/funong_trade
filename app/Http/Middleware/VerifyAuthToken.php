<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class VerifyAuthToken
{
    
    /**
     * The URIs that should be excluded from Token verification.
     *
     * @var array
     */
    protected $except = [

        '/admin/address/api?act=getSonAreaInfo',
        '/admin/address/api?act=searchDeliveryAddress',
        '/admin/goods/api?act=getGoodsCategories',
        '/admin/offer/api?act=getOfferPattern',
        '/admin/user/api?act=login',
        '/admin/user/api?act=loginOut',
        '/admin/user/api?act=refreshToken',
        '/admin/account/api?act=getAccountById',
        '/admin/Common/api?act=fieldDescribe',
        '/admin/goods/api?act=goodsTemplate',
        '/admin/Common/api?act=getWechatMsgUrl',
        '/admin/offer/api?act=editGoodsOffer',
        '/admin/offer/api?act=addOffer',
        '/admin/offer/api?act=getOfferPattern',
        '/admin/goods/api?act=goodsTemplate',
        '/admin/goods/api?act=deleteGoods',
        '/admin/address/api?act=getGoodsAddressById',
        '/admin/address/api?act=editDeliveryAddress',
        '/admin/address/api?act=deleteAddress',

        '/home/address/api?act=getSonAreaInfo',
        '/home/account/api?act=sendVerifyCode',
        '/home/account/api?act=register',
        '/home/account/api?act=login',
        '/home/account/api?act=weChatOfficialLogin',
        '/home/account/api?act=weChatOfficialRegister',
        '/home/account/api?act=forgetPassword',
        '/home/goods/api?act=getGoodsCategories',
        '/home/goods/api?act=goodsTemplate',
        '/home/goods/api?act=goodsDetail',
        '/home/offer/api?act=goodsOfferDetail',
        '/home/offer/api?act=getOfferPattern',
        '/home/offer/api?act=getFilterParam',
        '/home/offer/api?act=offerTemplate',
        '/home/offer/api?act=recommendGoodsOffer',
        '/home/offer/api?act=recommendGoodsOffer2',

        '/home/system/api?act=getAppVersion',
        '/home/system/api?act=indexBanner',

        '/dealers/contract/api?act=changeOrderStatus',
        '/dealers/contract/api?act=getContractListBySeller',
        '/dealers/contract/api?act=relationAccount',
        '/dealers/contract/api?act=relationAccountList',
        '/dealers/contract/api?act=searchRelationAccountList',
        '/dealers/contract/api?act=getBuyerDetail',
        '/dealers/contract/api?act=goodsList',
        '/dealers/contract/api?act=searchGoods',
        '/dealers/contract/api?act=getGoodsById',
        '/dealers/contract/api?act=offerList',
        '/dealers/contract/api?act=searchOffer',
        '/dealers/contract/api?act=goodsOfferDetail',
        '/dealers/contract/api?act=orderList',
        '/dealers/contract/api?act=disableOrderById',
        '/dealers/contract/api?act=SearchOrderList',
        '/dealers/contract/api?act=getOrderById',
        '/dealers/contract/api?act=editGoodsOffer',
        '/dealers/contract/api?act=offerAddress',
        '/dealers/contract/api?act=getOfferPattern',
        '/dealers/contract/api?act=deleteGoodsOffer',
        '/dealers/contract/api?act=modifyGoodsOffer',
        '/dealers/contract/api?act=confirmOrder',
        '/dealers/contract/api?act=offerPatternList',
        '/dealers/contract/api?act=goodsDetail',
        '/dealers/contract/api?act=addTest',
        '/dealers/contract/api?act=getUnionid',
        '/dealers/contract/api?act=getAllSelfTemp',
        '/dealers/contract/api?act=getWeiContractList',
        '/dealers/contract/api?act=offerTeach',
        '/dealers/contract/api?act=addNames',
        '/dealers/contract/api?act=countAccount',
        '/dealers/contract/api?act=countContractNum',
        '/dealers/contract/api?act=countOrder',
        '/dealers/contract/api?act=countSellOrder',
        '/dealers/contract/api?act=countPurchaseOrder',
        '/dealers/contract/api?act=countDeposit',
        '/dealers/contract/api?act=getCustomerUnionId',
        '/dealers/contract/api?act=countProfitAndLoss',
        '/dealers/contract/api?act=addDeliveryAddress',
        '/dealers/contract/api?act=deliveryAddressListBySellerId',
        '/dealers/contract/api?act=deliveryAddressList',
        '/dealers/contract/api?act=searchContract',

        //测试路由
        '/home/offer/api?act=mqttTest',
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $route = '/' . $request->route()->getUri() . '?act=' . $request->get('act');
        if (in_array($route, $this->except)) {
            return $next($request);
        } else {
            if (is_null($request->header('token'))) return apiReturn(-200, 'Token does not exist');
            try {
                $user = JWTAuth::toUser($request->header('token'));
            } catch (\Exception $exception) {
                if ($exception instanceof TokenExpiredException) {
                    return apiReturn(-201, 'Token Expired');
                } else if ($exception instanceof TokenInvalidException) {
                    return apiReturn(-202, 'Token Invalid');
                } else if ($exception instanceof TokenBlacklistedException) {
                    return apiReturn(-203, 'Token Blacklisted');
                } else {
                    return apiReturn(-204, 'Token validation fails');
                }
            }
            return $user ? $next($request) : response(apiReturn(-205, 'User not found'));
        }
    }
    
    
}
