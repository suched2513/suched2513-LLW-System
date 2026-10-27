<?php

namespace App\Core;

/**
 * Class Response
 * จัดการการตอบกลับ HTTP (HTML, JSON, File Download, Redirect)
 */
class Response
{
    public static function redirect(string $url, int $statusCode = 302): void
    {
        // Support relative path with BASE_URL
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $base = defined('BASE_URL') ? BASE_URL : '';
            $url = rtrim($base, '/') . '/' . ltrim($url, '/');
        }

        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function download(string $filePath, ?string $downloadName = null, string $contentType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('ไม่พบไฟล์ที่ต้องการดาวน์โหลด');
        }

        $downloadName = $downloadName ?? basename($filePath);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public static function csv(array $data, string $filename = 'export.csv'): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel in Thai language
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public static function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized (กรุณาเข้าสู่ระบบ)',
            403 => 'Forbidden (ไม่มีสิทธิ์เข้าถึงหน้านี้)',
            404 => 'Page Not Found (ไม่พบหน้าที่ต้องการ)',
            500 => 'Internal Server Error (เกิดข้อผิดพลาดภายในระบบ)'
        ];
        $title = $titles[$code] ?? "HTTP Error $code";

        if (file_exists(__DIR__ . '/../Views/errors/error.php')) {
            $viewData = ['code' => $code, 'title' => $title, 'message' => $message];
            extract($viewData);
            require __DIR__ . '/../Views/errors/error.php';
        } else {
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$title}</title></head>";
            echo "<body style='font-family:sans-serif;text-align:center;padding:50px;'>";
            echo "<h1 style='color:#0B2545;'>{$code} - {$title}</h1>";
            if ($message) echo "<p style='color:#666;'>{$message}</p>";
            echo "<p><a href='" . (defined('BASE_URL') ? BASE_URL : '/') . "' style='color:#0B2545;font-weight:bold;'>กลับสู่หน้าหลัก</a></p>";
            echo "</body></html>";
        }
        exit;
    }
}
