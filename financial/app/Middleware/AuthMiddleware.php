<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Class AuthMiddleware
 * ตรวจสอบว่าผู้ใช้ผ่านการเข้าสู่ระบบแล้วหรือไม่
 */
class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!AuthService::check()) {
            if ($request->isAjax()) {
                Response::json(['error' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ'], 401);
            }
            Response::redirect('/login');
        }
    }
}
