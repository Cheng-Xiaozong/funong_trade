<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/12
 * Time: 11:12
 */
namespace App\Services;
use Illuminate\Support\Facades\Log;

class SendMsgService
{
    protected  static $url = 'https://app.efunong.com/PHPSmser/sendMsg.php';
    protected  static $callback_url ='http://bl.cn/callBackTc.php';

    protected  static $smt_url = 'http://smsapis.efunong.com/send_ordinary_template_sms.json?appid=611RkiIVA0rbT7owNSLx0euIY3DFGg';
    protected  static $appid = '611RkiIVA0rbT7owNSLx0euIY3DFGg';

    public static function sendMsg($act,$phone_list, $params)
    {
        $data = array("act" => $act,"phone" => $phone_list, "params" => [$params], "callback_url" => self::$callback_url);
        $data_string = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_string)));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public static function getStrParam(){
        $parameter = [
            'appid' => self::$appid,
            'timestamp' => time(),
            'noncestr' => substr(time(),6).rand(0,9999)
        ];

        return $parameter;
    }


    /**
     * 发送提货通知
     * @param $data
     */
    public static function sendDeliveryMsg($data)
    {
        $param['action_id'] = 'AXRhTO4WcCZ33QDA';
        $param['phone_number'] = $data;
        $parameter = array_merge(self::getStrParam(), $param);
        ksort($parameter);
        $query_string = '';
        foreach ($parameter as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v);
            }
            $query_string .= '&' . $k . '=' . $v;
        }
        $string_to_sign = '&%2F&EFUNONG&%2F&' . substr($query_string, 1);
        $signature = sha1($string_to_sign);

        $url = self::$smt_url.'&timestamp='.$parameter['timestamp'].'&noncestr='.$parameter['noncestr'].'&signature='.$signature;

        return self::sendNotify($url,$param);
    }


    /**
     * 发送通知
     * @param $data
     */
    public static function sendNotify($url,$data)
    {
        # 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        if(!empty($data)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $temp = curl_exec($ch);
        curl_close($ch);
        #输出结果
        //print_r($temp."\n");
        return $temp;
    }
}