<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/12
 * Time: 11:12
 */
namespace App\Services;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AddressLocateService
{

    protected $Redis;

    public function __construct(Redis $redis)
    {
        $this->Redis = $redis;
    }

    /**
     * 获取定位
     * @param $address
     * @return bool
     */
    public  function addressLocate($address)
    {

        if(empty($address)){
            return false;
        }

        //{"status":0,"result":{"location":{"lng":121.6384813140922,"lat":31.23089534913395},"precise":0,"confidence":14,"level":"区县"}}
        $content = file_get_contents("http://api.map.baidu.com/geocoder/v2/?ak=d8a8Go6hFwrR89uuydEdWq6DeGEa9KEl&address={$address}&output=json");

        if(empty($content)){
            return false;
        }

        $json = json_decode($content,true);

        if(empty($json['result'])||empty($json['result']['location'])){
            return false;
        }

        $location = $json['result']['location'];

        //保存到Redis服务
        //$redis->set($address,json_encode($location));
        $this->Redis::hset("addressLocate",$address,json_encode($location));

        return $location;
    }


    /**
     * 距离计算，单位：公里
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @return float|int
     */
    public function getDistance($lat1, $lng1, $lat2, $lng2)
    {

        $earthRadius = 6367000; //approximate radius of earth in meters

        /*
        Convert these degrees to radians
        to work with the formula
        */

        $lat1 = ($lat1 * pi() ) / 180;
        $lng1 = ($lng1 * pi() ) / 180;

        $lat2 = ($lat2 * pi() ) / 180;
        $lng2 = ($lng2 * pi() ) / 180;

        /*
        Using the
        Haversine formula

        http://en.wikipedia.org/wiki/Haversine_formula

        calculate the distance
        */

        $calcLongitude = $lng2 - $lng1;
        $calcLatitude = $lat2 - $lat1;
        $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);  $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo / 1000;

        //2016-10-28  服务半径覆盖
        //1 <=300km; 2 300km - 500km ; 3 500km - 1000km;4>1000km
        /*
        if($calculatedDistance <=300) {return 1;}
        else if($calculatedDistance >300 && $calculatedDistance <=500) {return 2;}
        else if($calculatedDistance >500 && $calculatedDistance <=1000) {return 3;}
        else {return 4;}
        */

        return $calculatedDistance;
    }


    /**
     * 获取经纬度
     * @param $address
     * @return bool
     */
    public  function addressLngLat($address)
    {

        if(empty($address)){
            return false;
        }

        //{"status":0,"result":{"location":{"lng":121.6384813140922,"lat":31.23089534913395},"precise":0,"confidence":14,"level":"区县"}}
        $content = file_get_contents("http://api.map.baidu.com/geocoder/v2/?ak=d8a8Go6hFwrR89uuydEdWq6DeGEa9KEl&address={$address}&output=json");

        if(empty($content)){
            return false;
        }

        $json = json_decode($content,true);

        if(empty($json['result'])||empty($json['result']['location'])){
            return false;
        }

        $location = $json['result']['location'];

        return $location;
    }



    //根据IP获取经纬度：http://www.oschina.net/code/snippet_144656_45460
    public function GetIpLookup($ip = ''){
        if(empty($ip)){return false;}

        $content = file_get_contents("http://api.map.baidu.com/location/ip?ak=d8a8Go6hFwrR89uuydEdWq6DeGEa9KEl&ip={$ip}&coor=bd09ll");

        if(empty($content)){return false;}

        $json = json_decode($content,true);

        if(empty($json['content'])||empty($json['content']['point'])){return false;}

        $point = $json['content']['point'];

        //保存到Redis服务
        $this->Redis::hset("ipLocate",$ip,json_encode($point));
        return $point;
    }
}