<?php
// Konfigurasi Supabase REST API
$supabase_url = "https://howzeojdzegfntcvbwrj.supabase.co";
$supabase_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhvd3plb2pkemVnZm50Y3Zid3JqIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQzOTcyMTEsImV4cCI6MjA3OTk3MzIxMX0.Wf3BrtjipK0Q5o2HodHxvHIBpT61kLjcOHabcYZftPc";

// Helper function untuk call Supabase REST API
function supabase($table, $method = 'GET', $data = null) {
    global $supabase_url, $supabase_key;
    
    $url = $supabase_url . '/rest/v1/' . $table;
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $supabase_key,
        'Authorization: Bearer ' . $supabase_key,
        'Prefer: return=representation'
    ];
    
    // Prefer cURL when available, otherwise fall back to file_get_contents stream
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certs in local dev

        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            $json = is_string($data) ? $data : json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new Exception('cURL Error: ' . ($curl_error ?? 'Unknown error'));
        }

        if ($http_code >= 400) {
            throw new Exception('HTTP ' . $http_code . ': ' . substr($result, 0, 200));
        }

        return $result ? json_decode($result, true) : null;
    }

    // cURL not available — use stream context fallback
    $http_headers = [];
    foreach ($headers as $h) {
        $http_headers[] = $h;
    }

    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $http_headers),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];

    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        $opts['http']['content'] = is_string($data) ? $data : json_encode($data);
    }

    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);

    // Parse HTTP response code from $http_response_header
    $http_code = 0;
    if (!empty($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
        $http_code = (int)$m[1];
    }

    if ($result === false) {
        $err = error_get_last();
        throw new Exception('HTTP request failed: ' . ($err['message'] ?? 'Unknown error'));
    }

    if ($http_code >= 400) {
        throw new Exception('HTTP ' . $http_code . ': ' . substr($result, 0, 200));
    }

    return $result ? json_decode($result, true) : null;
}
?>