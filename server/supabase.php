<?php
// server/supabase.php
// Simple Supabase helpers for server-side verification and REST queries.

function supabase_request($method, $path, $body = null, $useServiceRole = true) {
    $cfg = @include __DIR__ . '/supabase_config.php';
    if (!is_array($cfg)) return null;
    $base = rtrim($cfg['url'] ?? '', '/');
    $url = $base . $path;

    $headers = [
        'Content-Type: application/json',
    ];
    // For POST requests, request the created representation so server returns inserted rows
    if (strtoupper($method) === 'POST') {
        $headers[] = 'Prefer: return=representation';
    }
    if ($useServiceRole && !empty($cfg['service_role_key'])) {
        $headers[] = 'apikey: ' . $cfg['service_role_key'];
        $headers[] = 'Authorization: Bearer ' . $cfg['service_role_key'];
    } elseif (!empty($cfg['anon_key'])) {
        $headers[] = 'apikey: ' . $cfg['anon_key'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if ($body !== null) {
            $json = is_string($body) ? $body : json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) return null;
        $data = json_decode($resp, true);
        return ['status'=>$code, 'body'=>$data, 'raw'=>$resp, 'error'=>$err];
    }

    // Fallback: use file_get_contents with stream context
    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ];
    if ($body !== null) {
        $opts['http']['content'] = is_string($body) ? $body : json_encode($body);
    }
    // Allow self-signed certs on local dev (mirror previous behavior)
    $opts['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false];

    $context = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $context);
    $code = 0;
    if (!empty($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    return ['status'=>$code, 'body'=>$data, 'raw'=>$resp, 'error'=>null];
}

function supabase_get_user($idToken) {
    // Calls Supabase Auth endpoint /auth/v1/user to get user info from token
    $cfg = @include __DIR__ . '/supabase_config.php';
    if (!is_array($cfg)) return null;
    $base = rtrim($cfg['url'] ?? '', '/');
    $url = $base . '/auth/v1/user';

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $idToken,
    ];
    if (!empty($cfg['service_role_key'])) $headers[] = 'apikey: ' . $cfg['service_role_key'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) return null;
        $data = json_decode($resp, true);
        return ['status'=>$code, 'body'=>$data, 'raw'=>$resp];
    }

    // Fallback using file_get_contents
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ];
    $context = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $context);
    $code = 0;
    if (!empty($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    return ['status'=>$code, 'body'=>$data, 'raw'=>$resp];
}

function supabase_safe_request($method, $path, $body = null, $useServiceRole = true) {
    $res = supabase_request($method, $path, $body, $useServiceRole);
    if ($res === null) return ['ok' => false, 'status' => 0, 'message' => 'Request failed', 'raw' => null];
    $status = $res['status'] ?? 0;
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'status' => $status, 'body' => $res['body'] ?? null, 'raw' => $res['raw'] ?? null];
    }
    $msg = 'Supabase returned HTTP ' . $status;
    if (!empty($res['error'])) $msg .= ': ' . $res['error'];
    return ['ok' => false, 'status' => $status, 'message' => $msg, 'body' => $res['body'] ?? null, 'raw' => $res['raw'] ?? null];
}

function supabase_is_admin_request() {
    // Returns ['ok'=>true,'user'=>..., 'admin'=>...] or ['ok'=>false,'reason'=>...]
    $cfg = @include __DIR__ . '/supabase_config.php';
    if (!is_array($cfg)) $cfg = [];

    // Get Authorization header or POST idToken
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = $v;
            }
        }
    }
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    if (!$authHeader && !empty($_POST['idToken'])) $authHeader = 'Bearer ' . trim($_POST['idToken']);
    if (!$authHeader) return ['ok'=>false,'reason'=>'no_token'];
    if (stripos($authHeader,'Bearer ') !== 0) return ['ok'=>false,'reason'=>'malformed_auth_header'];
    $idToken = trim(substr($authHeader,7));

    // Get user info from Supabase
    $userResp = supabase_get_user($idToken);
    if (!$userResp || $userResp['status'] !== 200 || empty($userResp['body'])) return ['ok'=>false,'reason'=>'invalid_token','detail'=>$userResp];
    $user = $userResp['body'];

    // Query admin table by email or id (email preferred)
    $email = $user['email'] ?? null;
    $uid = $user['id'] ?? null;

    if (!$email && !$uid) return ['ok'=>false,'reason'=>'no_identifiers','user'=>$user];

    // Use REST to query admin table
    $filter = '';
    if ($email) $filter = '?email=eq.' . urlencode($email);
    else $filter = '?id_admin=eq.' . urlencode($uid);

    $res = supabase_request('GET', '/rest/v1/admin' . $filter, null, true);
    if (!$res) return ['ok'=>false,'reason'=>'query_failed'];
    if ($res['status'] >= 200 && $res['status'] < 300) {
        $rows = $res['body'];
        if (is_array($rows) && count($rows) > 0) {
            return ['ok'=>true, 'user'=>$user, 'admin'=>$rows[0]];
        }
        return ['ok'=>false,'reason'=>'not_admin','user'=>$user,'rows'=>$rows];
    }
    return ['ok'=>false,'reason'=>'rest_error','status'=>$res['status'],'body'=>$res['body']];
}
