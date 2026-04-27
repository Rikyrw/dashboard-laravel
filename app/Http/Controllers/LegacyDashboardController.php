<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class LegacyDashboardController extends Controller
{
    private string $legacyRoot;
    private const LEGACY_SESSION_NAME = 'PHPSESSID';
    private const SESSION_ID_PATTERN = '/^[A-Za-z0-9,-]{1,128}$/';

    public function __construct()
    {
        $configured = config('legacy_dashboard.root_path', base_path('dashboard'));
        if (!is_dir($configured)) {
            $configured = base_path('../dashboard');
        }
        $resolved = realpath($configured);
        $this->legacyRoot = $resolved !== false ? $resolved : $configured;
    }

    public function serve(Request $request, ?string $path = null): Response
    {
        $path = trim((string) $path, '/');

        if ($path === '') {
            return response('', 302)->header('Location', '/dashboard/admin/login.php');
        }

        $target = $this->resolvePath($path);

        // Convenience fallback: allow route without extension if matching PHP file exists.
        if ($target === null && pathinfo($path, PATHINFO_EXTENSION) === '') {
            $target = $this->resolvePath($path . '.php');
        }

        if ($target === null || !is_file($target)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));

        if ($extension !== 'php') {
            return response()->file($target);
        }

        return $this->executeLegacyPhp($target, $path);
    }

    private function resolvePath(string $path): ?string
    {
        if (str_contains($path, "\0")) {
            return null;
        }

        $fullPath = $this->legacyRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $realPath = realpath($fullPath);

        if ($realPath === false) {
            return null;
        }

        $rootNormalized = rtrim(str_replace('\\', '/', $this->legacyRoot), '/');
        $realNormalized = str_replace('\\', '/', $realPath);

        if ($realNormalized !== $rootNormalized && !str_starts_with($realNormalized, $rootNormalized . '/')) {
            return null;
        }

        return $realPath;
    }

    private function executeLegacyPhp(string $target, string $routePath): Response
    {
        $originalCwd = getcwd();
        $sessionState = $this->prepareLegacySession();

        $serverBackup = [
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
            'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        ];

        $_SERVER['SCRIPT_FILENAME'] = $target;
        $_SERVER['SCRIPT_NAME'] = '/dashboard/' . ltrim($routePath, '/');
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

        header_remove();
        http_response_code(200);

        ob_start();

        try {
            chdir(dirname($target));
            include $target;
            $content = ob_get_clean() ?: '';
        } finally {
            $this->restoreLegacySession($sessionState);

            if ($originalCwd !== false) {
                chdir($originalCwd);
            }

            foreach ($serverBackup as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }

        $status = http_response_code();
        $status = is_int($status) && $status >= 100 ? $status : 200;
        $response = response($content, $status);

        foreach (headers_list() as $headerLine) {
            if (!str_contains($headerLine, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $headerLine, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '' || $value === '') {
                continue;
            }

            if (strtolower($name) === 'set-cookie') {
                try {
                    $response->headers->setCookie(Cookie::fromString($value));
                } catch (\Throwable $e) {
                    // Ignore malformed cookie header from legacy scripts.
                }
                continue;
            }

            $response->headers->set($name, $value, false);
        }

        header_remove();

        return $response;
    }

    private function prepareLegacySession(): array
    {
        $state = [
            'name' => session_name(),
            'id' => session_id(),
            'active' => session_status() === PHP_SESSION_ACTIVE,
        ];

        if ($state['active']) {
            @session_write_close();
        }

        @session_name(self::LEGACY_SESSION_NAME);
        $this->clearInvalidSessionId(self::LEGACY_SESSION_NAME);

        return $state;
    }

    private function restoreLegacySession(array $state): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        if (!empty($state['name']) && $state['name'] !== session_name()) {
            @session_name((string) $state['name']);
        }

        $id = (string) ($state['id'] ?? '');
        if ($id !== '' && preg_match(self::SESSION_ID_PATTERN, $id) === 1) {
            @session_id($id);
        }
    }

    private function clearInvalidSessionId(string $sessionName): void
    {
        $cookieValue = $_COOKIE[$sessionName] ?? '';
        if (is_string($cookieValue) && $cookieValue !== '' && preg_match(self::SESSION_ID_PATTERN, $cookieValue) !== 1) {
            unset($_COOKIE[$sessionName]);

            // Expire malformed cookie so browser does not keep sending it.
            setcookie($sessionName, '', time() - 3600, '/');
        }

        foreach (['_GET', '_POST'] as $sourceName) {
            if (!isset($GLOBALS[$sourceName]) || !is_array($GLOBALS[$sourceName])) {
                continue;
            }

            $value = $GLOBALS[$sourceName][$sessionName] ?? '';
            if (is_string($value) && $value !== '' && preg_match(self::SESSION_ID_PATTERN, $value) !== 1) {
                unset($GLOBALS[$sourceName][$sessionName]);
            }
        }
    }
}
