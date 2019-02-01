<?php
namespace WirelessCognitive\LaravelOrm;


/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/1/21
 * Time: 9:24
 */
class Cache{
    /** @var $redisConnection \Redis 自定义缓存连接 */
    public static $redisConnection;
    /** @var array $redisConfig laravel的redis相关的配置 */
    public static $redisConfig = [];

    /**
     * 添加ID缓存
     * @param $id
     * @param $table
     * @param $data
     */
    public function addIdCache($id,$table,$data){
        $data = (array)$data;
        $key = $this->initConnection($table,$id);
        $data['id'] = $id;
        self::$redisConnection->set($key,json_encode($data,true));
    }

    /**
     * 删除ID缓存
     * @param $id
     * @param $table
     */
    public function deleteIdCache($id,$table){
        $key = $this->initConnection($table,$id);
        self::$redisConnection->del($key);
    }

    /**
     * 按照缓存和ID去查找一条信息
     * @param $id
     * @param $table
     * @return array 数据的内容数组
     */
    public function get($id,$table){
        $key = $this->initConnection($table,$id);
        $data = self::$redisConnection->get($key);
        return json_decode($data,true);
    }

    /**
     * 根据表明选择redis的数据库
     * @param $table
     */
    private function selectDb($table){
        $haveSelect = false;
        if(is_array(self::$redisConfig['database'])){
            foreach (self::$redisConfig['database'] as $database=>$prefix){
                if(strpos($table,$prefix) > -1 && strpos($table,$prefix) == 0){
                    self::$redisConnection->select($database);
                    $haveSelect = true;
                    break;
                }
            }
        }
        if(!$haveSelect){
            $database = array_search('custom_default',self::$redisConfig['database']);
            if($database){
                self::$redisConnection->select($database);
            }
        }
    }

    /**
     * 初始化缓存连接
     * @param $table string 表名称
     * @param $id int 数据内容的主键
     * @return string 缓存的键
     */
    private function initConnection($table,$id){
        if(!self::$redisConnection){
            $redisConfig =  config('database.redis.default');
            if(is_array($redisConfig) && !empty($redisConfig)){
                self::$redisConnection = new \Redis();
                self::$redisConnection->connect(
                    isset($redisConfig['host'])?$redisConfig['host']:'127.0.0.1',
                    isset($redisConfig['port'])?$redisConfig['port']:6379);
                if(isset($redisConfig['password'])&&!empty($redisConfig['password'])){
                    self::$redisConnection->auth($redisConfig['password']);
                }
                $mapConfig = config('database.id_cache_map');
                if(is_array($mapConfig)){
                    $redisConfig['database'] = $mapConfig;
                }
                self::$redisConfig = $redisConfig;
            }
        }
        $this->selectDb($table);
        return $table.":".$id;
    }
}