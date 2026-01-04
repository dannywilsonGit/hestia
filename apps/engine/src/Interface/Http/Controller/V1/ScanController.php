<?php
declare(strict_types=1);

namespace Hestia\Interface\Http\Controller\V1;

use Hestia\Application\UseCase\StartScan;
use Hestia\Application\UseCase\GetScanStatus;
use Hestia\Interface\Http\Response\ApiResponse;

final class ScanController
{
    public function __construct(
        private StartScan $startScan,
        private GetScanStatus $getScanStatus
    ) {}

    public function start(array $body): void
    {
        if (empty($body['path'])) {
            ApiResponse::fail('VALIDATION_ERROR', 'Missing path');
            return;
        }

        $scan = $this->startScan->execute($body['path']);

        ApiResponse::ok([
            'scanId' => $scan['scanId'],
            'status' => $scan['status'],
            'createdAt' => $scan['createdAt']
        ], 201);
    }

    public function status(string $scanId): void
    {
        $scan = $this->getScanStatus->execute($scanId);

        if (!$scan) {
            ApiResponse::fail('SCAN_NOT_FOUND', 'Scan not found', null, 404);
            return;
        }

        ApiResponse::ok($scan);
    }
}
