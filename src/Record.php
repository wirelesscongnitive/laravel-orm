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
 * @method static array select($needReturn = false,\Closure $function = '')
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
 * @method static int count($columns = '*')
 * @method static float min($column)
 * @method static float max($column)
 * @method static int sum($column)
 * @method static float average($column)
 */
class Record{
    /** @var $table string 表名称 */
    public $table = '';

    /** @var $fields array 字段名称数组 */
    public $fields = [];

    /** @var $id int 数据记录的编号 */
    public $id;

    /** @var $nowRecord Record 当前的record表对象 */
    public $nowRecord;

    /** @var $table_name string 当前的数据表名称 */
    public $table_name;

    /** @var $cacheObj Cache */
    public $cacheObj;

    /** @var bool $enableCache 默认都是开启id一级缓存的 继承子类当中可以进行复写 */
    public static $enableCache = true;

    /**
     * @var bool $user_hiddle_fields 是否使用隐藏字段
     * 隐藏字段包含
     * is_open 记录是否开启暂时用于软删除
     * open_close_time 上次开启或者关闭的时间
     */
    public static $use_hidden_fields = false;

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
        if($name == 'select' || $name == 'find' || $name == 'count'){
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
        $model = get_called_class();
        $toUpdateArray = [];
        foreach ($this->fields as $oneFields=>$type){
            if(isset($this->$oneFields) && (!empty($this->$oneFields) || (int)$this->$oneFields === 0)){
                if(is_array($this->$oneFields) || is_object($this->$oneFields)){
                    $toInsertArray[$oneFields] = json_encode($this->$oneFields, true);
                }else{
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
            DB::table($this->table)->where('id',$this->id)->update($this->filterFieldsForUpdate($toUpdateArray));
            if($model::$enableCache){
                //建立id缓存
                $this->cacheObj->addIdCache($this->id,$this->table,$toUpdateArray);
            }
        }
    }

    /**
     * 过滤掉一些编辑不需要编辑的保留字段
     * @param $fields_array
     * @return mixed
     */
    private function filterFieldsForUpdate($fields_array){
        $privateFields = ['create_time','is_open','open_close_time'];
        foreach ($fields_array as $field=>$value){
            if(in_array($field,$privateFields)){
                unset($fields_array[$field]);
            }
        }
        return $fields_array;
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
                        $timeStr = $value;
                        $value = strtotime($timeStr) * 1000 + $mini;
                    }else{
                        $value = strtotime($value) * 1000;
                    }
                }
            }else if($format == 'time'){
                if(is_string($value)){
                    $value = strtotime($value);
                }
            }else if($format == 'pic'){
                $obs_url = config('outer.obs.file_url').'/';
                if(strpos($value,$obs_url) > -1){
                    $value = str_replace($obs_url,'',$value);
                }
            }
        }
        return $value;
    }

    /**
     * 数据信息插入方法
     * @return int
     */
    public function insert(){
        $model = get_called_class();
        $toInsertArray = [];
        foreach ($this->fields as $oneFields=>$type){
            if(isset($this->$oneFields) && !empty($this->$oneFields)){
                if(is_array($this->$oneFields) || is_object($this->$oneFields)){
                    $toInsertArray[$oneFields] = json_encode($this->$oneFields, true);
                }else{
                    $toInsertArray[$oneFields] = $this->$oneFields;
                }
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
        if($model::$enableCache){
            //建立id缓存
            $this->cacheObj->addIdCache($id,$this->table,$toInsertArray);
        }
        $this->id = $id;
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
        //对id缓存进行清空 有就清除没有就算了
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
     * @return $this|array
     */
    public static function get($id){
        if(!$id)return null;
        $model = get_called_class();
        /* @var $model Record */
        $model = new $model;
        $table = $model->table;
        $cacheObj = new Cache();
        if($model::$enableCache){
            $data = $cacheObj->get($id,$table);
        }else{
            $data = false;
        }
        if(!is_array($data)){
            if(self::$use_hidden_fields){
                if(isset($model->fields['is_open'])){
                    $data = DB::table($table)->where('id',$id)->where('is_open',1)->first();
                }else{
                    $data = DB::table($table)->where('id',$id)->first();
                }
            }else{
                $data = DB::table($table)->where('id',$id)->first();
            }
            if(is_array($data) || is_object($data)) {
                self::filterHiddenFields($data);
                if ($model::$enableCache) {
                    $cacheObj->addIdCache($id, $table, $data);
                }
            }
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
     * 获取当前对象参数的数组形式
     * @return array
     */
    public function toArray(){
        $protectFields = ['table','fields','table_name','nowRecord','cacheObj'];
        $tempData = [];
        foreach ($this as $field=>$value){
            if(!in_array($field,$protectFields)){
                $tempData[$field] = $value;
            }
        }
        return $tempData;
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
                    if($value > 0){
                        $second_time = floor($value * 0.001);
                        $date = date("Y-m-d H:i:s",$second_time);
                    }else{
                        $date = '-';
                    }
                    return $date;
                case 'time':
                    if($value > 0){
                        return date('Y-m-d H:i:s',$value);
                    }else{
                        return '-';
                    }
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
                case 'pic':
                    return self::picFormat($value);
                default:
                    return $value;
            }
        }else{
            return $value;
        }
    }

    /**
     * 拼接图片类型的数据
     * @param $value
     * @return string
     */
    private static function picFormat($value){
        $config_url = config('outer.obs.file_url');
        return $config_url.'/'.$value;
    }
}