<?php

namespace App\Core;

use App\Services\AuthService;
use App\Services\AuditTrailService;

/**
 * Class Controller
 * Base Controller สำหรับ Controller ทุกตัวในระบบ
 */
abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function render(string $viewPath, array $data = [], ?string $layout = 'layouts/main'): void
    {
        // Inject current user, flash messages, and CSRF token automatically
        $data['currentUser'] = AuthService::getCurrentUser();
        $data['csrfToken'] = AuthService::getCsrfToken();
        $data['activeFiscalYear'] = AuthService::getActiveFiscalYear();
        $data['flash'] = $this->getFlash();

        View::render($viewPath, $data, $layout);
    }

    protected function json(mixed $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function setFlash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION['flash'] = [
            'type' => $type, // 'success', 'danger', 'warning', 'info'
            'message' => $message
        ];
    }

    protected function getFlash(): ?array
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    protected function user(): ?array
    {
        return AuthService::getCurrentUser();
    }

    protected function checkPermission(string $module, string $action): void
    {
        if (!AuthService::hasPermission($module, $action)) {
            if ($this->request->isAjax()) {
                $this->json(['error' => 'คุณไม่มีสิทธิ์ในการกระทำนี้'], 403);
            }
            Response::abort(403, "ท่านไม่มีสิทธิ์ในการเข้าถึงโมดูล {$module} สำหรับการกระทำ {$action}");
        }
    }
}
