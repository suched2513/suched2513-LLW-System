<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Class CsrfMiddleware
 * ป้องกันการโจมตีแบบ Cross-Site Request Forgery (CSRF) สำหรับคำขอ POST
 */
class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        if ($request->isPost()) {
            $token = $request->input('_token') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!AuthService::validateCsrfToken($token)) {
                if ($request->isAjax()) {
                    Response::json(['error' => 'CSRF token mismatch หรือหมดอายุ กรุณารีเฟรชหน้าจอแล้วลองใหม่อีกครั้ง'], 419);
                }
                Response::abort(403, 'CSRF token validation failed (โทเค็นความปลอดภัยไม่ถูกต้องหรือเซสชันหมดอายุ กรุณากดรีเฟรชหน้าเว็บ)');
            }
        }
    }
}
