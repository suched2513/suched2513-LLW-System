<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\SettingService;
use App\Services\AuditTrailService;

/**
 * Class SettingController
 * จัดการข้อมูลหน่วยงาน ตราสัญลักษณ์ ที่อยู่ ผู้มีอำนาจลงนาม และนโยบายควบคุมงบประมาณ
 */
class SettingController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('settings', 'view');

        $settings = SettingService::all();

        $this->render('settings/index', [
            'pageTitle' => 'ตั้งค่าระบบและข้อมูลหน่วยงาน',
            'settings'  => $settings
        ]);
    }

    public function update(): void
    {
        $this->checkPermission('settings', 'edit');

        // Fields to update
        $fields = [
            'school_name', 'school_code', 'affiliation', 'address', 'subdistrict',
            'district', 'province', 'postal_code', 'phone', 'fax', 'email', 'website', 'tax_id',
            'principal_name', 'principal_position', 'budget_deputy_name', 'finance_head_name',
            'accountant_head_name', 'inventory_head_name',
            'budget_warning_percent', 'budget_block_percent', 'vat_rate', 'withholding_tax_service_rate'
        ];

        $dataToSave = [];
        foreach ($fields as $field) {
            $dataToSave[$field] = $this->request->input($field, '');
        }

        // Handle file uploads (School Logo, Garuda Emblem)
        $uploadDir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $logoFile = $this->request->file('school_logo');
        if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($logoFile['tmp_name'], $uploadDir . $filename)) {
                    $dataToSave['school_logo_path'] = 'uploads/' . $filename;
                }
            }
        }

        $garudaFile = $this->request->file('garuda_logo');
        if ($garudaFile && $garudaFile['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($garudaFile['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                $filename = 'garuda_' . time() . '.' . $ext;
                if (move_uploaded_file($garudaFile['tmp_name'], $uploadDir . $filename)) {
                    $dataToSave['garuda_logo_path'] = 'uploads/' . $filename;
                }
            }
        }

        $success = SettingService::setMany($dataToSave, 'general');

        if ($success) {
            $this->setFlash('success', 'บันทึกการตั้งค่าระบบและข้อมูลสถานศึกษาเรียบร้อยแล้ว');
        } else {
            $this->setFlash('danger', 'เกิดข้อผิดพลาดในการบันทึกการตั้งค่า');
        }

        $this->redirect('/settings');
    }
}
