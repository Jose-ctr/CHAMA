-- ============================================================
-- CHAMA
-- V1.0 MVP Database Schema
-- PostgreSQL 14+
--
-- Sales pitch:
-- Track contributions, see savings, and know whose turn is next.
-- ============================================================

BEGIN;

-- ============================================================
-- GROUPS
-- ============================================================

CREATE TABLE IF NOT EXISTS groups (
    id SERIAL PRIMARY KEY,

    name VARCHAR(150) NOT NULL,

    target_amount NUMERIC(12, 2) NOT NULL DEFAULT 0,

    contribution_amount NUMERIC(12, 2) NOT NULL,

    cycle_months INTEGER NOT NULL DEFAULT 1,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT groups_target_amount_positive
        CHECK (target_amount >= 0),

    CONSTRAINT groups_contribution_amount_positive
        CHECK (contribution_amount > 0),

    CONSTRAINT groups_cycle_months_positive
        CHECK (cycle_months > 0)
);

-- ============================================================
-- MEMBERS
-- ============================================================

CREATE TABLE IF NOT EXISTS members (
    id SERIAL PRIMARY KEY,

    name VARCHAR(120) NOT NULL,

    phone VARCHAR(20) NOT NULL UNIQUE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ============================================================
-- GROUP MEMBERS
-- ============================================================

CREATE TABLE IF NOT EXISTS group_members (
    id SERIAL PRIMARY KEY,

    group_id INTEGER NOT NULL,

    member_id INTEGER NOT NULL,

    rotation_position INTEGER NOT NULL,

    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_group_members_group
        FOREIGN KEY (group_id)
        REFERENCES groups(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_group_members_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE,

    CONSTRAINT group_members_position_positive
        CHECK (rotation_position > 0),

    CONSTRAINT group_members_unique_member
        UNIQUE (group_id, member_id),

    CONSTRAINT group_members_unique_position
        UNIQUE (group_id, rotation_position)
);

-- ============================================================
-- CONTRIBUTIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS contributions (
    id SERIAL PRIMARY KEY,

    group_id INTEGER NOT NULL,

    member_id INTEGER NOT NULL,

    amount NUMERIC(12, 2) NOT NULL,

    contribution_month DATE NOT NULL,

    mpesa_code VARCHAR(50),

    payment_method VARCHAR(20) NOT NULL DEFAULT 'mpesa',

    verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',

    verified_at TIMESTAMPTZ,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_contributions_group
        FOREIGN KEY (group_id)
        REFERENCES groups(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_contributions_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE,

    CONSTRAINT contributions_amount_positive
        CHECK (amount > 0),

    CONSTRAINT contributions_month_valid
        CHECK (
            contribution_month =
            DATE_TRUNC('month', contribution_month)::DATE
        ),

    CONSTRAINT contributions_payment_method_valid
        CHECK (
            payment_method IN ('mpesa')
        ),

    CONSTRAINT contributions_verification_status_valid
        CHECK (
            verification_status IN (
                'pending',
                'verified',
                'failed'
            )
        ),

    CONSTRAINT contributions_mpesa_code_unique
        UNIQUE (mpesa_code)
);

-- ============================================================
-- PAYOUTS
-- ============================================================

CREATE TABLE IF NOT EXISTS payouts (
    id SERIAL PRIMARY KEY,

    group_id INTEGER NOT NULL,

    member_id INTEGER NOT NULL,

    rotation_position INTEGER NOT NULL,

    amount NUMERIC(12, 2) NOT NULL,

    payout_month DATE NOT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'pending',

    mpesa_code VARCHAR(50),

    paid_at TIMESTAMPTZ,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_payouts_group
        FOREIGN KEY (group_id)
        REFERENCES groups(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_payouts_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE,

    CONSTRAINT payouts_position_positive
        CHECK (rotation_position > 0),

    CONSTRAINT payouts_amount_positive
        CHECK (amount > 0),

    CONSTRAINT payouts_month_valid
        CHECK (
            payout_month =
            DATE_TRUNC('month', payout_month)::DATE
        ),

    CONSTRAINT payouts_status_valid
        CHECK (
            status IN (
                'pending',
                'paid'
            )
        )
);

-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_groups_name
    ON groups(name);

CREATE INDEX IF NOT EXISTS idx_members_phone
    ON members(phone);

CREATE INDEX IF NOT EXISTS idx_group_members_group
    ON group_members(group_id);

CREATE INDEX IF NOT EXISTS idx_group_members_member
    ON group_members(member_id);

CREATE INDEX IF NOT EXISTS idx_group_members_position
    ON group_members(group_id, rotation_position);

CREATE INDEX IF NOT EXISTS idx_contributions_group
    ON contributions(group_id);

CREATE INDEX IF NOT EXISTS idx_contributions_member
    ON contributions(member_id);

CREATE INDEX IF NOT EXISTS idx_contributions_month
    ON contributions(contribution_month);

CREATE INDEX IF NOT EXISTS idx_contributions_status
    ON contributions(verification_status);

CREATE INDEX IF NOT EXISTS idx_payouts_group
    ON payouts(group_id);

CREATE INDEX IF NOT EXISTS idx_payouts_member
    ON payouts(member_id);

CREATE INDEX IF NOT EXISTS idx_payouts_month
    ON payouts(payout_month);

CREATE INDEX IF NOT EXISTS idx_payouts_status
    ON payouts(status);

COMMIT;
