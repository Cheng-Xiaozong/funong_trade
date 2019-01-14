<?php

namespace App\Http\Middleware;

use App\Services\SignService;
use Closure;
use Illuminate\Support\Facades\Redis;

class VerifySignature
{

    /**
     * 签名验证
     * @param $app_id
     * @param $sign
     * @param $parameters
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifySignature($app_id, $sign, $parameters)
    {
        try {
            $server_secret = Redis::get($app_id . '_' . config('ext.rediskey'));

        } catch (\Exception $exception) {
            return apiReturn(-500, '服务器错误');
        }

        if (!$server_secret) {
            return apiReturn(-101, '客户端非法');
        }

        $parameters = json_decode($parameters, true);
        $str = '';
        if (!empty($parameters)) {
            foreach ($parameters as $key => $value) {
                $arr[$key] = $key;
            }
            sort($arr);
            foreach ($arr as $k => $v) {
                if (is_array($parameters[$v])) {
                    $str = $str . $arr[$k] . json_encode($parameters[$v],JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE);
                } else {
                    $str = $str . $arr[$k] . $parameters[$v];
                }
            }
        }
        $str = $str . $server_secret;
        $server_sign = strtoupper(sha1($str));
        if ($sign != $server_sign) {
            return apiReturn(-102, '非法请求');
        }
    }

    /**
     * 验证签名合法性
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(config('app.debug')==true) return $next($request);
        $act = $request->get('act');
        $app_id = $request->get('appid');
        $sign = $request->get('sign');
        $parameters = $request->getContent();
        if (is_null($act) || is_null($app_id) || is_null($sign)) {
            return apiReturn('-100', '缺少参数');
        }
        if (is_null($this->verifySignature($app_id, $sign, $parameters))) {
            return $next($request);
        } else {
            return $this->verifySignature($app_id, $sign, $parameters);
        }
    }
}
