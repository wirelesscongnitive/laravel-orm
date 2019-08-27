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
    public $controllerBaseUrl = './app/Controllers/';

    /** @var $controllerText string 控制器的文本信息 */
    public $controllerText;

    /** @var string $projectControllerUrl 当前项目的路由地址 */
    public $projectControllerUrl = '';

    /** @var string $thisText 文本标识 */
    public $thisText = '$this->';

    /** @var string $serviceBaseDir 服务层的默认路径 */
    public $serviceBaseDir = './app/Service/';

    /** @var string $requestBaseDir 创建路由过滤层代码 */
    public $requestBaseDir = './app/Requests/';

    /**
     * 初始化目录信息
     * @param $project
     */
    private function initDir($project){
        if(!is_dir($this->controllerBaseUrl)){
            mkdir($this->controllerBaseUrl,0777);
        }
        if(!is_dir($this->serviceBaseDir)){
            mkdir($this->serviceBaseDir,0777);
        }
        if(!is_dir($this->requestBaseDir)){
            mkdir($this->requestBaseDir,0777);
        }
        if(!is_dir($this->controllerBaseUrl.$project)){
            mkdir($this->controllerBaseUrl.$project,0777);
        }
        $this->projectControllerUrl = $this->controllerBaseUrl.$project."/";
    }

    /**
     * 生成路由文件
     */
    public function makeRoute(){
        set_time_limit(0);
        $data = file_get_contents('./data.json');
        $data = json_decode($data,true);
        if(isset($data['item']) && realArray($data['item'])){
            $modules = $data['item'];
            $project = $this->getProject($modules[0]);
            $this->initDir($project);
            $this->pushToRoute(0,"<?php");
            $this->pushToRoute(0,"use Illuminate\Support\Facades\Route;");
            $this->pushToRoute(1,"Route::prefix('".strtolower($project)."')->group(function(){");
            $this->makeBaseController();
            foreach ($modules as $module){
                $this->makeModule($module);
            }
            $this->pushToRoute(1,"});");
            file_put_contents("./app/route.php",$this->routeText);
        }
    }
    private function makeBaseController(){
        $text = <<<EOF
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
EOF;
        if(!file_exists($this->controllerBaseUrl."Controller.php")){
            file_put_contents($this->controllerBaseUrl."Controller.php",$text);
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
        if(isset($module['item']) && realArray($module['item'])){
            $data = $this->getProjectGroup($module['item'][0]);
            $projectName = $data[0];
        }
        return $projectName;
    }

    /**
     * 获取接口的url数组
     * @param $interface array
     * @return array
     */
    private function getUrlArray($interface){
        $urlArray = [];
        if(
            isset($interface['request']) &&
            isset($interface['request']['url']) &&
            isset($interface['request']['url']['path']) &&
            realArray($interface['request']['url']['path'])
        )
        $urlArray = explode('/',$interface['request']['url']['path'][0]);
        return $urlArray;
    }

    /**
     * 生成单一接口的脚手架
     * @param $interface
     * @param $group
     * @param $project
     */
    public function makeInterface($interface,$project,$group){
        $urlArray = $this->getUrlArray($interface);
        $uri = end($urlArray);
        $action = convertUnderline($uri);
        $method = strtolower($interface['request']["method"]);
        $lowerAction = lcfirst($action);
        $this->pushToRoute(3,"Route::{$method}('{$uri}','{$project}\\".$group."Controller@{$lowerAction}');//".$interface['name']);
        if(isset($interface['request']) && isset($interface['request']['body'])){
            if(isset($interface['request']['body']['formdata']) && realArray($interface['request']['body']['formdata'])){
                $params = $interface['request']['body']['formdata'];
            }else if(isset($interface['request']['url']['query'])){
                $params = $interface['request']['url']['query'];
            }else{
                $params = [];
            }
            if($method == 'get'){
                $requestName = "Request";
            }else{
                $requestName = ucfirst($lowerAction)."Request";
                $this->makeRequest($requestName,$group,$params,$interface['name']);
            }
            $this->addFunctionNote($params,$interface['name'],$requestName);
            $requestText = realArray($params)?"{$requestName} ".'$request':"";
            $this->controllerText .= "\tpublic function {$lowerAction}({$requestText}){\n";
            $this->addGetParam($params);
            $dataText = '$data';
            $paramsText = realArray($params)?($this->makeParamText($params)):"";
            $this->controllerText .= <<<EOF
        {$dataText} = (new {$action}Service())->handle({$paramsText});
        return succ({$dataText});      
    }
EOF;
            $this->controllerText .= "\n\n";
            $this->makeService($action,$group,$params,$interface['name']);
        }
    }

    /**
     * 生成脚本的请求集合文本
     * @param $params
     * @return string
     */
    private function makeParamText($params){
        $paramText = '';
        foreach ($params as $param) {
            $paramText .= '$'.$param['key'].',';
        }
        $paramText =rtrim($paramText, ',');
        return $paramText;
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
        $content = "<?php\nnamespace App\Http\Service\\".ucfirst($group).";\n\n";
        $content .= "/**\n*{$name}\n*/\n";
        $content .= "class ".$action.'Service{'."\n";
        $varText = '$';
        $content .="\t/**\n";
        foreach ($params as $param){
            $typeText = isset($param["type"])?$param["type"]:'string';
            if($typeText == 'text')$typeText = 'string';
            $content .= "\t*\t@param\t{$varText}{$param['key']}\t{$typeText}\t{$param['description']}\n";
        }
        $content .="\t* @return array\n\t*/\n";
        $content.="\tpublic function handle(".($this->makeParamText($params))."){\n\n\n";
        $content.= "\t\treturn ['{$name}'];";
        $content.= "\n\t}\n";
        $content .= "}";
        if(!file_exists($fileName)){
            file_put_contents($fileName,$content);
        }
    }

    /**
     * 创建路由过滤层
     * @param $requestName
     * @param $group
     * @param $params
     * @param $name
     */
    private function makeRequest($requestName,$group,$params,$name){
        if(!is_dir($this->requestBaseDir.$group)){
            mkdir($this->requestBaseDir.$group,0777);
        }
        $fileName = $this->requestBaseDir.$group.'/'.$requestName.'.php';
        $content = <<<EOF
<?php
/**
 * {$name}
 * Created by PhpStorm.
 * User: wirelessCognitive
 */

EOF;
        $content .= "namespace App\Http\Requests\\".$group.";\n";
        $content .= <<<EOF
use App\Http\Requests\MiaoMiRequest;

class {$requestName} Extends MiaoMiRequest
{
    /**
     * 是否需要用户登录
     * @return bool
     */
    public function authorize(){
        return true;
    }
    
    /**
     * 验证规则
     * @return array
     */
    public function rules(){
        return [

EOF;
        foreach ($params as $param){
            $content .= "\t\t\t'{$param['key']}'\t=>\t'required',\t//{$param['description']}\n";
        }
        $content .= "\t\t];\n\t}\n\n";
$content .= <<<EOF
    /**
     * 验证返回消息内容
     * @return array
     */
    public function messages(){
        return [
        
EOF;
        foreach ($params as $param){
            $content .= "\t\t\t'{$param['key']}.required'\t=>\t'需要输入{$param['description']}',\n";
        }
        $content .= "\t\t];\n\t}\n}\n";
        if(!file_exists($fileName)){
            file_put_contents($fileName,$content);
        }
    }

    /**
     * 获取参数
     * @param $properties
     */
    private function addGetParam($properties){
        $requestText = '$request->';
        foreach ($properties as $param){
            $lowerName = strtolower($param['key']);
            $paramList[] = $lowerName;
            $this->controllerText .= "\t\t$".$lowerName.'  = '.$requestText.'input("'.$lowerName.'","");'."\n";
        }
    }

    /**
     * 添加方法的注解
     * @param $properties array
     * @param $name string
     * @param $requestName string 请求过滤器的名称
     */
    private function addFunctionNote($properties,$name,$requestName){
        $requestText = '$request';
        $this->controllerText .="\t/**\n\t* {$name}\n";
        foreach ($properties as $param){
            $keyName = "$".$param['key'];
            $typeName = isset($param['type'])?$param['type']:'string';
            if($typeName == 'text')$typeName = 'string';
            $this->controllerText .= "\t* {$keyName}\t{$typeName}\t{$param['description']}\n";
        }
        if(realArray($properties)){
            $this->controllerText .="\t* @param {$requestName} {$requestText}\n\t* @return array\n\t*/\n";
        }else{
            $this->controllerText .="\t* @return array\n\t*/\n";
        }
    }

    /**
     * 生成一个模型的脚手架
     * @param $module
     */
    private function makeModule($module){
        if(isset($module['item']) && realArray($module['item'])){
            list($project,$group) = $this->getProjectGroup($module['item'][0]);
            $this->controllerText = '<?php'."\n".'namespace App\Http\Controllers\\'.$project.";\n\nuse Illuminate\Http\Request;\nuse App\Http\Controllers\Controller;\n";
            $this->addControllerUser($module['item'],$group);
            $this->controllerText .=<<<EOF
            
class {$group}Controller extends Controller{

EOF;
            $this->pushToRoute(2,"/**".$module['name']."*/");
            $this->pushToRoute(2,"Route::prefix('".strtolower($group)."')->group(function(){");
            foreach ($module['item'] as $interface){
                $this->makeInterface($interface,$project,$group);
            }
            $this->pushToRoute(2,"});");
            $this->controllerText .= "\n}\n";
            file_put_contents($this->projectControllerUrl.$group."Controller.php",$this->controllerText);
            $this->controllerText = '';
        }
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
            $method = strtolower($interface['request']["method"]);
            if($method == 'put'){
                $urlArray = $this->getUrlArray($interface);
                $lowerAction = lcfirst(convertUnderline(end($urlArray)));
                $requestName = ucfirst($lowerAction)."Request";
                $this->controllerText .= "use App\Http\Requests\\{$group}\\{$requestName};\n";
            }
        }
        foreach ($interfaces as $interface){
            $urlArray = $this->getUrlArray($interface);
            $action = end($urlArray);
            $action = convertUnderline($action);
            $actionList[] = $action;
            $this->controllerText .= "use App\Http\Service\\".$group."\\".$action."Service;\n";
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
        $urlArray = $this->getUrlArray($oneInterface);
        if(realArray($urlArray)){
            do{
                $urlParam = array_shift($urlArray);
                if(!empty($urlParam)){
                    $toReturn[] = ucfirst($urlParam);
                }
            }while(count($toReturn) < 2);
        }
        return $toReturn;
    }
}