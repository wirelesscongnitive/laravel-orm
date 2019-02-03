<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/3
 * Time: 13:29
 */

namespace WirelessCognitive\LaravelOrm;


class Route
{
    /** @var $routeText string 路由文件 */
    public $routeText;

    /** @var string $controllerBaseUrl 基础控制器所在的文件夹 */
    public $controllerBaseUrl = './app/';

    /** @var $controllerText string 控制器的文本信息 */
    public $controllerText;

    /** @var string $projectControllerUrl 当前项目的路由地址 */
    public $projectControllerUrl = '';

    /** @var string $thisText 文本标识 */
    public $thisText = '$this->';

    /** @var string $serviceBaseDir 服务层的默认路径 */
    public $serviceBaseDir = './service/';

    /**
     * 生成路由文件
     */
    public function makeRoute(){
        set_time_limit(0);
        $data = file_get_contents('./data.json');
        $data = json_decode($data,true);
        if(isset($data['data']) && isset($data['data']['modules'])){
            $modules = $data['data']['modules'];
            if(is_array($modules) && count($modules) > 0){
                $project = $this->getProject($modules[0]);
                if(!is_dir($this->controllerBaseUrl.$project)){
                    mkdir($this->controllerBaseUrl.$project,0777);
                    $this->projectControllerUrl = $this->controllerBaseUrl.$project."/";
                }
                $this->pushToRoute(1,"Route::prefix('".strtolower($project)."')->group(function(){");
                foreach ($modules as $module){
                    $this->makeModule($module);
                }
                $this->pushToRoute(1,"});");
                file_put_contents("./route",$this->routeText);
            }
        }
    }

    /**
     * 添加到路由文本
     * @param $level
     * @param $content
     */
    private function pushToRoute($level,$content){
        $tab = '';
        for($i=0;$i<= $level;$i++){
            $tab .= "\t";
        }
        $this->routeText .= $tab.$content."\n";
    }

    /**
     * 获取当前项目的项目名称
     * @param $module
     * @return string
     */
    private function getProject($module){
        $projectName = '';
        if(isset($module['interfaces']) && is_array($module['interfaces']) && count($module['interfaces'])){
            $data = $this->getProjectGroup($module['interfaces'][0]);
            $projectName = $data[0];
        }
        return $projectName;
    }

    /**
     * 生成单一接口的脚手架
     * @param $interface
     * @param $group
     * @param $project
     */
    public function makeInterface($interface,$project,$group){
        $urlArray = explode('/',$interface['url']);
        $action = end($urlArray);
        $action = $this->convertUnderline($action);
        $method = strtolower($interface["method"]);
        $this->pushToRoute(3,"Route::{$method}('{$action}','{$project}\\".$group."Controller@{$action}');");
        if(is_array($interface['properties']) && count($interface['properties'])){
            $this->addFunctionNote($interface['properties'],$interface['name']);
            $requestText = '$request';
            $lowerAction = strtolower($action);
            $this->controllerText .= <<<EOF
public function {$lowerAction}(Request {$requestText}){
EOF;
            $this->controllerText .= "\n";
            $arrayName = '$argument';
            $this->addGetParam($interface['properties'],$method);
            $dataText = '$data';
            $this->controllerText .= <<<EOF
        {$dataText}={$this->thisText}{$action}Service->handle(...{$arrayName});
        return succ({$dataText});      
    }
EOF;
            $this->controllerText .= "\n\n";
            $this->makeService($action,$group,$interface['properties'],$interface['name']);
        }
    }

    /**
     * 创建服务层
     * @param $action
     * @param $params
     * @param $group
     * @param $name
     */
    public function makeService($action,$group,$params,$name){
        if(!is_dir($this->serviceBaseDir.$group)){
            mkdir($this->serviceBaseDir.$group,0777);
        }
        $fileName = $this->serviceBaseDir.$group.'/'.$action.'Service.php';
        $content = "<?php\nnamespace APP\Http\Service\\".ucfirst($group).";\n\n";
        $content .= "/**\n/*{$name}\n*/\n";
        $content .= "class ".$action.'Service{'."\n";
        $paramText = '';
        $varText = '$';
        $content .="\t/**\n";
        foreach ($params as $param){
            $paramText .= '$'.$param['name'].',';
            $content .= <<<EOF
/* {$varText}{$param["name"]} {$param["type"]} {$param["description"]}
    
EOF;
        }
        $content .="\t*/\n";
        $paramText =rtrim($paramText, ',');
        $content.="\tpublic function handle(".$paramText."){\n\n\n";
        $content.= "\t\treturn [];";
        $content.= "\n\t}\n";
        $content .= "}";
        if(!file_exists($fileName)){
            file_put_contents($fileName,$content);
        }
    }

    /**
     * 获取参数
     * @param $properties
     * @param $method
     */
    private function addGetParam($properties,$method){
        $arrayName = '$argument';
        $requestText = '$request->';
        foreach ($properties as $param){
            $lowerName = strtolower($param['name']);
            $paramList[] = $lowerName;
            $this->controllerText .= "\t\t".$arrayName.'["'.$lowerName.'"]  = '.$requestText.'input("'.$method.'.'.$lowerName.'","");'."\n";
        }
    }

    /**
     * 添加方法的注解
     * @param $properties array
     * @param $name string
     */
    private function addFunctionNote($properties,$name){
        $requestText = '$request';
        $this->controllerText .=<<<EOF
    /**
    /* {$name}
    /*  @param Request {$requestText}
    
EOF;
        foreach ($properties as $param){
            $this->controllerText .= <<<EOF
/* {$param["name"]} {$param["type"]} {$param["description"]}
    
EOF;
        }
        $this->controllerText .=<<<EOF
*/
    
EOF;
    }

    /**
     * 生成一个模型的脚手架
     * @param $module
     */
    private function makeModule($module){
        if(isset($module['interfaces']) && is_array($module['interfaces']) && count($module['interfaces'])){
            list($project,$group) = $this->getProjectGroup($module['interfaces'][0]);
            $this->controllerText = '<?php'."\n".'namespace App\Http\Controllers\\'.$project.";\n\nuse Illuminate\Http\Request;\n";
            $actionList = $this->addControllerUser($module['interfaces'],$group);
            $this->controllerText .=<<<EOF
            
class {$group}Controller extends Controller{

EOF;
            $this->addControllerInit($actionList);
            $this->pushToRoute(2,"Route::prefix('".strtolower($group)."')->group(function(){");
            foreach ($module['interfaces'] as $interface){
                $this->makeInterface($interface,$project,$group);
            }
            $this->pushToRoute(2,"});");
            file_put_contents($this->projectControllerUrl.$group."Controller.php",$this->controllerText);
            $this->controllerText = '';
        }
    }

    /**
     * 添加控制器的初始化方法
     * @param $actionList
     */
    private function addControllerInit($actionList){
        foreach ($actionList as $action){
            $serviceName = $action."Service";
            $serviceNameVar = "$".$serviceName;
            $this->controllerText .=<<<EOF
/** @var {$serviceNameVar} {$serviceName} */
    private {$serviceNameVar};
    
EOF;
        }
        $this->controllerText .=<<<EOF
        
    public function __construct()
    {
    
EOF;
        foreach ($actionList as $action){
            $serviceName =  $action."Service";
            $this->controllerText .=<<<EOF
    {$this->thisText}{$serviceName} = new {$serviceName}();
    
EOF;
        }
        $this->controllerText .=<<<EOF
 }
     
     
EOF;
    }

    /**
     * 添加控制器的引用文件头
     * @param $interfaces
     * @param $group
     * @return array
     */
    private function addControllerUser($interfaces,$group){
        $actionList = [];
        foreach ($interfaces as $interface){
            $urlArray = explode('/',$interface['url']);
            $action = end($urlArray);
            $action = $this->convertUnderline($action);
            $actionList[] = $action;
            $this->controllerText .= "use APP\Http\Service\\".$group."\\".$action."Service;\n";
        }
        return $actionList;
    }
    /**
     * 获得项目和分组的信息
     * @param $oneInterface
     * @return array
     */
    private function getProjectGroup($oneInterface){
        $toReturn = [];
        if(isset($oneInterface['url'])){
            $urlArray = explode('/',$oneInterface['url']);
            do{
                $urlParam = array_shift($urlArray);
                if(!empty($urlParam)){
                    $toReturn[] = ucfirst($urlParam);
                }
            }while(count($toReturn) < 2);
        }
        return $toReturn;
    }

    /**
     * 获取远程的接口地址
     * @param $url
     * @return bool|string
     */
    private function curlGet($url){
        set_time_limit(0);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {return 'ERROR '.curl_error($curl);}
        curl_close($curl);
        return $data;
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