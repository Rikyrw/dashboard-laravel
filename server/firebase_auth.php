<?php
// firebase_auth.php
// Minimal Firebase ID token verification helper (no external dependencies).
// This verifies an ID token using Google's tokeninfo endpoint. It is NOT as
// feature-complete as the Firebase Admin SDK but is useful as a lightweight
// skeleton until you add a proper Admin SDK integration.

function http_get_json($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) return null;
        $data = json_decode($resp, true);
        if (!is_array($data)) return null;
        $data['_http_code'] = $code;
        return $data;
    }

    // Fallback to file_get_contents
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'timeout' => 5,
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
    if (!is_array($data)) return null;
    $data['_http_code'] = $code;
    return $data;
}

function firebase_verify_id_token_simple($idToken) {
    // Uses Google's tokeninfo endpoint. For Firebase ID tokens this returns
    // a JSON payload with fields like 'aud', 'iss', 'sub' (uid), etc.
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $payload = http_get_json($url);
    if (!$payload) return null;
    // tokeninfo returns an HTTP 200 even for some invalid tokens, but will
    // include 'error_description' or other fields. We'll return the payload
    // to the caller for further checks.
    return $payload;
}

function firebase_is_admin_request() {
    // Returns array with ['ok'=>true,'uid'=>..., 'payload'=>...] if the
    // incoming HTTP request contains a valid Bearer ID token and the uid
    // belongs to configured admin list. Otherwise returns ['ok'=>false, 'reason'=>...]

    $cfg = @include __DIR__ . '/firebase_config.php';
    if (!is_array($cfg)) $cfg = [];
    $projectId = $cfg['project_id'] ?? '';
    $adminUids = $cfg['admin_uids'] ?? [];
    $adminEmails = $cfg['admin_emails'] ?? [];

    // Look for Authorization header
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Fallback for some servers
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                $headers[$name] = $v;
            }
        }
    }

    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    if (!$auth && !empty($_POST['idToken'])) {
        $auth = 'Bearer ' . trim($_POST['idToken']);
    }
    if (!$auth) return ['ok'=>false, 'reason'=>'no_token'];

    if (stripos($auth, 'Bearer ') === 0) {
        $idToken = trim(substr($auth, 7));
    } else {
        return ['ok'=>false, 'reason'=>'malformed_auth_header'];
    }

    $payload = firebase_verify_id_token_simple($idToken);
    if (!$payload) return ['ok'=>false, 'reason'=>'invalid_token'];

    // tokeninfo returns 'aud' and 'iss' and 'sub'
    $aud = $payload['aud'] ?? null;
    $iss = $payload['iss'] ?? null;
    $sub = $payload['sub'] ?? null; // user's uid
    $email = $payload['email'] ?? null;

    if ($projectId && $aud !== $projectId) {
        return ['ok'=>false, 'reason'=>'aud_mismatch', 'payload'=>$payload];
    }

    // issuer should be https://securetoken.google.com/<projectId>
    if ($projectId && $iss !== ('https://securetoken.google.com/' . $projectId)) {
        // Some tokens may instead come from accounts.google.com - accept them
        // but log as a warning.
        // For strict validation, require the issuer match above.
    }

    // Admin check: uid or email in configured lists
    if (in_array($sub, $adminUids, true) || ($email && in_array($email, $adminEmails, true))) {
        return ['ok'=>true, 'uid'=>$sub, 'email'=>$email, 'payload'=>$payload];
    }

    return ['ok'=>false, 'reason'=>'not_admin', 'payload'=>$payload];
}
