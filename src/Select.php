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
    /** @var $selectObj object 当前的查询对象 */
    public static $selectObj;
    /** @var $fields array 当前的查询对象所涉及的字段 */
    public static $fields;
    /**
     * 等于条件的信息
     * @param $record Record
     * @param $params
     * @return Record
     */
    public static function equal($record,$params){
        self::initSelectObj($record);
        if(is_array($params) && count($params) > 0){
            foreach ($params as $field=>$value){
                if(in_array($field,self::$fields) && !empty($value)){
                    self::$selectObj->where($field,$value);
                }
            }
        }
        return $record;
    }
    /**
     * 初始化查询的主对象
     * @param $record Record 当前的查询模型
     */
    private static function initSelectObj($record){
        if(!is_object(self::$selectObj)){
            self::$selectObj = DB::table($record->table);
            self::$fields = $record->fields;
        }
    }

    /**
     * 模糊查询条件
     * @param $record Record
     * @param $params
     * @return Record
     */

    public static function like($record,$params){
        self::initSelectObj($record);
        if(is_array($params) && count($params) > 0){
            foreach ($params as $field=>$value){
                if(in_array($field,self::$fields) && !empty($value)){
                    self::$selectObj->where($field,'like','%'.$value.'%');
                }
            }
        }
        return $record;
    }

    /**
     * 排序方式
     * @param $record
     * @param $field
     * @param $type
     */
    public static function order($record,$field,$type){
        self::initSelectObj($record);
        $type = in_array($type,['asc','desc'])?$type:'asc';
        if(in_array($field,self::$fields)){
            self::$selectObj->orderBy($field,$type);
        }
    }

    /**
     * 分页查询参数
     * @param $record
     * @param $page
     * @param $step
     */
    public static function page($record,$page,$step){
        self::initSelectObj($record);
        self::$selectObj->limit(($page * $step - $step),$step);
    }

    /**
     * 执行查询方法
     * @param $record Record
     * @param string $function
     * @return array
     */
    public static function select($record,$function = ''){
        if($function instanceof \Closure){
            return [];
        }else{
            $ids = self::$selectObj->pluck('id');
            $data = [];
            if(is_array($ids) && count($ids) > 0){
                foreach ($ids as $id){
                    $data[] = $record->get($id);
                }
            }
            return $data;
        }
    }
}