<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Class RbacMiddleware
 * ตรวจสอบสิทธิ์การเข้าถึงระดับโมดูลและการกระทำ (Module & Action RBAC)
 */
class RbacMiddleware
{
    public function handle(Request $request, string $module = '', string $action = 'view'): void
    {
        if (!AuthService::check()) {
            Response::redirect('/login');
        }

        if (empty($module)) {
            return;
        }

        if (!AuthService::hasPermission($module, $action)) {
            if ($request->isAjax()) {
                Response::json([
                    'error' => 'ไม่มีสิทธิ์ในการเข้าถึงหรือดำเนินการในส่วนนี้',
                    'module' => $module,
                    'action' => $action
                ], 403);
            }
            Response::abort(403, "ท่านไม่มีสิทธิ์ในการเข้าถึงโมดูล [{$module}] หรือการกระทำ [{$action}]");
        }
    }
}
