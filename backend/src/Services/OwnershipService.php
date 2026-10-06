<?php

namespace App\Services;

use PDO;
use RuntimeException;

class OwnershipService
{
    private PDO $db;

    /**
     * The initial unit price used when an asset has no ownership units yet.
     *
     * This is a valuation convention, not a currency conversion.
     */
    private const INITIAL_UNIT_PRICE = 1.0000;

    /**
     * Savings Reserve asset name.
     */
    private const SAVINGS_ASSET_NAME = 'Chama Savings Reserve';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get the group's Savings Reserve asset.
     *
     * Creates it if it does not exist.
     */
    public function getSavingsAsset(int $groupId): array
    {
        if ($groupId <= 0) {
            throw new RuntimeException('Invalid group ID.');
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM assets
            WHERE group_id = ?
              AND type = 'savings'
              AND name = ?
            LIMIT 1
        ");

        $stmt->execute([
            $groupId,
            self::SAVINGS_ASSET_NAME
        ]);

        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($asset) {
            return $asset;
        }

        $stmt = $this->db->prepare("
            INSERT INTO assets (
                group_id,
                name,
                type,
                purchase_price,
                current_value,
                description
            )
            VALUES (
                ?,
                ?,
                'savings',
                0,
                0,
                'Group savings reserve'
            )
            RETURNING *
        ");

        $stmt->execute([
            $groupId,
            self::SAVINGS_ASSET_NAME
        ]);

        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$asset) {
            throw new RuntimeException(
                'Unable to create Savings Reserve asset.'
            );
        }

        return $asset;
    }

    /**
     * Calculate the current total ownership units for an asset.
     */
    public function getTotalUnits(int $assetId): float
    {
        if ($assetId <= 0) {
            throw new RuntimeException('Invalid asset ID.');
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(units), 0)
            FROM ownership_units
            WHERE asset_id = ?
        ");

        $stmt->execute([$assetId]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Calculate the current unit price for an asset.
     *
     * Unit Price = Current NAV / Total Units
     *
     * If the asset has no units yet, the initial unit price is 1.0000.
     */
    public function getUnitPrice(int $assetId): float
    {
        if ($assetId <= 0) {
            throw new RuntimeException('Invalid asset ID.');
        }

        $stmt = $this->db->prepare("
            SELECT current_value
            FROM assets
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$assetId]);

        $currentValue = $stmt->fetchColumn();

        if ($currentValue === false) {
            throw new RuntimeException('Asset not found.');
        }

        $currentValue = (float) $currentValue;
        $totalUnits = $this->getTotalUnits($assetId);

        if ($totalUnits <= 0) {
            return self::INITIAL_UNIT_PRICE;
        }

        return $currentValue / $totalUnits;
    }

    /**
     * Allocate an explicitly specified amount of a verified contribution
     * into ownership units.
     *
     * This method deliberately does NOT assume that every contribution
     * contains a fixed KSh 500 ownership allocation.
     *
     * Example:
     *
     * Contribution = KSh 3,500
     * Ownership allocation = KSh 500
     *
     * The caller decides that KSh 500 is eligible for ownership.
     */
    public function allocateContribution(
        int $groupId,
        int $memberId,
        int $contributionId,
        float $ownershipAmount
    ): array {
        if ($groupId <= 0) {
            throw new RuntimeException('Invalid group ID.');
        }

        if ($memberId <= 0) {
            throw new RuntimeException('Invalid member ID.');
        }

        if ($contributionId <= 0) {
            throw new RuntimeException('Invalid contribution ID.');
        }

        if ($ownershipAmount <= 0) {
            throw new RuntimeException(
                'Ownership allocation amount must be greater than zero.'
            );
        }

        $this->db->beginTransaction();

        try {
            /*
             * 1. Verify that the contribution exists and belongs
             *    to the specified group/member.
             */
            $stmt = $this->db->prepare("
                SELECT
                    id,
                    group_id,
                    member_id,
                    amount,
                    verification_status
                FROM contributions
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([$contributionId]);

            $contribution = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contribution) {
                throw new RuntimeException(
                    'Contribution not found.'
                );
            }

            if ((int) $contribution['group_id'] !== $groupId) {
                throw new RuntimeException(
                    'Contribution does not belong to this group.'
                );
            }

            if ((int) $contribution['member_id'] !== $memberId) {
                throw new RuntimeException(
                    'Contribution does not belong to this member.'
                );
            }

            if ($contribution['verification_status'] !== 'verified') {
                throw new RuntimeException(
                    'Only verified contributions can generate ownership units.'
                );
            }

            $contributionAmount = (float) $contribution['amount'];

            if ($ownershipAmount > $contributionAmount) {
                throw new RuntimeException(
                    'Ownership allocation cannot exceed contribution amount.'
                );
            }

            /*
             * 2. Prevent duplicate allocation.
             */
            $stmt = $this->db->prepare("
                SELECT
                    id,
                    units,
                    unit_price_at_purchase
                FROM ownership_units
                WHERE contribution_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([$contributionId]);

            $existingOwnership = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingOwnership) {
                throw new RuntimeException(
                    'This contribution has already generated ownership units.'
                );
            }

            /*
             * 3. Get the group's Savings Reserve.
             */
            $asset = $this->getSavingsAsset($groupId);

            $assetId = (int) $asset['id'];

            /*
             * 4. Lock the asset while calculating NAV/unit price.
             */
            $stmt = $this->db->prepare("
                SELECT
                    id,
                    group_id,
                    current_value
                FROM assets
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$assetId]);

            $lockedAsset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedAsset) {
                throw new RuntimeException(
                    'Savings Reserve asset not found.'
                );
            }

            /*
             * 5. Calculate current unit price.
             *
             * Existing:
             *   NAV = current asset value
             *   Units = existing ownership units
             *
             * New ownership receives units at the current price.
             */
            $currentValue = (float) $lockedAsset['current_value'];

            $totalUnits = $this->getTotalUnits($assetId);

            if ($totalUnits <= 0) {
                $unitPrice = self::INITIAL_UNIT_PRICE;
            } else {
                if ($currentValue <= 0) {
                    throw new RuntimeException(
                        'Cannot calculate unit price from zero NAV with existing units.'
                    );
                }

                $unitPrice = $currentValue / $totalUnits;
            }

            if ($unitPrice <= 0) {
                throw new RuntimeException(
                    'Calculated unit price is invalid.'
                );
            }

            /*
             * 6. Calculate new ownership units.
             *
             * Units = Ownership Allocation / Unit Price
             */
            $units = $ownershipAmount / $unitPrice;

            if ($units <= 0) {
                throw new RuntimeException(
                    'Calculated ownership units are invalid.'
                );
            }

            /*
             * 7. Insert ownership record.
             */
            $stmt = $this->db->prepare("
                INSERT INTO ownership_units (
                    group_id,
                    member_id,
                    asset_id,
                    contribution_id,
                    units,
                    unit_price_at_purchase
                )
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING *
            ");

            $stmt->execute([
                $groupId,
                $memberId,
                $assetId,
                $contributionId,
                $units,
                $unitPrice
            ]);

            $ownership = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ownership) {
                throw new RuntimeException(
                    'Unable to create ownership record.'
                );
            }

            /*
             * 8. Increase the asset NAV by the ownership allocation.
             */
            $newAssetValue = $currentValue + $ownershipAmount;

            $stmt = $this->db->prepare("
                UPDATE assets
                SET
                    current_value = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $newAssetValue,
                $assetId
            ]);

            /*
             * 9. Commit the complete ownership operation.
             */
            $this->db->commit();

            /*
             * 10. Return useful calculation information.
             */
            $newTotalUnits = $totalUnits + $units;
            $newUnitPrice = $newTotalUnits > 0
                ? $newAssetValue / $newTotalUnits
                : self::INITIAL_UNIT_PRICE;

            return [
                'ownership' => $ownership,
                'asset_id' => $assetId,
                'ownership_amount' => $ownershipAmount,
                'units_created' => $units,
                'unit_price_at_purchase' => $unitPrice,
                'asset_value_before' => $currentValue,
                'asset_value_after' => $newAssetValue,
                'total_units_after' => $newTotalUnits,
                'unit_price_after' => $newUnitPrice
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Get a member's total ownership units in a group.
     */
    public function getMemberUnits(
        int $groupId,
        int $memberId
    ): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(units), 0)
            FROM ownership_units
            WHERE group_id = ?
              AND member_id = ?
        ");

        $stmt->execute([
            $groupId,
            $memberId
        ]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Get a member's ownership percentage in a group.
     *
     * Ownership % =
     * Member Units / Total Group Units × 100
     */
    public function getMemberOwnershipPercentage(
        int $groupId,
        int $memberId
    ): float {
        $memberUnits = $this->getMemberUnits(
            $groupId,
            $memberId
        );

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(units), 0)
            FROM ownership_units
            WHERE group_id = ?
        ");

        $stmt->execute([$groupId]);

        $totalUnits = (float) $stmt->fetchColumn();

        if ($totalUnits <= 0) {
            return 0.0;
        }

        return ($memberUnits / $totalUnits) * 100;
    }

    /**
     * Get the current value of a member's ownership
     * in a specific asset.
     */
    public function getMemberAssetValue(
        int $assetId,
        int $memberId
    ): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(units), 0)
            FROM ownership_units
            WHERE asset_id = ?
              AND member_id = ?
        ");

        $stmt->execute([
            $assetId,
            $memberId
        ]);

        $memberUnits = (float) $stmt->fetchColumn();

        if ($memberUnits <= 0) {
            return 0.0;
        }

        $unitPrice = $this->getUnitPrice($assetId);

        return $memberUnits * $unitPrice;
    }

    /**
     * Get a complete ownership summary for a member.
     */
    public function getMemberSummary(
        int $groupId,
        int $memberId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(ou.units), 0) AS member_units,
                COALESCE(SUM(a.current_value), 0) AS asset_values
            FROM ownership_units ou
            INNER JOIN assets a
                ON a.id = ou.asset_id
            WHERE ou.group_id = ?
              AND ou.member_id = ?
        ");

        $stmt->execute([
            $groupId,
            $memberId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $memberUnits = (float) ($row['member_units'] ?? 0);

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(units), 0)
            FROM ownership_units
            WHERE group_id = ?
        ");

        $stmt->execute([$groupId]);

        $totalUnits = (float) $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(current_value), 0)
            FROM assets
            WHERE group_id = ?
        ");

        $stmt->execute([$groupId]);

        $totalNav = (float) $stmt->fetchColumn();

        $ownershipPercentage = $totalUnits > 0
            ? ($memberUnits / $totalUnits) * 100
            : 0.0;

        $currentValue = $totalUnits > 0
            ? $memberUnits * ($totalNav / $totalUnits)
            : 0.0;

        $unitPrice = $totalUnits > 0
            ? $totalNav / $totalUnits
            : self::INITIAL_UNIT_PRICE;

        return [
            'group_id' => $groupId,
            'member_id' => $memberId,
            'member_units' => $memberUnits,
            'total_units' => $totalUnits,
            'total_nav' => $totalNav,
            'unit_price' => $unitPrice,
            'ownership_percentage' => $ownershipPercentage,
            'current_value' => $currentValue
        ];
    }
}
