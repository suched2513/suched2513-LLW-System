<?php

namespace App\Core;

/**
 * Class View
 * ตัวเรนเดอร์ View Template พร้อม Layout และ Global Variables
 */
class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $viewPath, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $mergedData = array_merge(self::$shared, $data);
        extract($mergedData);

        // หา path ของ view template
        $viewFile = __DIR__ . '/../Views/' . str_replace('.', '/', $viewPath) . '.php';
        if (!file_exists($viewFile)) {
            Response::abort(500, "ไม่พบ View Template: {$viewPath}");
        }

        // จับ Output Buffer ของ View Content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // เรนเดอร์ครอบด้วย Layout
        if ($layout) {
            $layoutFile = __DIR__ . '/../Views/' . str_replace('.', '/', $layout) . '.php';
            if (!file_exists($layoutFile)) {
                Response::abort(500, "ไม่พบ Layout Template: {$layout}");
            }
            require $layoutFile;
        } else {
            echo $content;
        }
        exit;
    }
}
