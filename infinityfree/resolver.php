<?php
declare(strict_types=1);
function valid_destination(string $url): bool {
    if(strlen($url)>4096 || preg_match('/[\x00-\x20\x7f\\\\]/',$url) || !filter_var($url,FILTER_VALIDATE_URL))return false;
    $p=parse_url($url);return $p && in_array(strtolower($p['scheme']??''),['http','https'],true) && !isset($p['user']) && !isset($p['pass']) && !in_array(strtolower(rtrim($p['host']??'','.')),['ser.gt.tc','www.ser.gt.tc'],true);
}
function lookup(array $config,string $code,bool $count): array {
    $base=rtrim((string)($config['api_base_url']??''),'/');$secret=(string)($config['resolver_secret']??'');
    if(!preg_match('~^https://[a-zA-Z0-9.-]+(?::443)?$~D',$base)||strlen($secret)<32||strpos($secret,'REPLACE_')===0||preg_match('/[\r\n]/',$secret))throw new RuntimeException('Invalid hosting configuration');
    $url=$base.'/api/handler?action=resolve&code='.rawurlencode($code).'&count='.($count?'1':'0');
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is required');
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: application/json','X-Resolver-Key: '.$secret],CURLOPT_WRITEFUNCTION=>static function($handle,$data)use(&$body){if(strlen($body)+strlen($data)>16384)return 0;$body.=$data;return strlen($data);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($ok===false)throw new RuntimeException('Backend lookup failed');
    if(in_array($status,[404,410],true))return [$status,null];
    if($status!==200)throw new RuntimeException('Backend unavailable');
    $result=json_decode($body,true,8,JSON_THROW_ON_ERROR);$destination=$result['destination']??null;
    if(!is_string($destination)||!valid_destination($destination))throw new RuntimeException('Invalid destination');
    return [302,$destination];
}
