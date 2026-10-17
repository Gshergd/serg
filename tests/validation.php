<?php
require dirname(__DIR__).'/app.php';
$cases = [
 'https://example.com/a?b=1&c=2'=>true,
 'http://example.com/#part'=>true,
 'https://example.com:8443/path'=>true,
 'javascript:alert(1)'=>false,
 'data:text/html,test'=>false,
 '//example.com'=>false,
 'https://user:password@example.com'=>false,
 'https://ser.gt.tc/12345'=>false,
 'https://SER.GT.TC./12345'=>false,
 'https://www.ser.gt.tc/12345'=>false,
 "https://example.com/\r\nX-Test:yes"=>false,
 'https://example.com/with space'=>false,
];
foreach ($cases as $url=>$expected) {
 if (valid_destination($url)!==$expected) throw new Exception('Validation mismatch: '.json_encode($url));
}
if (e('<script>"&') !== '&lt;script&gt;&quot;&amp;') throw new Exception('Escaping failed');
if (valid_destination('https://example.com/'.str_repeat('a',4096))) throw new Exception('Length check failed');
echo "Passed destination validation, header-injection prevention, escaping and length checks.\n";
