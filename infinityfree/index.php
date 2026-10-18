<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Content-Type: text/plain; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if($path==='/'||$path==='/index.php'){header('Location: https://gshergd.github.io/serg/',true,302);exit;}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit('Method not allowed.');}
if(!is_string($path)||!preg_match('~^/[A-Za-z0-9]{5}$~D',$path)){http_response_code(404);exit('Unknown short link.');}
try{
    require __DIR__.'/resolver.php';
    if(!is_file(__DIR__.'/config.php'))throw new RuntimeException('Configuration missing');
    $config=require __DIR__.'/config.php';
    [$status,$destination]=lookup($config,substr($path,1),$_SERVER['REQUEST_METHOD']==='GET');
    if($status===302){header('Location: '.$destination,true,302);exit;}
    http_response_code($status);echo $status===410?'This short link is paused.':'Unknown short link.';
}catch(Throwable $error){error_log('serg redirect lookup failed: '.$error->getMessage());http_response_code(503);header('Retry-After: 30');echo 'Redirect service temporarily unavailable. Please try again shortly.';}
