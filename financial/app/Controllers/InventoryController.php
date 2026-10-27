<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AssetService;
use App\Services\AuthService;
use PDO;

/**
 * Class InventoryController
 * จัดการวัสดุสิ้นเปลือง ทะเบียนคุมครุภัณฑ์ และค่าเสื่อมราคาสะสม
 */
class InventoryController extends Controller
{
    public function index(): void
    {
        $this->checkPermission('inventory', 'view');

        $inventory = AssetService::getInventory();
        $assets = AssetService::getAssets();

        $pdo = Database::getConnection();
        $users = $pdo->query("SELECT id, full_name FROM fin_users WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll();

        $this->render('inventory/index', [
            'pageTitle' => 'ระบบพัสดุ ทะเบียนคุมครุภัณฑ์ และค่าเสื่อมราคา',
            'inventory' => $inventory,
            'assets'    => $assets,
            'users'     => $users
        ]);
    }

    public function storeAsset(): void
    {
        $this->checkPermission('inventory', 'create');

        $data = [
            'asset_code'        => $this->request->input('asset_code'),
            'asset_name'        => $this->request->input('asset_name'),
            'category'          => $this->request->input('category'),
            'acquired_date'     => $this->request->input('acquired_date', date('Y-m-d')),
            'cost_amount'       => (float)$this->request->input('cost_amount'),
            'useful_life_years' => (int)$this->request->input('useful_life_years', 5),
            'current_location'  => $this->request->input('current_location'),
            'custodian_user_id' => $this->request->input('custodian_user_id')
        ];

        if (empty($data['asset_code']) || empty($data['asset_name']) || $data['cost_amount'] <= 0) {
            $this->setFlash('danger', 'กรุณากรอกข้อมูลครุภัณฑ์และราคาทุนให้ครบถ้วน');
            $this->redirect('/inventory');
        }

        try {
            AssetService::createAsset($data);
            $this->setFlash('success', "ขึ้นทะเบียนครุภัณฑ์ '{$data['asset_name']}' เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/inventory');
    }

    public function storeMovement(): void
    {
        $this->checkPermission('inventory', 'edit');
        $user = $this->user();

        $itemId = (int)$this->request->input('inventory_item_id');
        $type = (string)$this->request->input('movement_type', 'in');
        $qty = (float)$this->request->input('quantity');
        $unitCost = (float)$this->request->input('unit_cost', 0);
        $refDoc = (string)$this->request->input('reference_doc', '');

        if ($itemId <= 0 || $qty <= 0) {
            $this->setFlash('danger', 'กรุณาเลือกรายการวัสดุและจำนวนให้ถูกต้อง');
            $this->redirect('/inventory');
        }

        try {
            AssetService::recordStockMovement($itemId, $type, $qty, $unitCost, $refDoc, (int)$user['id']);
            $this->setFlash('success', 'บันทึกการรับเข้า/เบิกจ่ายวัสดุเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        $this->redirect('/inventory');
    }
}
