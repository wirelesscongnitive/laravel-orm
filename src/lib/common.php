<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/14
 * Time: 13:17
 */
if(!function_exists('realArray')){
    /**
     * 判断是否为有内容的数组
     * @param $data
     * @return bool
     */
    function realArray($data){
        if(is_array($data) && count($data) > 0){
            return true;
        }else{
            return false;
        }
    }
}
if(!function_exists('succ')){
    /**
     * 系统成功返回信息
     * @param bool $data
     * @return array
     */
    function succ($data = false){
        if($data instanceof \WirelessCognitive\LaravelOrm\Record){
            if(isset($data->table))unset($data->table);
            if(isset($data->fields))unset($data->fields);
            if(isset($data->table_name))unset($data->table_name);
            if(isset($data->nowRecord))unset($data->nowRecord);
            if(isset($data->cacheObj))unset($data->cacheObj);
        }
        $info = [
            'success'=>true,
            'code'=>200,
            'msg'=>'成功',
            'time'=>time()
        ];
        if($data || is_array($data)){
            $info['data'] = $data;
        }
        return $info;
    }
}
if(!function_exists('convertUnderline')){
    /**
     * 蛇形命名转大驼峰命名
     * @param $str
     * @param bool $ucFirst
     * @return string
     */
    function convertUnderline($str , $ucFirst = true){
        while(($pos = strpos($str , '_'))!==false)
            $str = substr($str , 0 , $pos).ucfirst(substr($str , $pos+1));
        return $ucFirst ? ucfirst($str) : $str;
    }
}