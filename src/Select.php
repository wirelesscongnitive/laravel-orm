<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/1/31
 * Time: 17:08
 */

namespace WirelessCognitive\LaravelOrm;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Select
{
    /** @var $selectObj Builder 当前的查询对象 */
    public static $selectObj;

    /** @var $fields array 当前的查询对象所涉及的字段 */
    public static $fields;

    /** @var $needPage bool 是否需要分页参数 */
    public static $needPage = false;

    /** @var $recordTotal int 当前record的总数量 */
    public static $recordTotal;

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
                if(in_array($field,array_keys(self::$fields)) && !empty($value)){
                    self::$selectObj->where($field,$value);
                }
            }
        }
        return $record;
    }

    /**
     * 调用builder类的基础方法
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name,$arguments)
    {
        $record = array_shift($arguments);
        self::initSelectObj($record);
        if(in_array($name,['forPageAfterId'])){
            self::$needPage = true;
        }
        self::$selectObj->$name(...$arguments);
    }

    /**
     * 初始化查询的主对象
     * @param $record Record 当前的查询模型
     */
    private static function initSelectObj($record){
        if(!self::$selectObj instanceof Builder){
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
                if(in_array($field,array_keys(self::$fields)) && !empty($value)){
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
        if(in_array($field,array_keys(self::$fields))){
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
        self::$recordTotal = self::$selectObj->count();
        self::$selectObj->offset($page * $step - $step)->limit($step);
        self::$needPage = true;
    }

    /**
     * 执行查询方法
     * @param $record Record
     * @param string|\Closure $function
     * @return array
     */
    public static function select($record,$function = ''){
        self::initSelectObj($record);
        $list = $data = [];
        $ids = self::$selectObj->pluck('id')->toArray();
        if(is_array($ids) && count($ids) > 0){
            foreach ($ids as $id){
                if($function instanceof \Closure) {
                    $oneInfo = $record->get($id);
                    $function($oneInfo);
                    $list[] = $oneInfo;
                }else{
                    $list[] = $record->get($id);
                }
            }
        }
        if(self::$needPage){
            $total = self::$recordTotal;
            $data = compact('list','total');
        }else{
            $data = $list;
        }
        //重置静态变量
        self::$selectObj = null;
        self::$needPage = false;
        self::$recordTotal = 0;
        return $data;
    }
}