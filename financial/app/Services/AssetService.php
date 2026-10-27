<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Exception;

/**
 * Class AssetService
 * จัดการพัสดุสิ้นเปลือง ทะเบียนคุมครุภัณฑ์ และการคำนวณค่าเสื่อมราคาแบบเส้นตรง (Straight-Line Depreciation)
 */
class AssetService
{
    /**
     * คำนวณค่าเสื่อมราคาครุภัณฑ์แบบเส้นตรงตามเกณฑ์กรมบัญชีกลาง
     * มูลค่าซาก (Residual Value) = 1.00 บาท
     * ค่าเสื่อมราคาต่อปี = (ราคาทุน - 1.00) / อายุการใช้งาน
     */
    public static function calculateDepreciation(array $asset, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? date('Y-m-d');
        $cost = (float)$asset['cost_amount'];
        $usefulLife = (int)($asset['useful_life_years'] ?? 5);
        if ($usefulLife <= 0) $usefulLife = 5;

        $acquiredDate = $asset['acquired_date'];
        $d1 = new \DateTime($acquiredDate);
        $d2 = new \DateTime($asOfDate);

        if ($d2 < $d1) {
            $yearsElapsed = 0.0;
        } else {
            $diff = $d1->diff($d2);
            $yearsElapsed = (float)$diff->y + ((float)$diff->m / 12.0) + ((float)$diff->d / 365.0);
        }

        $depreciableAmount = max(0.00, $cost - 1.00); // หักมูลค่าซาก 1 บาท
        $annualDepreciation = $depreciableAmount / $usefulLife;

        // ค่าเสื่อมราคาสะสม (ไม่เกินราคาทุน - 1)
        $accumulatedDepreciation = min($depreciableAmount, $annualDepreciation * $yearsElapsed);
        $accumulatedDepreciation = round($accumulatedDepreciation, 2);

        // มูลค่าสุทธิตามบัญชี (Net Book Value) ขั้นต่ำ 1.00 บาท
        $netBookValue = max(1.00, round($cost - $accumulatedDepreciation, 2));

        return [
            'cost_amount'              => $cost,
            'useful_life_years'        => $usefulLife,
            'annual_depreciation'      => round($annualDepreciation, 2),
            'years_elapsed'            => round($yearsElapsed, 2),
            'accumulated_depreciation' => $accumulatedDepreciation,
            'net_book_value'           => $netBookValue,
            'is_fully_depreciated'     => ($netBookValue <= 1.00)
        ];
    }

    public static function getAssets(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT a.*, u.full_name as custodian_name
            FROM fin_assets a
            LEFT JOIN fin_users u ON a.custodian_user_id = u.id
            ORDER BY a.asset_code ASC
        ");
        $assets = $stmt->fetchAll();

        // Calculate current depreciation for each asset
        $calculatedAssets = [];
        foreach ($assets as $ast) {
            $dep = self::calculateDepreciation($ast);
            $calculatedAssets[] = array_merge($ast, $dep);
        }
        return $calculatedAssets;
    }

    public static function createAsset(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO fin_assets 
            (asset_code, asset_name, category, acquired_date, cost_amount, useful_life_years, depreciation_rate, current_location, custodian_user_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_use')
        ");
        $usefulLife = (int)($data['useful_life_years'] ?? 5);
        $rate = $usefulLife > 0 ? (100 / $usefulLife) : 20.00;

        $stmt->execute([
            trim($data['asset_code']),
            trim($data['asset_name']),
            trim($data['category'] ?? ''),
            $data['acquired_date'] ?? date('Y-m-d'),
            (float)$data['cost_amount'],
            $usefulLife,
            $rate,
            trim($data['current_location'] ?? ''),
            !empty($data['custodian_user_id']) ? (int)$data['custodian_user_id'] : null
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function getInventory(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query("SELECT * FROM fin_inventory_items ORDER BY item_code ASC")->fetchAll();
    }

    public static function recordStockMovement(int $itemId, string $type, float $qty, float $unitCost, string $refDoc, int $userId): int
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO fin_stock_movements (inventory_item_id, movement_date, movement_type, quantity, unit_cost, reference_doc, created_by)
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$itemId, $type, $qty, $unitCost, $refDoc, $userId]);
            $moveId = (int)$pdo->lastInsertId();

            if ($type === 'in') {
                $pdo->prepare("UPDATE fin_inventory_items SET current_stock = current_stock + ? WHERE id = ?")->execute([$qty, $itemId]);
            } elseif ($type === 'out') {
                $pdo->prepare("UPDATE fin_inventory_items SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?")->execute([$qty, $itemId]);
            }

            $pdo->commit();
            return $moveId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
