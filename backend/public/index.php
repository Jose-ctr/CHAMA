<?php

declare(strict_types=1);

use Chama\Config\Bootstrap;
use Chama\Config\Database;
use Chama\Http\Cors;
use Chama\Http\Request;
use Chama\Http\Response;
use Chama\Http\Router;
use App\Services\AssetService;
use App\Services\OwnershipService;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::init();

Cors::handle();

$router = new Router();

/*
|--------------------------------------------------------------------------
| Service factories
|--------------------------------------------------------------------------
*/

$assetService = static function (): AssetService {
    return new AssetService();
};

$ownershipService = static function (): OwnershipService {
    return new OwnershipService(Database::connect());
};

/*
|--------------------------------------------------------------------------
| Health check
|--------------------------------------------------------------------------
*/

$router->get('/', static function (): never {
    Response::success([
        'application' => 'CHAMA API',
        'version' => '2.0.0',
        'status' => 'running',
    ]);
});

/*
|--------------------------------------------------------------------------
| Asset endpoints
|--------------------------------------------------------------------------
*/

/**
 * GET /api/assets?group_id=1
 *
 * Retrieve all assets belonging to a group.
 */
$router->get('/api/assets', static function (Request $request) use ($assetService): never {
    $groupId = (int) $request->query('group_id');

    if ($groupId <= 0) {
        Response::error('A valid group_id is required.', 422);
    }

    $assets = $assetService()->getGroupAssets($groupId);

    Response::success([
        'group_id' => $groupId,
        'assets' => $assets,
    ]);
});

/**
 * POST /api/assets
 *
 * Create a group asset.
 */
$router->post('/api/assets', static function (Request $request) use ($assetService): never {
    $data = $request->all();

    $groupId = (int) ($data['group_id'] ?? 0);
    $name = trim((string) ($data['name'] ?? ''));
    $type = trim((string) ($data['type'] ?? ''));
    $purchasePrice = $data['purchase_price'] ?? null;
    $description = isset($data['description'])
        ? trim((string) $data['description'])
        : null;

    if ($groupId <= 0) {
        Response::error('A valid group_id is required.', 422);
    }

    if ($name === '') {
        Response::error('Asset name is required.', 422);
    }

    if ($type === '') {
        Response::error('Asset type is required.', 422);
    }

    if (
        $purchasePrice !== null
        && (!is_numeric($purchasePrice)
            || !is_finite((float) $purchasePrice)
            || (float) $purchasePrice < 0)
    ) {
        Response::error('purchase_price must be a valid non-negative number.', 422);
    }

    $asset = $assetService()->createAsset(
        $groupId,
        $type,
        $name,
        (float) ($purchasePrice ?? 0),
        $description
    );

    Response::success([
        'asset' => $asset,
    ], 201);
});

/**
 * GET /api/asset?id=1
 *
 * Retrieve a single asset.
 */
$router->get('/api/asset', static function (Request $request) use ($assetService): never {
    $assetId = (int) $request->query('id');

    if ($assetId <= 0) {
        Response::error('A valid asset id is required.', 422);
    }

    $asset = $assetService()->getAsset($assetId);

    Response::success([
        'asset' => $asset,
    ]);
});

/**
 * PUT /api/asset?id=1
 *
 * Update an existing asset.
 */
$router->put('/api/asset', static function (Request $request) use ($assetService): never {
    $assetId = (int) $request->query('id');
    $data = $request->all();

    if ($assetId <= 0) {
        Response::error('A valid asset id is required.', 422);
    }

    $name = isset($data['name'])
        ? trim((string) $data['name'])
        : null;

    $type = isset($data['type'])
        ? trim((string) $data['type'])
        : null;

    $description = array_key_exists('description', $data)
        ? ($data['description'] === null
            ? null
            : trim((string) $data['description']))
        : null;

    $purchasePrice = $data['purchase_price'] ?? null;

    if ($name === '' || $type === '') {
        Response::error('Asset name and type cannot be empty.', 422);
    }

    if (
        $purchasePrice !== null
        && (!is_numeric($purchasePrice)
            || !is_finite((float) $purchasePrice)
            || (float) $purchasePrice < 0)
    ) {
        Response::error('purchase_price must be a valid non-negative number.', 422);
    }

    $asset = $assetService()->updateAsset(
        $assetId,
        $name,
        $type,
        $purchasePrice !== null ? (float) $purchasePrice : null,
        $description
    );

    Response::success([
        'asset' => $asset,
    ]);
});

/**
 * POST /api/asset/valuation?id=1
 *
 * Record a new asset valuation.
 */
$router->post('/api/asset/valuation', static function (Request $request) use ($assetService): never {
    $assetId = (int) $request->query('id');
    $data = $request->all();

    if ($assetId <= 0) {
        Response::error('A valid asset id is required.', 422);
    }

    $newValue = $data['new_value'] ?? null;

    if (
        !is_numeric($newValue)
        || !is_finite((float) $newValue)
        || (float) $newValue < 0
    ) {
        Response::error('A valid non-negative new_value is required.', 422);
    }

    $reason = isset($data['reason'])
        ? trim((string) $data['reason'])
        : null;

    $valuation = $assetService()->recordValuation(
        $assetId,
        (float) $newValue,
        $reason
    );

    Response::success([
        'valuation' => $valuation,
    ], 201);
});

/*
|--------------------------------------------------------------------------
| Ownership endpoints
|--------------------------------------------------------------------------
*/

/**
 * GET /api/ownership/savings-asset?group_id=1
 *
 * Retrieve or create the group's savings reserve asset.
 */
$router->get(
    '/api/ownership/savings-asset',
    static function (Request $request) use ($ownershipService): never {
        $groupId = (int) $request->query('group_id');

        if ($groupId <= 0) {
            Response::error('A valid group_id is required.', 422);
        }

        $asset = $ownershipService()->getSavingsAsset($groupId);

        Response::success([
            'group_id' => $groupId,
            'asset' => $asset,
        ]);
    }
);

/**
 * GET /api/ownership/units?asset_id=1
 *
 * Retrieve the total ownership units for an asset.
 */
$router->get(
    '/api/ownership/units',
    static function (Request $request) use ($ownershipService): never {
        $assetId = (int) $request->query('asset_id');

        if ($assetId <= 0) {
            Response::error('A valid asset_id is required.', 422);
        }

        $totalUnits = $ownershipService()->getTotalUnits($assetId);

        Response::success([
            'asset_id' => $assetId,
            'total_units' => $totalUnits,
        ]);
    }
);

/**
 * GET /api/ownership/unit-price?asset_id=1
 *
 * Retrieve the current price per ownership unit.
 */
$router->get(
    '/api/ownership/unit-price',
    static function (Request $request) use ($ownershipService): never {
        $assetId = (int) $request->query('asset_id');

        if ($assetId <= 0) {
            Response::error('A valid asset_id is required.', 422);
        }

        $unitPrice = $ownershipService()->getUnitPrice($assetId);

        Response::success([
            'asset_id' => $assetId,
            'unit_price' => $unitPrice,
        ]);
    }
);

/**
 * POST /api/ownership/allocate
 *
 * Allocate verified contribution funds into ownership units.
 *
 * JSON body:
 * {
 *   "group_id": 1,
 *   "member_id": 1,
 *   "contribution_id": 1,
 *   "ownership_amount": 500
 * }
 */
$router->post(
    '/api/ownership/allocate',
    static function (Request $request) use ($ownershipService): never {
        $data = $request->all();

        $groupId = filter_var(
            $data['group_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $memberId = filter_var(
            $data['member_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $contributionId = filter_var(
            $data['contribution_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $ownershipAmount = $data['ownership_amount'] ?? null;

        if ($groupId === false || $groupId === null || $groupId <= 0) {
            Response::error('A valid group_id is required.', 422);
        }

        if ($memberId === false || $memberId === null || $memberId <= 0) {
            Response::error('A valid member_id is required.', 422);
        }

        if (
            $contributionId === false
            || $contributionId === null
            || $contributionId <= 0
        ) {
            Response::error('A valid contribution_id is required.', 422);
        }

        if (
            !is_numeric($ownershipAmount)
            || !is_finite((float) $ownershipAmount)
            || (float) $ownershipAmount <= 0
        ) {
            Response::error(
                'ownership_amount must be a positive number.',
                422
            );
        }

        $allocation = $ownershipService()->allocateContribution(
            $groupId,
            $memberId,
            $contributionId,
            (float) $ownershipAmount
        );

        Response::success([
            'allocation' => $allocation,
        ], 201);
    }
);

/**
 * GET /api/ownership/member-units?group_id=1&member_id=1
 *
 * Retrieve a member's ownership units in a group.
 */
$router->get(
    '/api/ownership/member-units',
    static function (Request $request) use ($ownershipService): never {
        $groupId = (int) $request->query('group_id');
        $memberId = (int) $request->query('member_id');

        if ($groupId <= 0 || $memberId <= 0) {
            Response::error(
                'Valid group_id and member_id are required.',
                422
            );
        }

        $units = $ownershipService()->getMemberUnits(
            $groupId,
            $memberId
        );

        Response::success([
            'group_id' => $groupId,
            'member_id' => $memberId,
            'member_units' => $units,
        ]);
    }
);

/**
 * GET /api/ownership/member-percentage?group_id=1&member_id=1
 *
 * Retrieve a member's ownership percentage.
 */
$router->get(
    '/api/ownership/member-percentage',
    static function (Request $request) use ($ownershipService): never {
        $groupId = (int) $request->query('group_id');
        $memberId = (int) $request->query('member_id');

        if ($groupId <= 0 || $memberId <= 0) {
            Response::error(
                'Valid group_id and member_id are required.',
                422
            );
        }

        $percentage = $ownershipService()->getMemberOwnershipPercentage(
            $groupId,
            $memberId
        );

        Response::success([
            'group_id' => $groupId,
            'member_id' => $memberId,
            'member_ownership_percentage' => $percentage,
        ]);
    }
);

/**
 * GET /api/ownership/member-asset-value?asset_id=1&member_id=1
 *
 * Retrieve a member's value in a specific asset.
 */
$router->get(
    '/api/ownership/member-asset-value',
    static function (Request $request) use ($ownershipService): never {
        $assetId = (int) $request->query('asset_id');
        $memberId = (int) $request->query('member_id');

        if ($assetId <= 0 || $memberId <= 0) {
            Response::error(
                'Valid asset_id and member_id are required.',
                422
            );
        }

        $assetValue = $ownershipService()->getMemberAssetValue(
            $assetId,
            $memberId
        );

        Response::success([
            'asset_id' => $assetId,
            'member_id' => $memberId,
            'member_asset_value' => $assetValue,
        ]);
    }
);

/**
 * GET /api/ownership/member-summary?group_id=1&member_id=1
 *
 * Retrieve a member's ownership summary.
 */
$router->get(
    '/api/ownership/member-summary',
    static function (Request $request) use ($ownershipService): never {
        $groupId = (int) $request->query('group_id');
        $memberId = (int) $request->query('member_id');

        if ($groupId <= 0 || $memberId <= 0) {
            Response::error(
                'Valid group_id and member_id are required.',
                422
            );
        }

        $summary = $ownershipService()->getMemberSummary(
            $groupId,
            $memberId
        );

        Response::success([
            'summary' => $summary,
        ]);
    }
);

/*
|--------------------------------------------------------------------------
| Dispatch request
|--------------------------------------------------------------------------
*/

try {
    $router->dispatch();
} catch (Throwable $e) {
    error_log(
        sprintf(
            '[CHAMA API] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        )
    );

    Response::error('An internal server error occurred.', 500);
}
