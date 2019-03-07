<?php
require  "./vendor/autoload.php";
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 2019/2/15
 * Time: 13:17
 */
$routeMake = new \WirelessCognitive\LaravelOrm\Route();
$vueRoute = new \WirelessCognitive\LaravelOrm\ApiForVue();
$vueRoute->make();