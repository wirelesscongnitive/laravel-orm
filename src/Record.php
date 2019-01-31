<?php
namespace WirelessCognitive\LaravelOrm;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\Types\Static_;
use phpDocumentor\Reflection\Types\Void_;

/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/1/21
 * Time: 9:24
 * @method static void equal(array $params)
 * @method static void like(array $params)
 * @method static $this order(string $field,string $type)
 * @method static void fromTo(array $params)
 * @method static void keyword(array $fields,string $keywords)
 * @method static void timeZone(array $time_array)
 * @method static array select()
 */
class Record{
    /** @var $table string 表名称 */
    public $table = '';
    /** @var $fields array 字段名称数组 */
    public $fields = [];
    /** @var $nowRecord Record 当前的record表对象 */
    public $nowRecord;
    /** @var $table_name string 当前的数据表名称 */
    public $table_name;
    /** @var bool $user_hiddle_fields 是否使用隐藏字段 */
    /**
     * 隐藏字段包含
     * is_open 记录是否开启暂时用于软删除
     * open_close_time 上次开启或者关闭的时间
     */
    public static $use_hidden_fields = false;
    /** @var $cacheObj Cache */
    public $cacheObj;
    public function __construct()
    {
        $this->cacheObj = new Cache();
    }

    /**
     * 调用静态方法向静态类进行转发
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        $model = get_called_class();
        /* @var $model Record */
        $model = new $model;
        $table_name = self::getTable();
        array_unshift($arguments,$model);
        $selectObj = Select::$name(...$arguments);
        return $selectObj;
    }

    /**
     * 数值的修改方法
     */
    public function update(){
        $toUpdateArray = [];
        foreach ($this->fields as $oneFields=>$type){
            if(isset($this->$oneFields) && !empty($this->$oneFields)){
                $toUpdateArray[$oneFields] = $this->$oneFields;
            }
        }
        if(isset($this->id)){
            //隐藏字段拼接
            if(self::$use_hidden_fields){
                $toUpdateArray['update_time'] = $this->getMicrotime();
            }
            //更新数据库
            DB::table($this->table)->where('id',$this->id)->update($toUpdateArray);
            //建立id缓存
            $this->cacheObj->addIdCache($this->id,$this->table,$toUpdateArray);
        }
    }
    /**
     * 数据信息插入方法
     */
    public function insert(){
        $toInsertArray = [];
        foreach ($this->fields as $oneFields=>$type){
            if(isset($this->$oneFields)){
                $toInsertArray[$oneFields] = $this->$oneFields;
            }
        }
        //隐藏字段拼接
        if(self::$use_hidden_fields){
            $toInsertArray['create_time'] = $this->getMicrotime();
            $toInsertArray['is_open'] = 1;
        }
        //存入数据库
        $id = DB::table($this->table)->insertGetId($toInsertArray);
        //建立id缓存
        $this->cacheObj->addIdCache($id,$this->table,$toInsertArray);
        //返回索引
        return $id;
    }

    /**
     * 获取精确到毫秒的时间戳
     * @return float
     */
    private function getMicrotime(){
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return round($msectime);
    }

    /**
     * 删除方法
     * @param $id
     * @param bool $force
     */
    public static function delete($id,$force=false){
        $table = self::getTable();
        //开启软删除且不强制删除的情况下进行软删除
        if(self::$use_hidden_fields && !$force){
            DB::table($table)->where('id',$id)->update([
                'is_open'=>-1,
                'open_close_time'=>time()
            ]);
        }else{
            //进行数据库的硬性删除
            DB::table($table)->where('id',$id)->delete();
        }
        //对id缓存进行清空
       $cacheObj = new Cache();
        $cacheObj->deleteIdCache($id,$table);
    }

    /**
     * 获取表的名称
     * @return string
     */
    public static function getTable(){
        $model = get_called_class();
        /* @var $model Record */
        $model = new $model;
        $table = $model->table;
        return $table;
    }

    /**
     * 过滤数据中的隐藏字段
     * @param $data
     */
    private static function filterHiddenFields(&$data){
        $hiddenArray = ['is_open','open_close_time'];
        foreach ($data as $key=>&$value){
            if(in_array($key,$hiddenArray)){
                unset($value);
            }
        }
    }

    /**
     * 根据主键获取对象
     * @param $id
     * @return $this
     */
    public static function get($id){
        $model = get_called_class();
        /* @var $model Record */
        $model = new $model;
        $table = $model->table;
        $cacheObj = new Cache();
        $data = $cacheObj->get($id,$table);
        if(!is_array($data)){
            $data = DB::table($table)->where('id',$id)->first();
            self::filterHiddenFields($data);
            $cacheObj->addIdCache($id,$table,$data);
        }
        if(is_array($data) || is_object($data)){
            foreach ($data as $field=>$value){
                if(property_exists($model,$field)){
                    $model->$field = self::handleFormat($model,$field,$value);
                }
            }
            return $model;
        }else{
            return null;
        }
    }

    /**
     * 格式化数据类型
     * @param $model Record
     * @param $field
     * @param $value
     * @return 返回变更数值后的值
     */
    private static function handleFormat($model,$field,$value){
        if(isset($model->fields[$field])){
            $type = $model->fields[$field];
            switch ($type){
                case 'mini_time':
                    $second_time = floor($value * 0.001);
                    $mini_time = ($value - $second_time * 1000) * 0.001;
                    return date("Y-m-d H:i:s",$second_time).substr((string)$mini_time,1);
                case 'time':
                    return date('Y-m-d H:i:s',$value);
                case 'mini_date':
                    $second_time = floor($value * 0.001);
                    return date('Y-m-d',$second_time);
                case 'date':
                    return date('Y-m-d',$value);
                case 'int':
                    return (int)$value;
                case 'string':
                    return (string)$value;
                case 'float':
                    return (float)$value;
                default:
                    return $value;
            }
        }else{
            return $value;
        }
    }
}