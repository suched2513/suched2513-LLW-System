<?php
/**
 * โรงเรียนละลมวิทยา - ระบบบริหารการเงินและบัญชีสถานศึกษา
 * Root Entry Point (Redirect / Forward to public/index.php)
 */

declare(strict_types=1);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo "<div style='font-family:sans-serif;padding:30px;background:#fff5f5;border:2px solid #e53e3e;border-radius:8px;max-width:800px;margin:50px auto;'>";
        echo "<h2 style='color:#c53030;margin-top:0;'>PHP Fatal Error in Financial System</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($err['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($err['file']) . "</p>";
        echo "<p><strong>Line:</strong> " . $err['line'] . "</p>";
        echo "</div>";
    }
});

set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo "<div style='font-family:sans-serif;padding:30px;background:#fff5f5;border:2px solid #e53e3e;border-radius:8px;max-width:800px;margin:50px auto;'>";
    echo "<h2 style='color:#c53030;margin-top:0;'>Uncaught Exception in Financial System</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</p>";
    echo "<pre style='background:#f7fafc;padding:15px;border:1px solid #e2e8f0;border-radius:4px;overflow-x:auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
});

require_once __DIR__ . '/public/index.php';
