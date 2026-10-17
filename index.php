<?php
declare(strict_types=1);
require __DIR__.'/app.php';
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
if (!$local && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    header('Location: '.BASE_URL.($_SERVER['REQUEST_URI'] ?? '/'), true, 302); exit;
}
session_name('serg_owner');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!$local,'httponly'=>true,'samesite'=>'Strict']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path ?: '/', '/') ?: '/';
$error = ''; $notice = $_SESSION['notice'] ?? ''; unset($_SESSION['notice']);
$mode = 'login'; $rows = []; $count=0; $total=0; $clicks=0; $page=1; $pages=1; $q='';
try {
    if (!is_file(__DIR__.'/config.php')) throw new RuntimeException('Hosting configuration is missing. Follow the included setup guide.');
    $config = require __DIR__.'/config.php';
    if (!is_array($config) || strlen($config['setup_key'] ?? '') < 32 || strpos($config['setup_key'], 'REPLACE_') === 0) {
        throw new RuntimeException('Set a unique setup key in config.php before opening the installer.');
    }
    $db = db($config);
    if (preg_match('~^/[0-9]{5}$~D', $path)) {
        // Public redirects never require the owner session.
        $s=$db->prepare('SELECT destination,active FROM links WHERE code=?'); $s->execute([substr($path,1)]); $link=$s->fetch();
        if (!$link || !$link['active']) { http_response_code($link ? 410 : 404); $mode='missing'; }
        else {
            if (!valid_destination($link['destination'])) throw new RuntimeException('This destination is unavailable.');
            if ($_SERVER['REQUEST_METHOD']==='GET') {
                try { $db->prepare('UPDATE links SET clicks=clicks+1 WHERE code=?')->execute([substr($path,1)]); }
                catch (PDOException $ignore) { error_log('serg: click counter update failed'); }
            }
            session_write_close(); header('Location: '.$link['destination'],true,302); exit;
        }
    } elseif (!in_array($path, ['/','/index.php','/admin','/login','/setup'],true)) {
        http_response_code(404); $mode='missing';
    } else {
        // Tables are installed explicitly by the owner in phpMyAdmin.
        $hash = owner($db);
        if (!$hash) $mode='setup';
        elseif (logged_in()) $mode='admin';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            check_csrf(); $action=(string)($_POST['action'] ?? '');
            if ($mode==='setup' && $action==='setup') {
                if (!consume_attempt($db,$config,'setup')) { http_response_code(429); $error='Too many attempts. Try again in 15 minutes.'; }
                elseif (!hash_equals($config['setup_key'],(string)($_POST['key'] ?? ''))) $error='The setup key is incorrect.';
                elseif (strlen((string)($_POST['password'] ?? ''))<14 || strlen((string)$_POST['password'])>72) $error='Use a password of 14–72 bytes.';
                elseif ($_POST['password'] !== ($_POST['confirm'] ?? '')) $error='The passwords do not match.';
                else {
                    // Unique id prevents concurrent setup requests from replacing the owner.
                    $db->prepare('INSERT INTO settings (id,password_hash) VALUES (1,?)')->execute([password_hash($_POST['password'],PASSWORD_DEFAULT)]);
                    session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
                    $_SESSION['owner']=true; $_SESSION['seen']=time(); go('/admin');
                }
            } elseif ($mode==='login' && $action==='login') {
                if (!consume_attempt($db,$config,'login')) { http_response_code(429); $error='Too many attempts. Try again in 15 minutes.'; }
                elseif (strlen((string)($_POST['password'] ?? ''))<=72 && password_verify((string)($_POST['password'] ?? ''),$hash)) {
                    session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
                    $_SESSION['owner']=true; $_SESSION['seen']=time(); go('/admin');
                } else $error='That password is incorrect.';
            } elseif ($mode==='admin') {
                if ($action==='logout') { $_SESSION=[]; session_destroy(); go('/'); }
                elseif ($action==='create') {
                    $url=trim((string)($_POST['url'] ?? '')); $label=trim((string)($_POST['label'] ?? ''));
                    if (!valid_destination($url)) $error='Enter a full http:// or https:// destination outside ser.gt.tc, without login credentials.';
                    elseif (strlen($label)>120) $error='Keep the label within 120 bytes.';
                    else {
                        $code=insert_link($db,$url,$label);
                        $_SESSION['notice']='Created '.BASE_URL.'/'.$code; $_SESSION['created']=$code; go('/admin');
                    }
                } elseif ($action==='toggle' && preg_match('/^[0-9]{5}$/D',(string)($_POST['code'] ?? ''))) {
                    $db->prepare('UPDATE links SET active=1-active WHERE code=?')->execute([$_POST['code']]);
                    $_SESSION['notice']='Link status updated.'; go('/admin');
                }
            } else { http_response_code(403); $error='Sign in to continue.'; }
        }
        if ($mode==='admin') {
            $q=substr(trim((string)($_GET['q'] ?? '')),0,120);
            $where=$q!=='' ? ' WHERE code LIKE ? OR label LIKE ? OR destination LIKE ?' : '';
            $params=$q!=='' ? array_fill(0,3,'%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%') : [];
            if ($q!=='') $where=" WHERE code LIKE ? ESCAPE '!' OR label LIKE ? ESCAPE '!' OR destination LIKE ? ESCAPE '!'";
            $s=$db->prepare('SELECT COUNT(*) FROM links'.$where); $s->execute($params); $count=(int)$s->fetchColumn();
            $total=(int)$db->query('SELECT COUNT(*) FROM links')->fetchColumn();
            $clicks=(int)$db->query('SELECT COALESCE(SUM(clicks),0) FROM links')->fetchColumn();
            $pages=max(1,(int)ceil($count/20)); $page=min($pages,max(1,(int)($_GET['page'] ?? 1))); $offset=($page-1)*20;
            $s=$db->prepare('SELECT * FROM links'.$where.' ORDER BY created_at DESC,code DESC LIMIT 20 OFFSET '.$offset); $s->execute($params); $rows=$s->fetchAll();
        }
    }
} catch (Throwable $ex) {
    error_log('serg: '.$ex->getMessage()); http_response_code(503); $mode='unavailable';
    $error='The service needs hosting configuration. Check config.php and import schema.sql in phpMyAdmin.';
}
$created=$_SESSION['created'] ?? null; unset($_SESSION['created']);
require __DIR__.'/view.php';
