<?php

return [
    //redis key
    'rediskey'=>env('REDIS_KEY','FUNONG_TRADE'),

    //erp系统主机地址
    'funong_dealers_url'=>env('FUNONG_DEALERS_URL','http://192.168.0.10:84'),

    //消息通知环境
    'mqtt_env' => env('MQTT_ENV','dev'),

    //下单间隔 单位秒
    'time_interval' => env('TIME_INTERVAL',20),

    //每单限购量 单位吨
    'single_num' => env('SINGLE_NUM',200),

    //每日限购总量 单位吨
    'monthly_order_num' => env('MONTHLY_ORDER_NUM',1000),

    //订单取消限制  单位秒
    'order_cancel_time' => env('ORDER_CANCEL_TIME',300),
];