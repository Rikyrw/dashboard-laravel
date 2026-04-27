<?php
// test_connection.php
session_start();
echo "<!DOCTYPE html><html><head><title>Test Connection</title>";
echo "<style>body{font-family:Inter,sans-serif;padding:20px;max-width:1200px;margin:auto}</style>";
echo "</head><body>";
echo "<h2>🔧 Test Connection & Debug System</h2>";

// Test Supabase Helper
if (file_exists(__DIR__ . '/../../server/supabase.php')) {
    include_once __DIR__ . '/../../server/supabase.php';

    echo "<h3>1. Supabase Helper Functions</h3>";
    echo "supabase_request exists: " . (function_exists('supabase_request') ? '✅ YES' : '❌ NO') . "<br>";
    echo "supabase_is_admin_request exists: " . (function_exists('supabase_is_admin_request') ? '✅ YES' : '❌ NO') . "<br>";

    // Test connection
    echo "<h3>2. Test Database Connection</h3>";
    $result = supabase_request('GET', '/rest/v1/admin?limit=1', null, true);
    echo "Status: " . ($result['status'] ?? 'NO RESPONSE') . "<br>";
    echo "Count: " . (is_array($result['body'] ?? null) ? count($result['body']) : '0') . "<br>";

    // List all admins
    echo "<h3>3. Existing Admins with Roles</h3>";
    $admins = supabase_request('GET', '/rest/v1/admin?select=id_admin,username,email,role,status', null, true);
    if ($admins && isset($admins['status']) && $admins['status'] == 200) {
        echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
        echo "<tr style='background:#f3f4f6;'><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        foreach ($admins['body'] as $admin) {
            $roleClass = '';
            if ($admin['role'] == 'superadmin') $roleClass = 'style="background:#7c3aed;color:white;"';
            elseif ($admin['role'] == 'admin') $roleClass = 'style="background:#059669;color:white;"';

            echo "<tr>";
            echo "<td>" . htmlspecialchars($admin['id_admin'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($admin['username'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($admin['email'] ?? '') . "</td>";
            echo "<td><span $roleClass style='padding:2px 8px;border-radius:3px;'>" . htmlspecialchars($admin['role'] ?? '') . "</span></td>";
            echo "<td>" . htmlspecialchars($admin['status'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Error fetching admins. Status: " . ($admins['status'] ?? 'N/A') . "<br>";
        echo "Error: " . json_encode($admins['body'] ?? []);
    }
} else {
    echo "❌ Supabase helper not found at: " . __DIR__ . '/../../server/supabase.php';
}

// Session info
echo "<h3>4. Session Info</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . " (" .
    (session_status() === PHP_SESSION_ACTIVE ? 'Active' : (session_status() === PHP_SESSION_NONE ? 'None' : 'Disabled')) . ")<br>";
echo "<strong>Session Data:</strong><br>";
echo "<pre style='background:#f3f4f6;padding:10px;border-radius:5px;'>";
print_r($_SESSION);
echo "</pre>";

// Test CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
echo "<h3>5. CSRF Token</h3>";
echo "Token: " . substr($_SESSION['csrf_token'] ?? 'Not set', 0, 20) . "...<br>";

// Create test admin button
echo "<h3>6. Quick Actions</h3>";
echo "<div style='display:flex;gap:10px;margin-bottom:20px;'>";
echo "<a href='setup_admin.php' style='padding:10px 20px;background:#059669;color:white;text-decoration:none;border-radius:5px;font-weight:bold;'>📝 Setup Admin</a> ";
echo "<a href='login.php' style='padding:10px 20px;background:#3b82f6;color:white;text-decoration:none;border-radius:5px;font-weight:bold;'>🔐 Login Page</a> ";
echo "<a href='dashboard.php' style='padding:10px 20px;background:#8b5cf6;color:white;text-decoration:none;border-radius:5px;font-weight:bold;'>📊 Dashboard</a>";
echo "</div>";

// Test queries
echo "<h3>7. Test Specific Queries</h3>";
echo "<form method='POST' style='background:#f3f4f6;padding:15px;border-radius:5px;'>";
echo "<label>Test Email:</label> ";
echo "<input type='email' name='test_email' value='admin@greenpoint.com' style='padding:5px;margin:5px;'> ";
echo "<button type='submit' name='test_query' style='padding:5px 15px;background:#059669;color:white;border:none;border-radius:3px;'>Test Query</button>";
echo "</form>";

if (isset($_POST['test_query']) && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    echo "<h4>Query Results for: " . htmlspecialchars($test_email) . "</h4>";

    $queries = [
        "Format 1" => "/rest/v1/admin?email=eq." . urlencode($test_email),
        "Format 2" => "/rest/v1/admin?email=eq." . urlencode("'" . $test_email . "'"),
        "Format 3" => "/rest/v1/admin?email=ilike." . urlencode("'" . $test_email . "'")
    ];

    foreach ($queries as $name => $query) {
        echo "<strong>$name:</strong> $query<br>";
        $result = supabase_request('GET', $query, null, true);
        echo "Status: " . ($result['status'] ?? 'NO RESPONSE') . "<br>";
        echo "Count: " . (is_array($result['body'] ?? null) ? count($result['body']) : '0') . "<br><br>";
    }
}

echo "</body></html>";
