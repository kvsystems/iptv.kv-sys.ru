<?php
namespace Evie\Rest;

ini_set( 'error_reporting', E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );;

date_default_timezone_set( 'Asia/Yekaterinburg' );

session_start();

define( 'DS', DIRECTORY_SEPARATOR );
define( 'PS', PATH_SEPARATOR );
define( 'ROOT_DIR', realpath( dirname(__FILE__) ) . '/' );
require( ROOT_DIR . 'System/Init.php' );

$config = new System\Config([
    'username' => '',
    'password' => '',
    'database' => '',
]);

$request = new System\Request();
$api = new System\Api($config);
$response = $api->handle($request);
$response->output();
