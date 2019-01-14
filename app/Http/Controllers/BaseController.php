<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/11/1
 * Time: 13:30
 */

namespace App\Http\Controllers;

class BaseController extends Controller
{

/*
                   _ooOoo_
                  o8888888o
                  88" . "88
                  (| -_- |)
                  O\  =  /O
               ____/`---'\____
             .'  \\|     |//  `.
            /  \\|||  :  |||//  \
           /  _||||| -:- |||||-  \
           |   | \\\  -  /// |   |
           | \_|  ''\---/''  |   |
           \  .-\__  `-`  ___/-. /
         ___`. .'  /--.--\  `. . __
      ."" '<  `.___\_<|>_/___.'  >'"".
     | | :  `- \`.;`\ _ /`;.`/ - ` : | |
     \  \ `-.   \_ __\ /__ _/   .-` /  /
======`-.____`-.___\_____/___.-`____.-'======
                   `=---='
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
         佛祖保佑       永无BUG
*/

    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Action;
    protected $Input;

    /**
     * 公共方法注入
     * @param $request
     * @param $log
     * @param $redis
     */
    public function __construct($request, $log, $redis)
    {
        $this->Request = $request;
        $this->Log = $log;
        $this->Redis = $redis;
        $this->Action = $this->Request->input('act');
        $this->Input=(array)json_decode($this->Request->getContent(),true);

    }


    /**
     * API处理入口
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $action = $this->Action;
        if (method_exists(static::class, $action)) {
            return $this->$action();
        } else {
            return apiReturn(-103, 'act Parameter Errors');
        }
    }

}
