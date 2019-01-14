<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/13
 * Time: 16:48
 */

if (!function_exists('alert')) {
    /**
     * 打印日志1
     * @param $content
     */
    function alert($content)
    {
        file_put_contents('logs.log', $content);
    }
}


if (!function_exists('isImage')) {
    /**
     * 是否图片
     * @param $fileName
     * @return bool
     */
    function isImage($fileName)
    {
        $file = fopen($fileName, "rb");
        $bin = fread($file, 2); // 只读2字节
        
        fclose($file);
        $strInfo = @unpack("C2chars", $bin);
        $typeCode = intval($strInfo['chars1'] . $strInfo['chars2']);
        if ($typeCode == 255216 /*jpg*/ || $typeCode == 7173 /*gif*/ || $typeCode == 13780 /*png*/) {
            return true;
        } else {
            return false;
        }
    }
}


if (!function_exists('pageing')) {
    /**
     * 为Api返回分页信息
     * @param $result
     * @return mixed
     */
    function pageing($result)
    {
        $data['total'] = $result->total();
        $data['count'] = $result->count();
        $data['per_page'] = $result->perPage();
        $data['current_page'] = $result->currentPage();
        $data['has_more_pages'] = $result->hasMorePages();
        return $data;
    }
}


if (!function_exists('isMobilePhone')) {
    /**
     * 是否手机号
     * @param $phone
     * @return bool
     */
    function isMobilePhone($phone)
    {
        $search = '/^[1][3,4,5,7,8][0-9]{9}$/';
        if (preg_match($search, $phone)) {
            return true;
        } else {
            return false;
        }
    }
}


if (!function_exists('isChinaId')) {
    /**
     * 身份证合法性检查
     * @param $id
     * @return bool
     */
    function isChinaId($id)
    {
        $preg18 = '/^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/';
        $preg15 = '/^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{2}[0-9Xx]$/';
        if (preg_match($preg18, $id) > 0) {
            $w = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
            $a = str_split(strtoupper($id), 1);
            $c = array(1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2);
            
            $sum = 0;
            for ($i = 0; $i < 17; $i++) {
                $sum += $a[$i] * $w[$i];
            }
            $r = $sum % 11;
            
            if ($c[$r] == $a[17]) return true;
        } elseif (preg_match($preg15, $id) > 0) {
            return true;
        }
        return false;
    }
}


if (!function_exists('arrayToObject')) {
    /**
     * 数组转对象
     * @param $array
     * @return StdClass
     */
    function arrayToObject($array)
    {
        if (is_array($array)) {
            $obj = new StdClass();
            foreach ($array as $k => $v) {
                $obj->$k = $v;
            }
        } else {
            $obj = $array;
        }
        
        return $obj;
    }
}

if (!function_exists('objectToArray')) {

    /**
     * 对象转数组
     * @param $obj
     * @return array
     */
    function objectToArray($object)
    {
        $object =  json_decode( json_encode( $object),true);
        return  $object;
    }
}


if (!function_exists('changeNumToFloat')) {
    /**
     * 格式化数字
     * @param $num
     * @return string
     */
    function changeNumToFloat($num)
    {
        return sprintf("%.2f", $num);
    }
}


if (!function_exists('apiReturn')) {
    
    /**
     * api返回数据
     * @param $code
     * @param $message
     * @param null $data
     * @return \Illuminate\Http\JsonResponse
     */
    function apiReturn($code, $message, $data = null)
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => $data]);
    }
}

if (!function_exists('apiArrayReturn')){

    /**
     * api返回数据,解决数组下标问题
     * @param $code
     * @param $message
     * @param null $data
     * @return \Illuminate\Http\JsonResponse
     */
    function apiArrayReturn($code, $message, $data = null)
    {
        return json_encode(['code' => $code, 'message' => $message, 'data' => $data],JSON_FORCE_OBJECT);
    }
}

if (!function_exists('getImgUrl')) {
    /**
     * 获取图片url
     * @param $imgName
     * @param $img_dir
     * @param string $size
     * @return mixed
     */
    function getImgUrl($imgName, $img_dir, $size = '_60')
    {
        if(empty($imgName))  return '';
        if(is_array($imgName)){
            $full_name = array();
            foreach($imgName as $k=>$v){
                $arr = explode('.', $v);
                $name = $arr[0];
                $ext = '.' . $arr[1];
                $dir = substr($name, 0, 10);
                $file_host = 'http://'.$_SERVER['HTTP_HOST'].'/files/';
                $file_path = $img_dir.'/';
                $full_name[] =  $file_host . $file_path . $dir . '/' . $name . $size . $ext;
            }
        } else{
            $arr = explode('.', $imgName);
            $name = $arr[0];
            $ext = '.' . $arr[1];
            $dir = substr($name, 0, 10);
            $file_host = 'http://'.$_SERVER['HTTP_HOST'].'/files/';
            $file_path = $img_dir.'/';
            $full_name =  $file_host . $file_path . $dir . '/' . $name . $size . $ext;
        }
        return $full_name;
    }
}


if (!function_exists('getFileUrl')) {
    /**
     * 获取文件url
     * @param $fileName
     * @param $file_dir
     * @return string
     */
    function getFileUrl($fileName, $file_dir)
    {

        if(is_array($fileName)){
            $full_name = array();
            foreach ($fileName as $k=>$v){
                $arr = explode('.', $v);
                $name = $arr[0];
                $ext = '.' . $arr[1];
                $dir = substr($name, 0, 10);
                $file_host = 'http://'.$_SERVER['HTTP_HOST'].'/files/';
                $file_path = $file_dir.'/';
                $full_name[] = $file_host . $file_path . $dir . '/' . $name . $ext;
            }
        }else{
            $arr = explode('.', $fileName);
            $name = $arr[0];
            $ext = '.' . $arr[1];
            $dir = substr($name, 0, 10);
            $file_host = 'http://'.$_SERVER['HTTP_HOST'].'/files/';
            $file_path = $file_dir.'/';
            $full_name = $file_host . $file_path . $dir . '/' . $name . $ext;
        }

        return $full_name;
    }
}


if (!function_exists('generateNumber')) {
    /**
     * 生成唯一编号
     * @param $prefix
     * @return string
     */
    function generateNumber($prefix)
    {
        return $prefix.date('YmdHis', time()) . rand(1000,9999);
    }
}


//CURL
if (!function_exists('curl')) {
    function curl($url, $params = false, $ispost = false, $https = false)
    {
        $httpInfo = array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在
        }
        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                if (is_array($params)) {
                    $params = http_build_query($params);
                }
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        
        $response = curl_exec($ch);
        
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }
}

if (!function_exists('myEncrypt')) {
    function myEncrypt($data)
    {
        $arr = 'RxM4EBHt5/I74OjReYeVBA+qD7NUDYMwlstoU6O/VhI=';
        return openssl_encrypt($data, 'AES-128-CBC', $arr, 0, mb_substr($arr, 0, 16));
    }
}

if (!function_exists('myDecrypt')) {
    function myDecrypt($data)
    {
        $arr = 'RxM4EBHt5/I74OjReYeVBA+qD7NUDYMwlstoU6O/VhI=';
        return openssl_decrypt($data, 'AES-128-CBC', $arr, 0, mb_substr($arr, 0, 16));
    }
}


if (!function_exists('delFile')) {

    /**
     * 删除文件
     * @param $oldFileName
     * @param $type
     */
    function delFile($oldFileName, $type)
    {

        if(!is_array($oldFileName)){
            $oldFileName = explode(',',$oldFileName);
        }

        $image_path = array();
        foreach ($oldFileName as $k=>$v) {
            $dir = substr($v, 0, 10);
            $image_path[$k] = $v = $dir.'/'.$v;
        }

        foreach ($image_path as $k=>$v) {
            if (Storage::disk($type)->exists($v)) {
                Storage::disk($type)->delete($v);
            }
        }
    }
}

