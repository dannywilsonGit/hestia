<?php
declare(strict_types=1);

namespace Hestia\Interface\Http\Controller\V1;

use Hestia\Application\UseCase\BuildPlan;
use Hestia\Application\UseCase\GetPlanPreview;
use Hestia\Interface\Http\Response\ApiResponse;

final class PlanController
{
    public function __construct(
        private BuildPlan $buildPlan,
        private GetPlanPreview $getPlanPreview
    ) {}

    public function build(array $body): void
    {
        if (empty($body['scanId']) || empty($body['template'])) {
            ApiResponse::fail('VALIDATION_ERROR', 'Missing scanId or template');
            return;
        }

        /* $result = $this->buildPlan->execute($body['scanId'], $body['template']);

        if (isset($result['error']) && $result['error'] === 'SCAN_NOT_FOUND') {
            ApiResponse::fail('SCAN_NOT_FOUND', 'Scan not found', null, 404);
            return;
        } */

        $result = $this->buildPlan->execute($body['scanId'], $body['template']);

if (isset($result['error']) && $result['error'] === 'SCAN_NOT_FOUND') {
    ApiResponse::fail('SCAN_NOT_FOUND', 'Scan not found', null, 404);
    return;
}

if (isset($result['error']) && $result['error'] === 'TEMPLATE_NOT_FOUND') {
    ApiResponse::fail(
        'TEMPLATE_NOT_FOUND',
        'Template not found',
        [
            'template' => $result['template'] ?? null,
            'available' => $result['available'] ?? [],
        ],
        400
    );
    return;
}

        ApiResponse::ok([
            'planId' => $result['planId'],
            'status' => $result['status'],
            'stats' => [
                'mkdirCount' => count(array_filter($result['actions'], fn($a) => $a['type'] === 'mkdir')),
                'moveCount' => count(array_filter($result['actions'], fn($a) => $a['type'] === 'move')),
                'renameCount' => 0,
                'uncertainCount' => 0
            ],
            'createdAt' => $result['createdAt']
        ], 201);
    }

    public function preview(string $planId): void
    {
        $plan = $this->getPlanPreview->execute($planId);

        if (!$plan) {
            ApiResponse::fail('PLAN_NOT_FOUND', 'Plan not found', null, 404);
            return;
        }

        ApiResponse::ok([
            'planId' => $plan['planId'],
            'scanId' => $plan['scanId'],
            'root' => $plan['root'],
            'actions' => $plan['actions'],
            'warnings' => $plan['warnings'],
            'createdAt' => $plan['createdAt']
        ]);
    }
}
