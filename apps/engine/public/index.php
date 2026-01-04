<?php
declare(strict_types=1);
// ---- CORS (DEV uniquement) ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}


require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/app.php';
$scanDefaults = $config['scan_defaults'] ?? [];
$maxDepth = (int)($scanDefaults['max_depth'] ?? 20);
$excludeNames = (array)($scanDefaults['exclude_names'] ?? []);


use Hestia\Infrastructure\Service\SimpleIdGenerator;
use Hestia\Infrastructure\Filesystem\LocalFilesystem;

use Hestia\Application\UseCase\StartScan;
use Hestia\Application\UseCase\GetScanStatus;
use Hestia\Application\UseCase\BuildPlan;
use Hestia\Application\UseCase\GetPlanPreview;

use Hestia\Interface\Http\Controller\V1\ScanController;
use Hestia\Interface\Http\Controller\V1\PlanController;
use Hestia\Interface\Http\Response\ApiResponse;

use Hestia\Infrastructure\Persistence\File\FileScanJobRepository;
use Hestia\Infrastructure\Persistence\File\FilePlanRepository;

use Hestia\Infrastructure\Persistence\File\FileApplyRunRepository;
use Hestia\Application\UseCase\ApplyPlan;
use Hestia\Application\UseCase\GetApplyStatus;
use Hestia\Application\UseCase\UndoApply;
use Hestia\Interface\Http\Controller\V1\ApplyController;

// ------------------------------------------------------------
// Bootstrap minimal (DI "à la main") — InMemory uniquement
// ------------------------------------------------------------
$scanRepo = new FileScanJobRepository(__DIR__ . '/../storage/cache/scans');
$planRepo = new FilePlanRepository(__DIR__ . '/../storage/cache/plans');
$idGen = new SimpleIdGenerator();
$applyRepo = new FileApplyRunRepository(__DIR__ . '/../storage/cache/applies');


//$startScan = new StartScan($scanRepo, $idGen);
$fs = new LocalFilesystem();
$startScan = new StartScan($scanRepo, $idGen, $fs, $maxDepth, $excludeNames);

$getScanStatus = new GetScanStatus($scanRepo);

$buildPlan = new BuildPlan($scanRepo, $planRepo, $idGen);
$getPlanPreview = new GetPlanPreview($planRepo);

$applyPlan = new ApplyPlan($planRepo, $applyRepo, $idGen, $fs);
$getApplyStatus = new GetApplyStatus($applyRepo);
$undoApply = new UndoApply($applyRepo, $fs);

$scanController = new ScanController($startScan, $getScanStatus);
$planController = new PlanController($buildPlan, $getPlanPreview);
$applyController = new ApplyController($applyPlan, $getApplyStatus, $undoApply);

// ------------------------------------------------------------
// Routing ultra simple
// ------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    $body = [];
}

// Health
if ($path === '/health') {
    ApiResponse::ok([
        'status' => 'ok',
        'name' => 'HESTIA Engine',
        'version' => '0.1.0',
    ]);
    exit;
}

// --------------------
// Scans
// --------------------
if ($method === 'POST' && $path === '/v1/scans') {
    $scanController->start($body);
    exit;
}

if ($method === 'GET' && preg_match('#^/v1/scans/(.+)$#', $path, $m)) {
    $scanController->status($m[1]);
    exit;
}

// --------------------
// Plans
// --------------------
if ($method === 'POST' && $path === '/v1/plans') {
    $planController->build($body);
    exit;
}

if ($method === 'GET' && preg_match('#^/v1/plans/(.+)$#', $path, $m)) {
    $planController->preview($m[1]);
    exit;
}

if ($method === 'POST' && $path === '/v1/applies') {
    $applyController->start($body);
    exit;
}

if ($method === 'GET' && preg_match('#^/v1/applies/(.+)$#', $path, $m)) {
    $applyController->status($m[1]);
    exit;
}

if ($method === 'POST' && $path === '/v1/undo') {
    $applyController->undo($body);
    exit;
}


// Fallback
ApiResponse::fail('NOT_FOUND', 'Route not found', ['method' => $method, 'path' => $path], 404);
