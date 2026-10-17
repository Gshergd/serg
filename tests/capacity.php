<?php
require dirname(__DIR__).'/app.php';
if (getenv('SERG_DISPOSABLE_DB') !== 'yes') exit("Run only against a disposable local database: set SERG_DISPOSABLE_DB=yes.\n");
$db=db(require dirname(__DIR__).'/config.php');
$db->beginTransaction();
$db->exec('DELETE FROM links');
$db->exec("INSERT INTO links(code,destination,label) SELECT LPAD(seq,5,'0'),'https://example.com','capacity fixture' FROM seq_0_to_99999");
try { insert_link($db,'https://example.com/overflow','overflow'); throw new Exception('Capacity overflow accepted'); }
catch (RuntimeException $ex) { if (strpos($ex->getMessage(),'100,000')===false) throw $ex; }
$db->exec("DELETE FROM links WHERE code='00007'");
$code=insert_link($db,'https://example.com/last-slot','last slot');
if ($code!=='00007') throw new Exception('Final slot allocation failed');
if ((int)$db->query('SELECT COUNT(*) FROM links')->fetchColumn()!==100000) throw new Exception('Wrong count');
$db->rollBack();
echo "Passed full 100,000-code capacity, collision handling, final free slot and leading-zero preservation.\n";
