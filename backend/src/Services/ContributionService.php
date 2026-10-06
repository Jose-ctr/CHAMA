<?php

namespace App\Services;

use PDO;
use RuntimeException;

class ContributionService
{
    private PDO $db;
    private OwnershipService $ownershipService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ownershipService = new OwnershipService($db);
    }

    /**
     * Verify a contribution and optionally allocate an explicit
     * portion of that contribution to MALI CHAMA ownership.
     *
     * Ownership allocation is optional so existing V1 contributions
     * continue to work normally.
     */
    public function verifyContribution(
        int $contributionId,
        ?float $ownershipAmount = null
    ): array {
        if ($contributionId <= 0) {
            throw new RuntimeException('Invalid contribution ID.');
        }

        $this->db->beginTransaction();

        try {
            /*
             * Lock the contribution so two verification requests
             * cannot process it simultaneously.
             */
            $stmt = $this->db->prepare("
                SELECT
                    id,
                    group_id,
                    member_id,
                    amount,
                    contribution_month,
                    mpesa_code,
                    payment_method,
                    verification_status,
                    verified_at,
                    created_at
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

            /*
             * Already verified contributions are not processed again.
             */
            if ($contribution['verification_status'] === 'verified') {
                throw new RuntimeException(
                    'Contribution has already been verified.'
                );
            }

            /*
             * Failed contributions cannot be verified through
             * this operation without being explicitly reset first.
             */
            if ($contribution['verification_status'] === 'failed') {
                throw new RuntimeException(
                    'Failed contribution cannot be verified.'
                );
            }

            $groupId = (int) $contribution['group_id'];
            $memberId = (int) $contribution['member_id'];
            $amount = (float) $contribution['amount'];

            /*
             * Validate optional ownership allocation.
             */
            if ($ownershipAmount !== null) {
                if ($ownershipAmount <= 0) {
                    throw new RuntimeException(
                        'Ownership allocation must be greater than zero.'
                    );
                }

                if ($ownershipAmount > $amount) {
                    throw new RuntimeException(
                        'Ownership allocation cannot exceed contribution amount.'
                    );
                }
            }

            /*
             * Mark contribution as verified.
             */
            $stmt = $this->db->prepare("
                UPDATE contributions
                SET
                    verification_status = 'verified',
                    verified_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$contributionId]);

            /*
             * Allocate ownership only when explicitly requested.
             *
             * We temporarily commit the verification before calling
             * OwnershipService because OwnershipService manages its
             * own transaction.
             */
            $this->db->commit();

            /*
             * MALI CHAMA V2 ownership allocation.
             */
            $ownership = null;

            if ($ownershipAmount !== null && $ownershipAmount > 0) {
                $ownership = $this->ownershipService->allocateContribution(
                    $groupId,
                    $memberId,
                    $contributionId,
                    $ownershipAmount
                );
            }

            return [
                'contribution' => $contribution,
                'verification_status' => 'verified',
                'verified_at' => date('c'),
                'ownership' => $ownership
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Get a single contribution.
     */
    public function getContribution(
        int $contributionId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                group_id,
                member_id,
                amount,
                contribution_month,
                mpesa_code,
                payment_method,
                verification_status,
                verified_at,
                created_at
            FROM contributions
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$contributionId]);

        $contribution = $stmt->fetch(PDO::FETCH_ASSOC);

        return $contribution ?: null;
    }

    /**
     * Get contributions for a group.
     */
    public function getGroupContributions(
        int $groupId
    ): array {
        if ($groupId <= 0) {
            throw new RuntimeException('Invalid group ID.');
        }

        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.group_id,
                c.member_id,
                m.name AS member_name,
                m.phone AS member_phone,
                c.amount,
                c.contribution_month,
                c.mpesa_code,
                c.payment_method,
                c.verification_status,
                c.verified_at,
                c.created_at
            FROM contributions c
            INNER JOIN members m
                ON m.id = c.member_id
            WHERE c.group_id = ?
            ORDER BY
                c.contribution_month DESC,
                c.created_at DESC
        ");

        $stmt->execute([$groupId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a member's contribution history in a group.
     */
    public function getMemberContributions(
        int $groupId,
        int $memberId
    ): array {
        if ($groupId <= 0 || $memberId <= 0) {
            throw new RuntimeException(
                'Invalid group or member ID.'
            );
        }

        $stmt = $this->db->prepare("
            SELECT
                id,
                group_id,
                member_id,
                amount,
                contribution_month,
                mpesa_code,
                payment_method,
                verification_status,
                verified_at,
                created_at
            FROM contributions
            WHERE group_id = ?
              AND member_id = ?
            ORDER BY
                contribution_month DESC,
                created_at DESC
        ");

        $stmt->execute([
            $groupId,
            $memberId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total verified contributions for a member.
     */
    public function getMemberVerifiedTotal(
        int $groupId,
        int $memberId
    ): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM contributions
            WHERE group_id = ?
              AND member_id = ?
              AND verification_status = 'verified'
        ");

        $stmt->execute([
            $groupId,
            $memberId
        ]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Get total verified contributions for a group.
     */
    public function getGroupVerifiedTotal(
        int $groupId
    ): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM contributions
            WHERE group_id = ?
              AND verification_status = 'verified'
        ");

        $stmt->execute([$groupId]);

        return (float) $stmt->fetchColumn();
    }
}
