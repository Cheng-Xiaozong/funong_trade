<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Services\CommonService;
use App\Services\SendWeChatMsgService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class CommonController extends BaseController
{
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Validator;
    protected $Common;
    protected $SendWeChatMsg;


    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        Validator $validator,
        CommonService $common,
        SendWeChatMsgService $sendWeChatMsg
    ){
        parent::__construct($request, $log, $redis);
        $this->Validator = $validator;
        $this->Common = $common;
        $this->SendWeChatMsg = $sendWeChatMsg;
    }

    /**
     * 返回所有字段描述
     * @return \Illuminate\Http\JsonResponse
     */
    public function fieldDescribe()
    {
        $data['field_describe']=$this->Common::fieldDescribe();
        return apiArrayReturn(0,'获取成功',$data);
    }

    /**
     * 注册省市县选择
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSonAreaInfo()
    {
        $validator = $this->Validator::make($this->Input, [
            'area_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['area']=$this->Common::getSonAreaInfo($this->Input['area_id']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 发送消息
     * @param $param
     * @return array|bool
     */
    public function socketMessage($param)
    {

        //非线上环境不发消息
        if(env('MQTT_ENV','dev') != 'prd'){
            return true;
        }
        $first = json_encode($param['data']['data']['first'],JSON_UNESCAPED_UNICODE);
        $system = $this->Request->header('system');

        if(empty($system)){
            $system = 'xxx';
        }

        $system_name = [
            'SMT'   =>  '商贸通后台',
            'ERP'   =>  'ERP后台',
            'android'   =>  '商贸通APP',
            'wechat'   =>  '微信公众号',
            'xxx'   =>  '未知平台',
        ];

        $first = vsprintf($first,array($system_name[$system]));
        $param['data']['data']['first'] = json_decode($first,true);

        if(is_array($param)){
            $param = json_encode($param);
        }

        $url = 'http://'.$_SERVER['HTTP_HOST'].'/admin/Common/api?act=getWechatMsgUrl';
        $host = parse_url($url,PHP_URL_HOST);
        $port = parse_url($url,PHP_URL_PORT);
        $port = $port ? $port : 80;
        $scheme = parse_url($url,PHP_URL_SCHEME);
        $path = parse_url($url,PHP_URL_PATH);
        $query = parse_url($url,PHP_URL_QUERY);

        if($query) $path .= '?'.$query;
        if($scheme == 'https') {
            $host = 'ssl://'.$host;
        }

        $fp = fsockopen($host,$port,$error_code,$error_msg,1);
        if(!$fp) {
            return array('error_code' => $error_code,'error_msg' => $error_msg);
        }
        else {
            stream_set_blocking($fp,true);//开启非阻塞模式
            stream_set_timeout($fp,1);//设置超时
            $header = "POST $path HTTP/1.1\r\n";
            $header.="Host: $host\r\n";
            $header .= "content-length:".strlen($param)."\r\n";
            $header .= "content-type:application/json\r\n";
            $header.="Connection: close\r\n\r\n";//长连接关闭
            $header .= $param;
            fwrite($fp, $header);
            usleep(1000);
            fclose($fp);
            return array('error_code' => 0);
        }
    }


    /**
     * 异步调用
     */
    public function getWechatMsgUrl()
    {
        $content = $this->Input;
        $action = preg_replace('/\"/', '', $content['action']);;
        $this->SendWeChatMsg::$action($content['data']);
    }
}