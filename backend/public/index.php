<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CHAMA API Entry Point
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Chama\Config\Bootstrap;
use Chama\Http\Cors;
use Chama\Http\Request;
use Chama\Http\Response;
use Chama\Http\Router;
use App\Services\AssetService;

try {
    /*
    |--------------------------------------------------------------------------
    | Load application configuration
    |--------------------------------------------------------------------------
    */

    Bootstrap::load();

    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    */

    Cors::handle();

    /*
    |--------------------------------------------------------------------------
    | Request
    |--------------------------------------------------------------------------
    */

    $request = new Request();

    /*
    |--------------------------------------------------------------------------
    | Router
    |--------------------------------------------------------------------------
    */

    $router = new Router();

    /*
    |--------------------------------------------------------------------------
    | API Health
    |--------------------------------------------------------------------------
    */

    $router->get('/', function (Request $request): never {
        Response::success([
            'app' => 'CHAMA',
            'version' => 'v1',
            'message' => 'CHAMA API is running.',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Assets - List Group Assets
    |--------------------------------------------------------------------------
    */

    $router->get('/api/assets', function (Request $request): never {
        $groupId = (int) $request->query('group_id', 0);

        if ($groupId <= 0) {
            Response::error(
                'A valid group_id is required.',
                422
            );
        }

        $service = new AssetService();

        Response::success(
            $service->getGroupAssets($groupId)
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Assets - Create Asset
    |--------------------------------------------------------------------------
    */

    $router->post('/api/assets', function (Request $request): never {
        $data = $request->all();

        $groupId = (int) ($data['group_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $description = isset($data['description'])
            ? trim((string) $data['description'])
            : null;
        $purchaseValue = (float) ($data['purchase_value'] ?? 0);

        if ($groupId <= 0) {
            Response::error(
                'A valid group_id is required.',
                422
            );
        }

        if ($name === '') {
            Response::error(
                'Asset name is required.',
                422
            );
        }

        if ($purchaseValue < 0) {
            Response::error(
                'Purchase value cannot be negative.',
                422
            );
        }

        $service = new AssetService();

        Response::success(
            $service->createAsset(
                $groupId,
                $name,
                $description,
                $purchaseValue
            ),
            201
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Asset - Get Single Asset
    |--------------------------------------------------------------------------
    */

    $router->get('/api/asset', function (Request $request): never {
        $assetId = (int) $request->query('id', 0);

        if ($assetId <= 0) {
            Response::error(
                'A valid asset id is required.',
                422
            );
        }

        $service = new AssetService();

        Response::success(
            $service->getAsset($assetId)
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Asset - Update Asset
    |--------------------------------------------------------------------------
    */

    $router->put('/api/asset', function (Request $request): never {
        $assetId = (int) $request->query('id', 0);

        if ($assetId <= 0) {
            Response::error(
                'A valid asset id is required.',
                422
            );
        }

        $data = $request->all();

        $name = array_key_exists('name', $data)
            ? trim((string) $data['name'])
            : null;

        $description = array_key_exists('description', $data)
            ? trim((string) $data['description'])
            : null;

        $purchaseValue = array_key_exists('purchase_value', $data)
            ? (float) $data['purchase_value']
            : null;

        $currentValue = array_key_exists('current_value', $data)
            ? (float) $data['current_value']
            : null;

        if ($name !== null && $name === '') {
            Response::error(
                'Asset name cannot be empty.',
                422
            );
        }

        if ($purchaseValue !== null && $purchaseValue < 0) {
            Response::error(
                'Purchase value cannot be negative.',
                422
            );
        }

        if ($currentValue !== null && $currentValue < 0) {
            Response::error(
                'Current value cannot be negative.',
                422
            );
        }

        if (
            $name === null &&
            $description === null &&
            $purchaseValue === null &&
            $currentValue === null
        ) {
            Response::error(
                'At least one asset field is required.',
                422
            );
        }

        $service = new AssetService();

        Response::success(
            $service->updateAsset(
                $assetId,
                $name,
                $description,
                $purchaseValue,
                $currentValue
            )
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Asset - Record Valuation
    |--------------------------------------------------------------------------
    */

    $router->post('/api/asset/valuation', function (Request $request): never {
        $assetId = (int) $request->query('id', 0);

        if ($assetId <= 0) {
            Response::error(
                'A valid asset id is required.',
                422
            );
        }

        $data = $request->all();

        if (!array_key_exists('new_value', $data)) {
            Response::error(
                'New asset value is required.',
                422
            );
        }

        $newValue = (float) $data['new_value'];

        if ($newValue < 0) {
            Response::error(
                'Asset value cannot be negative.',
                422
            );
        }

        $reason = isset($data['reason'])
            ? trim((string) $data['reason'])
            : null;

        $service = new AssetService();

        Response::success(
            $service->recordValuation(
                $assetId,
                $newValue,
                $reason
            ),
            201
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Dispatch
    |--------------------------------------------------------------------------
    */

    $router->dispatch($request);
} catch (Throwable $e) {
    /*
    |--------------------------------------------------------------------------
    | Error handling
    |--------------------------------------------------------------------------
    */

    error_log($e->getMessage());

    Response::error(
        'An internal server error occurred.',
        500
    );
}

Commit

Use:

feat: expose asset valuation API endpoint

After you commit, send me the GitHub confirmation. Then we'll move to the next backend service rather than adding more routes to this file.
