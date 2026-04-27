<?php

use App\Http\Controllers\LegacyDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard/admin/login.php');
});

// Friendly admin routes that map to legacy dashboard/admin files.
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/admin/{path?}', function (Request $request, LegacyDashboardController $legacyController, ?string $path = null) {
    $path = trim((string) $path, '/');
    $legacyPath = 'admin/' . ($path === '' ? 'login.php' : $path);

    if (pathinfo($legacyPath, PATHINFO_EXTENSION) === '') {
        $legacyPath .= '.php';
    }

    return $legacyController->serve($request, $legacyPath);
})
    ->where('path', '.*')
    ->name('legacy.admin');

// Catch-all migration adapter for the existing dashboard folder.
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/dashboard/{path?}', [LegacyDashboardController::class, 'serve'])
    ->where('path', '.*')
    ->name('legacy.dashboard');
