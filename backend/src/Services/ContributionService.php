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
     * Verify a contribution.
     *
     * If an ownership allocation is supplied, the verification and
     * ownership allocation are treated as one operation.
     */
    public function verifyContribution(
        int $contributionId,
        ?float $ownershipAmount = null
    ): array {
        if ($contributionId <= 0) {
            throw new RuntimeException('Invalid contribution ID.');
        }

        /*
         * OwnershipService currently manages its own transaction.
         * Therefore this service handles verification first and only
         * commits after the ownership operation succeeds.
         *
         * When ownership allocation is requested, the ownership
         * service must not be called inside this transaction.
         */

        $this->db->beginTransaction();

        try {
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

            if ($contribution['verification_status'] === 'verified') {
                throw new RuntimeException(
                    'Contribution has already been verified.'
                );
            }

            if ($contribution['verification_status'] === 'failed') {
                throw new RuntimeException(
                    'Failed contribution cannot be verified.'
                );
            }

            $groupId = (int) $contribution['group_id'];
            $memberId = (int) $contribution['member_id'];
            $amount = (float) $contribution['amount'];

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
             * If ownership allocation is requested, we need to perform
             * the ownership operation outside this transaction because
             * OwnershipService manages its own transaction.
             *
             * First commit the contribution verification.
             */
            $stmt = $this->db->prepare("
                UPDATE contributions
                SET
                    verification_status = 'verified',
                    verified_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$contributionId]);

            $this->db->commit();

            /*
             * Allocate ownership after successful verification.
             */
            $ownership = null;

            if ($ownershipAmount !== null && $ownershipAmount > 0) {
                try {
                    $ownership = $this->ownershipService->allocateContribution(
                        $groupId,
                        $memberId,
                        $contributionId,
                        $ownershipAmount
                    );
                } catch (\Throwable $e) {
                    /*
                     * The ownership service failed after verification.
                     *
                     * Revert the contribution to pending so the financial
                     * record does not remain falsely complete.
                     */
                    $this->revertVerification($contributionId);

                    throw $e;
                }
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
     * Revert a verification when a dependent V2 operation fails.
     */
    private function revertVerification(
        int $contributionId
    ): void {
        $stmt = $this->db->prepare("
            UPDATE contributions
            SET
                verification_status = 'pending',
                verified_at = NULL
            WHERE id = ?
              AND verification_status = 'verified'
        ");

        $stmt->execute([$contributionId]);
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
     * Get a member's contribution history.
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
