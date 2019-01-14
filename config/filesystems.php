<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. A "local" driver, as well as a variety of cloud
    | based drivers are available for your choosing. Just store away!
    |
    | Supported: "local", "ftp", "s3", "rackspace"
    |
    */

    'default' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => 's3',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
        ],

        //商品圖片
        'goods_imgs' => [
            'driver' => 'local',
            'root' => public_path('files/goods_imgs'),
            'visibility' => 'public',
        ],

        //商品視頻
        'goods_vedios' => [
            'driver' => 'local',
            'root' => public_path('files/goods_vedios'),
            'visibility' => 'public',
        ],

        'admin_imgs' => [
            'driver' => 'local',
            'root' => public_path('files/admin_imgs'),
            'visibility' => 'public',
        ],

        //企业账户信息
        'account_imgs' => [
            'driver' => 'local',
            'root' => public_path('files/account_imgs'),
            'visibility' => 'public',
        ],

        //商品分类图片
        'goods_category_images' => [
            'driver' => 'local',
            'root' => public_path('files/goods_category_images'),
            'visibility' => 'public',
        ],

        //版本文件
        'app_version_files' => [
            'driver' => 'local',
            'root' => public_path('files/app_version_files'),
            'visibility' => 'public',
        ],

        //轮播图片
        'banner_imgs' => [
            'driver' => 'local',
            'root' => public_path('files/banner_imgs'),
            'visibility' => 'public',
        ],

        //付款凭证
        'payment_imgs' => [
            'driver' => 'local',
            'root' => public_path('files/payment_imgs'),
            'visibility' => 'public',
        ],


        //报价教学
        'imgs' => [
            'driver' => 'local',
            'root' => public_path('imgs'),
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => 'your-key',
            'secret' => 'your-secret',
            'region' => 'your-region',
            'bucket' => 'your-bucket',
        ],

    ],

];
