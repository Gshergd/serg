<?php
declare(strict_types=1);
const BASE_URL = 'https://ser.gt.tc';
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $path): void { header('Location: '.$path, true, 303); exit; }
function valid_destination(string $url): bool {
    if (strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $p = parse_url($url);
    return $p && in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true)
        && !isset($p['user']) && !isset($p['pass'])
        && !in_array(strtolower(rtrim($p['host'] ?? '', '.')), ['ser.gt.tc','www.ser.gt.tc'], true);
}
function db(array $config): PDO {
    return new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
        $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
}
function owner(PDO $db): ?string {
    $row = $db->query('SELECT password_hash FROM settings WHERE id=1')->fetch();
    return $row ? $row['password_hash'] : null;
}
function logged_in(): bool {
    if (empty($_SESSION['owner']) || time() - ($_SESSION['seen'] ?? 0) > 1800) {
        unset($_SESSION['owner']); return false;
    }
    $_SESSION['seen'] = time(); return true;
}
function csrf_input(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Your session changed. Reload the page and try again.');
    }
}
function consume_attempt(PDO $db, array $config, string $kind): bool {
    // DB-backed throttling survives cookie deletion and multiple browser sessions.
    $bucket = hash_hmac('sha256', $kind.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $config['setup_key']);
    $now = time();
    $s = $db->prepare('INSERT INTO attempts (bucket,hits,expires) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=IF(expires<=?,1,hits+1), expires=IF(expires<=?,VALUES(expires),expires)');
    $s->execute([$bucket,$now+900,$now,$now]);
    $s = $db->prepare('SELECT hits FROM attempts WHERE bucket=?'); $s->execute([$bucket]);
    if (random_int(1,100)===1) $db->prepare('DELETE FROM attempts WHERE expires<?')->execute([$now]);
    return (int)$s->fetchColumn() <= 10;
}
function insert_link(PDO $db, string $url, string $label): string {
    $insert = $db->prepare('INSERT INTO links (code,destination,label) VALUES (?,?,?)');
    for ($try=0; $try<36; $try++) {
        if ($try < 32) $code = sprintf('%05d', random_int(0,99999));
        else {
            // Near capacity, find an actual free slot rather than extending the code.
            $cursor = 0;
            foreach ($db->query('SELECT code FROM links ORDER BY code') as $row) {
                if ((int)$row['code'] > $cursor) break;
                $cursor++;
            }
            if ($cursor > 99999) throw new RuntimeException('All 100,000 five-digit codes are reserved.');
            $code = sprintf('%05d', $cursor);
        }
        try { $insert->execute([$code,$url,$label]); return $code; }
        catch (PDOException $ex) { if (($ex->errorInfo[1] ?? null) !== 1062) throw $ex; }
    }
    throw new RuntimeException('A code was just taken. Please try again.');
}
