-- ============================================================
-- 002_mali_chama_v2.sql
-- MALI CHAMA V2
-- Save Together. Own Together. Grow Together.
--
-- Adds:
--   1. Assets
--   2. Asset valuation history
--   3. Ownership units
--
-- Non-breaking migration from CHAMA V1
-- PostgreSQL 14+
-- ============================================================

BEGIN;

-- ============================================================
-- 1. ASSETS
-- What the Chama owns together
-- ============================================================

CREATE TABLE IF NOT EXISTS assets (
    id SERIAL PRIMARY KEY,

    group_id INTEGER NOT NULL,

    name VARCHAR(150) NOT NULL,

    type VARCHAR(50) NOT NULL DEFAULT 'savings',

    purchase_price NUMERIC(12,2) NOT NULL DEFAULT 0,

    current_value NUMERIC(12,2) NOT NULL DEFAULT 0,

    description TEXT,

    purchase_date DATE NOT NULL DEFAULT CURRENT_DATE,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_assets_group
        FOREIGN KEY (group_id)
        REFERENCES groups(id)
        ON DELETE CASCADE,

    CONSTRAINT assets_type_valid
        CHECK (
            type IN (
                'savings',
                'land',
                'money_market',
                'stock',
                'equipment',
                'other'
            )
        ),

    CONSTRAINT assets_price_valid
        CHECK (
            purchase_price >= 0
            AND current_value >= 0
        ),

    CONSTRAINT assets_name_not_empty
        CHECK (TRIM(name) <> '')
);


-- ============================================================
-- 2. ASSET VALUATIONS
-- Historical record of asset value changes
-- ============================================================

CREATE TABLE IF NOT EXISTS asset_valuations (
    id SERIAL PRIMARY KEY,

    asset_id INTEGER NOT NULL,

    old_value NUMERIC(12,2) NOT NULL,

    new_value NUMERIC(12,2) NOT NULL,

    reason TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_asset_valuations_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE,

    CONSTRAINT asset_valuations_old_value_valid
        CHECK (old_value >= 0),

    CONSTRAINT asset_valuations_new_value_valid
        CHECK (new_value >= 0)
);


-- ============================================================
-- 3. OWNERSHIP UNITS
-- Core ownership record for MALI CHAMA
--
-- Example:
--
-- Asset NAV       = KSh 50,000
-- Total Units     = 500
-- Unit Price      = KSh 100
--
-- Member owns 25 units:
-- 25 / 500 = 5%
-- 25 × KSh 100 = KSh 2,500
-- ============================================================

CREATE TABLE IF NOT EXISTS ownership_units (
    id SERIAL PRIMARY KEY,

    group_id INTEGER NOT NULL,

    member_id INTEGER NOT NULL,

    asset_id INTEGER NOT NULL,

    contribution_id INTEGER,

    units NUMERIC(16,6) NOT NULL,

    unit_price_at_purchase NUMERIC(12,4) NOT NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_ownership_group
        FOREIGN KEY (group_id)
        REFERENCES groups(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ownership_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ownership_asset
        FOREIGN KEY (asset_id)
        REFERENCES assets(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ownership_contribution
        FOREIGN KEY (contribution_id)
        REFERENCES contributions(id)
        ON DELETE SET NULL,

    CONSTRAINT ownership_units_positive
        CHECK (units > 0),

    CONSTRAINT ownership_unit_price_positive
        CHECK (unit_price_at_purchase > 0)
);


-- ============================================================
-- 4. INDEXES
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_assets_group
    ON assets(group_id);

CREATE INDEX IF NOT EXISTS idx_assets_type
    ON assets(type);

CREATE INDEX IF NOT EXISTS idx_asset_valuations_asset
    ON asset_valuations(asset_id);

CREATE INDEX IF NOT EXISTS idx_asset_valuations_created
    ON asset_valuations(asset_id, created_at);

CREATE INDEX IF NOT EXISTS idx_ownership_group_member
    ON ownership_units(group_id, member_id);

CREATE INDEX IF NOT EXISTS idx_ownership_asset
    ON ownership_units(asset_id);

CREATE INDEX IF NOT EXISTS idx_ownership_member
    ON ownership_units(member_id);

CREATE INDEX IF NOT EXISTS idx_ownership_contribution
    ON ownership_units(contribution_id);


-- ============================================================
-- 5. SAVINGS RESERVE
--
-- Create one Savings Reserve asset for every existing group.
--
-- We use a partial unique index so the seed is safely
-- repeatable without creating duplicate reserves.
-- ============================================================

CREATE UNIQUE INDEX IF NOT EXISTS uq_assets_group_savings_reserve
    ON assets(group_id)
    WHERE type = 'savings'
      AND name = 'Chama Savings Reserve';


INSERT INTO assets (
    group_id,
    name,
    type,
    purchase_price,
    current_value,
    description
)
SELECT
    g.id,
    'Chama Savings Reserve',
    'savings',
    0,
    0,
    'Group savings reserve'
FROM groups AS g
WHERE NOT EXISTS (
    SELECT 1
    FROM assets AS a
    WHERE a.group_id = g.id
      AND a.type = 'savings'
      AND a.name = 'Chama Savings Reserve'
);


COMMIT;


-- ============================================================
-- END OF MIGRATION
-- ============================================================
