<?php

declare(strict_types=1);

namespace App\Services;

use Chama\Config\Database;
use PDO;
use RuntimeException;

final class AssetService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function getGroupAssets(int $groupId): array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM assets
             WHERE group_id = :group_id
             ORDER BY created_at DESC, id DESC'
        );

        $stmt->execute([
            'group_id' => $groupId,
        ]);

        return $stmt->fetchAll();
    }

    public function getAsset(int $assetId): array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM assets
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $assetId,
        ]);

        $asset = $stmt->fetch();

        if ($asset === false) {
            throw new RuntimeException('Asset not found.');
        }

        return $asset;
    }

    public function createAsset(
        int $groupId,
        string $name,
        string $type,
        float $purchasePrice,
        float $currentValue,
        ?string $description = null,
        ?string $purchaseDate = null
    ): array {
        $name = trim($name);
        $type = trim($type);

        if ($name === '') {
            throw new RuntimeException('Asset name is required.');
        }

        if ($purchasePrice < 0 || $currentValue < 0) {
            throw new RuntimeException(
                'Asset values cannot be negative.'
            );
        }

        $stmt = $this->db->prepare(
            'INSERT INTO assets (
                group_id,
                name,
                type,
                purchase_price,
                current_value,
                description,
                purchase_date
             )
             VALUES (
                :group_id,
                :name,
                :type,
                :purchase_price,
                :current_value,
                :description,
                COALESCE(:purchase_date, CURRENT_DATE)
             )
             RETURNING *'
        );

        $stmt->execute([
            'group_id' => $groupId,
            'name' => $name,
            'type' => $type,
            'purchase_price' => $purchasePrice,
            'current_value' => $currentValue,
            'description' => $description,
            'purchase_date' => $purchaseDate,
        ]);

        $asset = $stmt->fetch();

        if ($asset === false) {
            throw new RuntimeException('Failed to create asset.');
        }

        return $asset;
    }
    public function updateAsset(
        int $assetId,
        string $name,
        string $type,
        float $purchasePrice,
        float $currentValue,
        ?string $description = null,
        ?string $purchaseDate = null
    ): array {
        $name = trim($name);
        $type = trim($type);

        if ($name === "") {
            throw new RuntimeException("Asset name is required.");
        }

        if ($purchasePrice < 0 || $currentValue < 0) {
            throw new RuntimeException("Asset values cannot be negative.");
        }

        $stmt = $this->db->prepare(
            "UPDATE assets
             SET name = :name,
                 type = :type,
                 purchase_price = :purchase_price,
                 current_value = :current_value,
                 description = :description,
                 purchase_date = COALESCE(:purchase_date, purchase_date),
                 updated_at = NOW()
             WHERE id = :id
             RETURNING *"
        );

        $stmt->execute([
            "id" => $assetId,
            "name" => $name,
            "type" => $type,
            "purchase_price" => $purchasePrice,
            "current_value" => $currentValue,
            "description" => $description,
            "purchase_date" => $purchaseDate,
        ]);

        $asset = $stmt->fetch();

        if ($asset === false) {
            throw new RuntimeException("Asset not found.");
        }

        return $asset;
    }

}
