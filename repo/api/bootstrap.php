<?php
/**
 * 拼豆工坊 · 服务器 PHP 代理（bootstrap）
 * 持有 Fine-grained PAT-A，统一经 GitHub Contents API 读写 repo/data/*.json。
 *
 * 配置文件加载顺序：
 *   1) 环境变量 BEAN_CONFIG 指向的路径
 *   2) /etc/bean/config.php
 *   3) 同目录 config.php
 * 生产环境请将真实 config.php 放在 web 根目录之外并 chmod 600。
 */

header('Content-Type: application/json; charset=utf-8');

function load_config() {
    $candidates = [];
    if ($e = getenv('BEAN_CONFIG')) $candidates[] = $e;
    $candidates[] = '/etc/bean/config.php';
    $candidates[] = __DIR__ . '/config.php';
    foreach ($candidates as $p) {
        if ($p && is_file($p)) return require $p;
    }
    return null;
}

$CFG = load_config();
if (!is_array($CFG) || empty($CFG['owner']) || empty($CFG['repo'])) {
    http_response_code(500);
    echo json_encode(['error' => '服务器未正确配置 config.php（owner/repo 缺失）']);
    exit;
}
$CFG['branch'] = $CFG['branch'] ?? 'main';
// 开发便利：pat_a 留空时走匿名只读（适用于公开仓库本地调试）；写入仍需填入 PAT-A。
$READ_ONLY = empty($CFG['pat_a']);

// ---- 可选 Admin Key 闸门（仅在配置了 admin_key 时启用）----
function require_admin_key() {
    global $CFG;
    if (empty($CFG['admin_key'])) return;
    $key = $_SERVER['HTTP_X_ADMIN_KEY'] ?? ($_GET['key'] ?? '');
    if (!is_string($key) || !hash_equals((string)$CFG['admin_key'], $key)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden: admin key required']);
        exit;
    }
}

// ---- 轻量限流（按 IP，异常时静默放行，不影响主流程）----
function rate_limit($key, $max = 60, $window = 60) {
    try {
        $dir = sys_get_temp_dir() . '/bean_rl';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $f = $dir . '/' . md5($key) . '.txt';
        $now = time();
        $hits = [];
        if (is_file($f)) {
            $hits = json_decode(@file_get_contents($f), true) ?: [];
            $hits = array_filter($hits, function ($t) use ($now, $window) { return $t > $now - $window; });
        }
        if (count($hits) >= $max) {
            http_response_code(429);
            echo json_encode(['error' => '请求过于频繁，请稍后再试']);
            exit;
        }
        $hits[] = $now;
        @file_put_contents($f, json_encode($hits));
    } catch (Throwable $e) { /* 限流组件异常不影响主流程 */ }
}

// ---- GitHub Contents 客户端（仅服务器端调用）----
function gh_call($path, $method = 'GET', $body = null) {
    global $CFG;
    $url = 'https://api.github.com/repos/' . $CFG['owner'] . '/' . $CFG['repo']
         . '/contents/' . ltrim($path, '/') . '?ref=' . $CFG['branch'];
    $ch = curl_init($url);
    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: bean-php-proxy',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if (!empty($CFG['pat_a'])) {
        $headers[] = 'Authorization: Bearer ' . $CFG['pat_a'];
    }
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20];
    if ($method === 'PUT') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        $opts[CURLOPT_POSTFIELDS] = $body;
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) throw new Exception('GitHub 请求失败: ' . $err);
    return ['code' => $code, 'body' => json_decode($resp, true)];
}

function gh_read($path) {
    try { $r = gh_call($path, 'GET'); }
    catch (Exception $e) { return ['content' => null, 'sha' => null]; }
    if ($r['code'] === 404) return ['content' => null, 'sha' => null];
    if ($r['code'] !== 200) throw new Exception('读取失败 HTTP ' . $r['code']);
    $content = json_decode(@base64_decode($r['body']['content'] ?? ''), true);
    return ['content' => $content, 'sha' => $r['body']['sha'] ?? null];
}

function gh_write($path, $data, $message = '') {
    global $CFG;
    $cur = gh_read($path);
    $payload = [
        'message' => $message ?: ('update ' . $path),
        'content' => base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
        'branch'  => $CFG['branch'],
    ];
    if ($cur['sha']) $payload['sha'] = $cur['sha'];
    $body = json_encode($payload);
    // 乐观锁：遇 409 并发冲突自动重读 sha 后重试一次
    for ($i = 0; $i < 2; $i++) {
        $r = gh_call($path, 'PUT', $body);
        if ($r['code'] === 200 || $r['code'] === 201) return $r['body'];
        if ($r['code'] === 409 && $i === 0) {
            $cur = gh_read($path);
            $arr = json_decode($body, true);
            $arr['sha'] = $cur['sha'];
            $body = json_encode($arr);
            continue;
        }
        throw new Exception('写入失败 HTTP ' . $r['code']);
    }
}
