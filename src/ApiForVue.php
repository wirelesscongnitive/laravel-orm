<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/3
 * Time: 13:29
 */

namespace WirelessCognitive\LaravelOrm;


class ApiForVue
{
    /** @var string $baseDir 生成文件的目录 */
  private $baseDir = './app/';
  /** @var string $indexJsText 首页路由列表的js文本 */
  private $indexJsText = '';
  /** @var string $moduleJsText 各个模块快捷路由方法的js文件 */
  private $moduleJsText = '';
    /**
     * 文件的入口方法
     */
  public function make(){
      set_time_limit(0);
      $data = file_get_contents('./data.json');
      $data = json_decode($data,true);
      if(isset($data['item']) && realArray($data['item'])){
          $modules = $data['item'];
          $this->initIndexJs();
          foreach ($modules as $module){
              if(isset($module['item']) && realArray($module['item'])){
                  $this->createModuleJs($module);
              }
          }
          $this->endIndexJs();
      }
  }

    /**
     * 获取url列表
     * @param $request
     * @return array
     */
    private function getUrlArray($request){
        $array = [];
        if(isset($request['url']) && isset($request['url']['raw'])){
            $temp = explode('/',$request['url']['raw']);
            if($temp[1] == 'helper'){
                $array = [
                    'module'=>$temp[1],
                    'api'=>$temp[2]
                ];
            }else{
                $array = [
                    'module'=>$temp[2],
                    'api'=>$temp[3]
                ];
            }
        }
        return $array;
    }

    /**
     * 生成各个模块的路由js
     * @param $module
     */
    private function createModuleJs($module){
        $this->indexJsText .= "\t/**\n\t *\t{$module['name']}\n\t */\n";
        $this->moduleJsText = "import\tapi\tfrom\t'./index'\nimport\t{axios}\tfrom\t'@/utils/request'\n";
        $moduleFileName = '';
        foreach ($module['item'] as $oneApi){
            if(isset($oneApi['request']) && realArray($oneApi['request'])){
                $urlArray = $this->getUrlArray($oneApi['request']);
                $this->addApiToIndex($oneApi['name'],$urlArray);
                $this->addOneApi($urlArray,$oneApi['name'],$oneApi['request']);
                if(!$moduleFileName){
                    $moduleFileName = $urlArray['module'].'.js';
                }
            }
        }
        if(!file_exists($this->baseDir.$moduleFileName)){
            file_put_contents($this->baseDir.$moduleFileName,$this->moduleJsText);
        }
    }

    /**
     * 获取请求参数列表
     * @param $request
     * @return  array
     */
    private function getParams($request){
        $toReturn = [];
        if($request['method'] == 'GET'){
            if(isset($request['url']) && isset($request['url']['query']) && realArray($request['url']['query'])){
                $toReturn = $request['url']['query'];
            }
        }else{
            if(isset($request['body']) && isset($request['body']['mode']) && isset($request['body'][$request['body']['mode']])){
                if(realArray($request['body'][$request['body']['mode']])){
                    $toReturn = $request['body'][$request['body']['mode']];
                }
            }
        }
        return $toReturn;
    }

    /**
     * 添加一个接口定义文件
     * @param $urlArray
     * @param $name
     * @param $request
     */
    private function addOneApi($urlArray,$name,$request)
    {
        $params = $this->getParams($request);
        if(realArray($params)){
            $parameterText = 'parameter';
            $this->moduleJsText .= "\n/**\n*\t" . $name . "\n* parameter:{\n";
            foreach ($params as $key=>$param){
                $descriptionText = $param['description']?"//\t{$param['description']}":'';
                $valueText = $param['value']?("'".$param['value']."'"):"''";
                $this->moduleJsText .= "* \t{$param['key']} : {$valueText},{$descriptionText}\n";
            }
            $this->moduleJsText .= "* }\n* @param parameter\n* @returns {*}\n*/\n";
        }else{
            $this->moduleJsText .= "\n/**\n*" . $name . "\n* @return {*}\n*/\n";
            $parameterText = '';
        }
        $functionName = $this->getFunctionName($request['method'],$urlArray);
        $this->moduleJsText .="export function {$functionName} ({$parameterText}){\n\treturn axios({\n";
        if($urlArray['module'] == 'helper'){
            $this->moduleJsText .="\t\turl : '/helper/{$urlArray['api']}',\n";
        }else{
            $this->moduleJsText .="\t\turl : '/flat/{$urlArray['module']}/{$urlArray['api']}',\n";
        }
        $this->moduleJsText.="\t\tmethod : '".strtolower($request['method'])."',\n";
        if(realArray($params)){
            $this->moduleJsText.="\t\tdata : parameter,\n";
        }
        if($request['method'] == 'GET'){
            $this->moduleJsText .= "\t\theaders : {\n";
            $this->moduleJsText .= "\t\t\t'Content-Type' : 'application/json : charset=UTF-8'\n\t\t}\n";
        }
        $this->moduleJsText .="\t})\n}\n";
    }

    /**
     * 获取函数名称
     * @param $method
     * @param $urlArray
     * @return  string
     */
    private function getFunctionName($method,$urlArray){
        $apiText = $this->getApiText($urlArray['api']);
        if($method == 'GET'){
            return 'get'.ucfirst($urlArray['module']).$apiText;
        }else{
            return $urlArray['module'].$apiText;
        }
    }

    /**
     * 获取大写信息列表
     * @param $api
     * @return string
     */
    private function getApiText($api){
        $apiArray = explode('_',$api);
        $apiText = '';
        foreach ($apiArray as $oneParam){
            $apiText .= ucfirst($oneParam);
        }
        return $apiText;
    }
    /**
     * 添加接口到index文件
     * @param $name
     * @param $urlArray
     */
    private function addApiToIndex($name,$urlArray){
        $apiText = $this->getApiText($urlArray['api']);
        $apiName = ucfirst($urlArray['module']).$apiText;
        $this->indexJsText .= "\t{$apiName} : '".($urlArray['module']=='helper'?'':'/flat')."/".$urlArray['module']."/".$urlArray['api']."',\t//".$name."\n";
    }

    /**
     * 结束初始化路由
     */
    private function endIndexJs(){
      $this->indexJsText .= "};\nexport default api\n";
      if(!file_exists($this->baseDir.'index.js')){
          file_put_contents($this->baseDir.'index.js',$this->indexJsText);
      }
    }

    /**
     * 初始化路由列表js
     */
    private function initIndexJs(){
        $this->indexJsText = 'const api = {'."\n";
    }
}