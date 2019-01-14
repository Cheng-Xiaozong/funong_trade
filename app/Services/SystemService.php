<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/8/24
 * Time: 11:12
 */
namespace App\Services;


use App\Access;
use App\Admin;
use App\AdminAccess;
use App\AdminLogs;
use App\AdminMenu;
use App\AdminRole;
use App\Menu;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Account;
use App\AccountBusiness;
use App\AccountLog;
use App\AccountBuyer;
use App\AccountSeller;
use Tymon\JWTAuth\Token;


class SystemService
{

    /**
     * 记录日志
     * @param $data array
     * @return mixed
     */
    public static function addLogs($data)
    {
        return AdminLogs::create($data);
    }


}