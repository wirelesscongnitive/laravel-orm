<?php
namespace WirelessCognitive\LaravelOrm;

use PDO;

/**
 * 自动生成record
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/1/21
 * Time: 9:24
 */
class Seed{
    /** @var $connection PDO */
    private $connection;

    /** @var array $hiddenFields 隐藏字段 */
    private $hiddenFields = ['open_id','open_close_time'];

    /** @var $objectsDir string 目标文件夹 */
    private $objectsDir = "./app/Record/";

    /**
     * 创建record的入口方法
     */
    public function make(){
        set_time_limit(0);
//        $mysqlConfig = config('database.connections.mysql');
        $mysqlConfig = [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'fall'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'root'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];
        $this->connection = new PDO('mysql:host='.$mysqlConfig['host'].';dbname=information_schema',
            $mysqlConfig['username'], $mysqlConfig['password'],[PDO::ATTR_PERSISTENT => true]);
        $sql = "select * from `tables` where `TABLE_SCHEMA`='{$mysqlConfig['database']}'";
        foreach ($this->connection->query($sql) as $row){
            set_time_limit(0);
            $this->makeOneRecord($row['TABLE_SCHEMA'],$row['TABLE_NAME'],$row['TABLE_COMMENT']);
        }
    }

    /**
     * todo 暂时粒度仅限于表的增补后面的粒度需要做到字段的管理
     * 创建一个表的信息
     * @param $database
     * @param $table
     * @param $comment
     */
    private function makeOneRecord($database,$table,$comment){
        $sql = "select * from `columns` where `table_schema` ='{$database}'  and `table_name` = '{$table}';";
        $fieldsList = [];
        $this->connection->query($sql);
        foreach ($this->connection->query($sql) as $row){
            if(!in_array($row['COLUMN_NAME'],$this->hiddenFields)){
                $fieldsList[] = $row;
            }
        }
        $date = date('Y-m-d',time());
        $time = date('H:i:s',time());
        $namespace = "App\Http\Record";
        $name = $this->convertUnderline($table)."Record";
        $text =<<<EOF
<?php
/**
 * $comment
 * User: wirelessCognitive
 * Date: $date
 * Time: $time
 */
namespace $namespace;
use WirelessCognitive\LaravelOrm\Record;  

class $name Extends Record{
EOF;
        $text.= "\n\t".'/** @var string $table 数据表名 */'."\n";
        $text.= "\t".'public $table ="'.$table.'";'."\n\n";
        $text.= "\t".'/** @var array $fields 字段列表 */'."\n";
        $text.= "\t".'public $fields = ['."\n";
        foreach ($fieldsList as $oneField){
            if($oneField['COLUMN_NAME'] != 'id'){
                $fieldsType = $this->getFieldsType($oneField['COLUMN_NAME'],$oneField['DATA_TYPE']);
                $text.= "\t\t".'"'.$oneField['COLUMN_NAME'].'"=>"'.$fieldsType.'",'.'//'.$oneField['COLUMN_COMMENT']."\n";
            }
        }
        $text.= "\t".'];'."\n\n";
        foreach ($fieldsList as $oneField){
            $fieldsType = $this->getFieldsType($oneField['COLUMN_NAME'],$oneField['DATA_TYPE']);
            if(strpos($fieldsType,'time') > -1){
                $fieldsType = 'int';
            }
            $text .= "\t".'/** @var $'.$oneField['COLUMN_NAME'].' '.$fieldsType.' '.$oneField['COLUMN_COMMENT'].' */'."\n";
            $text .= "\t".'public $'.$oneField['COLUMN_NAME'].";\n\n";
        }
        $text .= "\t/** custom **/\n\n";
        $text .= "}";
        $fileName = $this->objectsDir.$name.".php";
        if(!file_exists($fileName)){
            file_put_contents($fileName,$text);
        }
    }

    /**
     * 获取字段类型
     * @param $fields_name string 字段名称
     * @param $data_type string 字段类型
     * @return string string 在当前系统中的可识别类型
     */
    public function getFieldsType($fields_name,$data_type){
        $stringType = ['varchar','char','text','mediumtext','longtext'];
        $floatType = ['float','decimal','double'];
        $intType = ['int','tinyint','bigint','smallint','mediumint'];
        if(in_array($data_type,$stringType)){
            return 'string';
        }else if(in_array($data_type,$floatType)){
            return 'float';
        }else if(strpos($fields_name,'time')){
            if($data_type == 'bigint'){
                return 'mini_time';
            }
            if($data_type == 'int'){
                return 'time';
            }
        }else if(in_array($data_type,$intType)){
            return 'int';
        }else if ($fields_name == 'pic'){
            return 'pic';
        }
        return $data_type;
    }

    /**
     * 蛇形命名转大驼峰命名
     * @param $str
     * @param bool $ucFirst
     * @return string
     */
    private function convertUnderline ( $str , $ucFirst = true){
        while(($pos = strpos($str , '_'))!==false)
            $str = substr($str , 0 , $pos).ucfirst(substr($str , $pos+1));
        return $ucFirst ? ucfirst($str) : $str;
    }
}