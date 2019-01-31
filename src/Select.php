<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/1/31
 * Time: 17:08
 */

namespace WirelessCognitive\LaravelOrm;

use Illuminate\Support\Facades\DB;

class Select
{
    public static $selectObj;

    /**
     * 等于条件的信息
     * @param $record Record
     * @param $params
     * @return Record
     */
    public static function equal($record,$params){
        self::$selectObj = DB::table($record->table);
        if(is_array($params) && count($params) > 0){
            foreach ($params as $field=>$value){
                if(in_array($field,$record->fields) && !empty($value)){
                    self::$selectObj->where($field,$value);
                }
            }
        }
        return $record;
    }

    /**
     * 模糊查询条件
     * @param $record Record
     * @param $params
     * @return Record
     */
    public static function like($record,$params){
        self::$selectObj = DB::table($record->table);
        if(is_array($params) && count($params) > 0){
            foreach ($params as $field=>$value){
                if(in_array($field,$record->fields) && !empty($value)){
                    self::$selectObj->where($field,'like','%'.$value.'%');
                }
            }
        }
        return $record;
    }
}