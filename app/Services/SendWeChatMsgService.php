<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2018/02/27
 * Time: 11:12
 */
namespace App\Services;

class SendWeChatMsgService
{

    protected  static $hostname = "redis.efunong.com";
    protected  static $port = "8000";
    protected  static $url = "http://redis.efunong.com:8000";
    protected  static $appId ="wxc179ff8c019bd102";

    /**
     * 用户注册
     * @param $data
     */
    public static function userRegister($data)
    {
//        $data= array(
//            'data' => array (
//                'first'    => array('value' => "来自应用：商贸通APP v4.0\n注册类型：卖家\n主体身份：个人"),
//                'keyword1' => array('value' => "张三，1399990000**"),
//                'keyword2' => array('value' => "2018-04-09 12:09:09"),
//                'remark'   => array('value' => "\n请及时进行审核！")
//            )
//        );
        $type='USER_REGIST';
        self::sendNotify($type,$data);
    }

    /**
     * 商品、报价审核
     * @param $data
     */
    public static function productChange($data)
    {
//        $data=array(
//            'data' => array (
//                'first'    => array('value' => "来自应用：商贸通APP v4.0\n提交时间：2018-04-09 12:09:09\n卖家信息：张三，130000******"),
//                'keyword1' => array('value' => "50kg，43%，嘉吉，豆粕"),
//                'keyword2' => array('value' => "报价"),
//                'keyword3' => array('value' => "南通嘉吉工厂，M1809+80"),
//                'remark'   => array('value' => "\n请及时进行审核！")
//            )
//        );
        $type='PRODUCT_CHANGE';
        self::sendNotify($type,$data);
    }


    /**
     * 新订单提醒
     * @param $data
     */
    public static function orderCreate($data)
    {

//        $data= array(
//            'data' => array (
//                'first'    => array('value' => "来自应用：商贸通APP v4.0\n提交时间：2018-04-09 12:09:09\n卖家信息：张三，130000******"),
//                'keyword1' => array('value' => "2018090909"),
//                'keyword2' => array('value' => "50kg，43%，嘉吉，豆粕\n提货地点：南通嘉吉工厂"),
//                'keyword3' => array('value' => "M1809+80,100吨"),
//                'keyword4' => array('value' => "基差"),
//                'keyword5' => array('value' => "李四，13000*****"),
//                'remark'   => array('value' => "\n请及时进行处理！")
//            )
//        );
        $type='ORDER_CREATE';

        self::sendNotify($type,$data);
    }

    /**
     * 订单取消
     * @param $data
     */
    public static function orderCancel($data)
    {
//        $data= array(
//            'data' => array (
//                'first'    => array('value' => "卖家信息：张三，130000******\n买家信息：李四，131000******\n商品信息：50kg，43%，嘉吉，豆粕\n价格数量：M1809+80,100吨"),
//                'keyword1' => array('value' => "2018090909"),
//                'keyword2' => array('value' => "2018-04-09 12:09:09"),
//                'keyword3' => array('value' => "2018-04-09 14:19:29"),
//                'keyword4' => array('value' => "买家取消|卖家取消|平台取消"),
//                'remark'   => array('value' => "\n请中断处理！")
//            )
//        );
        $type='ORDER_CANCEL';
        self::sendNotify($type,$data);
    }

    /**
     * 提货申请
     * @param $data
     */
    public static function orderDelivery($data)
    {
//        $data= array(
//            'data' => array (
//                'first'    => array('value' => "平台代申请|买家发起申请\n\n卖家信息：张三，130000******\n买家信息：徐桂明，131000******"),
//                'keyword1' => array('value' => "2018090909\n申请时间：2018-04-09 12:09:09"),
//                'keyword2' => array('value' => "50kg、43%、嘉吉、豆粕"),
//                'keyword3' => array('value' => "自提，平台代办，5车100吨"),
//                'remark'   => array('value' => "\n请及时处理！")
//            )
//        );

        $type='ORDER_DELIVERY';
        self::sendNotify($type,$data);
    }


    /**
     * 提货反馈信息
     * @param $data
     */
    public static function sentDeliveryCallback($data)
    {
        $type = 'WORK_TIME_NOTIFY';
        self::sendNotify($type,$data);
    }

    /**
     * 发送通知
     * @param $type
     * @param $body
     */
    public static function sendNotify($type, $body)
    {
        #组装数据
        $data['act']='sendNotify';
        $data['appId']=self::$appId;
        $data['type']=$type;
        $data['notifyBody']=$body;

        # 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,self::$url);
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
        print_r($temp."\n");
    }
}