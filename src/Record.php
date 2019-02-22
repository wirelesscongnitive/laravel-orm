<?php
namespace WirelessCognitive\LaravelOrm;
use Illuminate\Support\Facades\DB;

/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/1/21
 * Time: 9:24
 * @method static $this equal(array $params)
 * @method static $this like(array $params)
 * @method static $this order(string $field,string $type)
 * @method static $this fromTo(array $params)
 * @method static $this keyword(array $fields,string $keywords)
 * @method static $this timeZone(array $time_array)
 * @method static $this page(int $page,int $step)
 * @method static array select(\Closure $function = '',$needReturn = false)
 * @method static array find(\Closure $function = '',$needReturn = false)
 * @method static $this where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this distinct()
 * @method static $this orWhere($column, $operator = null, $value = null)
 * @method static $this whereIn($column, $values, $boolean = 'and', $not = false)
 * @method static $this orWhereIn($column, $values)
 * @method static $this whereNotIn($column, $values, $boolean = 'and')
 * @method static $this orWhereNotIn($column, $values)
 * @method static $this whereNull($column, $boolean = 'and', $not = false)
 * @method static $this orWhereNull($column)
 * @method static $this whereNotNull($column, $boolean = 'and')
 * @method static $this whereBetween($column, array $values, $boolean = 'and', $not = false)
 * @method static $this orWhereBetween($column, array $values)
 * @method static $this whereNotBetween($column, array $values, $boolean = 'and')
 * @method static $this orWhereNotBetween($column, array $values)
 * @method static $this orWhereNotNull($column)
 * @method static $this whereSub($column, $operator,\Closure $callback, $boolean)
 * @method static $this groupBy(...$groups)
 * @method static $this offset($value)
 * @method static $this limit($value)
 * @method Static $this forPageAfterId($perPage = 15, $lastId = 0, $column = 'id')
 * @method static $this toSql()
 * @method static $this count($columns = '*')
 * @method static $this min($column)
 * @method static $this max($column)
 * @method static $this sum($column)
 * @method static $this average($column)
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

    /** @var $cacheObj Cache */
    public $cacheObj;
    /**
     * @var bool $user_hiddle_fields 是否使用隐藏字段
     * 隐藏字段包含
     * is_open 记录是否开启暂时用于软删除
     * open_close_time 上次开启或者关闭的时间
     */
    public static $use_hidden_fields = true;

    public function __construct()
    {
        $this->cacheObj = new Cache();
    }

    /**
     * 调用静态方法向静态类进行转发
     * @param $name
     * @param $arguments
     * @return Record
     */
    public static function __callStatic($name, $arguments)
    {
        $model = get_called_class();
        /* @var $model Record */
        $model = new $model;
        array_unshift($arguments,$model);
        if($name == 'select'){
            return Select::$name(...$arguments);
        }else{
            Select::$name(...$arguments);
            return $model;
        }
    }

    /**
     * 非静态方法的转发类
     * @param $name
     * @param $arguments
     * @return Record
     */
    public function __call($name,$arguments)
    {
        array_unshift($arguments,$this);
        if($name == 'select' || $name == 'find'){
            return Select::$name(...$arguments);
        }else{
            Select::$name(...$arguments);
            return $this;
        }
    }

    /**
     * 数值的修改方法
     */
    public function update(){
        $toUpdateArray = [];
        $toCacheArray = [];
        foreach ($this->fields as $oneFields=>$type){
            if(isset($this->$oneFields) && !empty($this->$oneFields)){
                $toCacheArray[$oneFields] = self::handleFormat($this,$this->$oneFields,$oneFields);
                if($oneFields != 'create_time'){
                    $toUpdateArray[$oneFields] = $this->filterFormat($this->$oneFields,$oneFields);
                }
            }
        }
        if(isset($this->id)){
            //隐藏字段拼接
            if(self::$use_hidden_fields){
                $toUpdateArray['update_time'] = $this->getMicroTime();
            }
            //更新数据库
            DB::table($this->table)->where('id',$this->id)->update($toUpdateArray);
            //建立id缓存
            $this->cacheObj->addIdCache($this->id,$this->table,$toCacheArray);
        }
    }

    /**
     * 对数据的格式进行过滤
     * @param $value
     * @param $field
     * @return mixed
     */
    private function filterFormat($value,$field){
        if(isset($this->fields[$field])){
            $format = $this->fields[$field];
            if($format == 'mini_time'){
                //时间格式过滤(还原毫秒时间戳)
                if(is_string($value)){
                    $array = explode('.',$value);
                    if(count($array) > 1){
                        $mini = end($array);
                        $timeStr = end($array);
                        $value = strtotime($timeStr) * 1000 + $mini;
                    }else{
                        $value = strtotime($value) * 1000;
                    }
                }
            }else if($format == 'time'){
                if(is_string($value)){
                    $value = strtotime($value);
                }
            }
        }
        return $value;
    }

    /**
     * 数据信息插入方法
     */
    public function insert(){
        $toInsertArray = [];
        foreach ($this->fields as $oneFields=>$type){
            if(isset($this->$oneFields) && !empty($this->$oneFields)){
                $toInsertArray[$oneFields] = $this->$oneFields;
            }else{
                if(!in_array($oneFields,['create_time','is_open','update_time'])){
                    $toInsertArray[$oneFields] = $this->getDefaultValue($oneFields);
                }
            }
        }
        //隐藏字段拼接
        if(self::$use_hidden_fields){
            $toInsertArray['create_time'] = $this->getMicroTime();
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
     * 获取字段的默认值
     * @param $field
     * @return int|string
     */
    private function getDefaultValue($field){
        if(isset($this->fields[$field])){
            $type = $this->fields[$field];
            switch ($type){
                case 'mini_time':
                case 'time':
                case 'mini_date':
                case 'date':
                case 'int':
                case 'float':
                    return 0;
                case 'string':
                default:
                    return '';
            }
        }else{
            return '';
        }
    }

    /**
     * 获取精确到毫秒的时间戳
     * @return float
     */
    private function getMicroTime(){
        list($microTime, $second) = explode(' ', microtime());
        $fullTime = (float)sprintf('%.0f', (floatval($microTime) + floatval($second)) * 1000);
        return round($fullTime);
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
            if(self::$use_hidden_fields){
                $data = DB::table($table)->where('id',$id)->where('is_open',1)->first();
            }else{
                $data = DB::table($table)->where('id',$id)->first();
            }
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
     * @return mixed
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