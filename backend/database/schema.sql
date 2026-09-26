-- ============================================================
-- CHAMA
-- Database Schema - MVP
-- PostgreSQL 14+
-- ============================================================

BEGIN;

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
-- CONTRIBUTIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS contributions (
    id SERIAL PRIMARY KEY,
    member_id INTEGER NOT NULL,
    amount NUMERIC(12, 2) NOT NULL,
    month DATE NOT NULL,
    mpesa_code VARCHAR(50),
    verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_contributions_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE,

    CONSTRAINT contributions_amount_positive
        CHECK (amount > 0),

    CONSTRAINT contributions_month_valid
        CHECK (month = DATE_TRUNC('month', month)::DATE),

    CONSTRAINT contributions_member_month_unique
        UNIQUE (member_id, month)
);

-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_members_phone
    ON members(phone);

CREATE INDEX IF NOT EXISTS idx_contributions_member
    ON contributions(member_id);

CREATE INDEX IF NOT EXISTS idx_contributions_month
    ON contributions(month);

CREATE INDEX IF NOT EXISTS idx_contributions_verified
    ON contributions(verified);

COMMIT;
