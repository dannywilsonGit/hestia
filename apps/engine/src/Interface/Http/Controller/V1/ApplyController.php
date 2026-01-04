<?php
declare(strict_types=1);

namespace Hestia\Interface\Http\Controller\V1;

use Hestia\Application\UseCase\ApplyPlan;
use Hestia\Application\UseCase\GetApplyStatus;
use Hestia\Application\UseCase\UndoApply;
use Hestia\Interface\Http\Response\ApiResponse;

final class ApplyController
{
    public function __construct(
        private ApplyPlan $applyPlan,
        private GetApplyStatus $getApplyStatus,
        private UndoApply $undoApply
    ) {}

    public function start(array $body): void
    {
        if (empty($body['planId'])) {
            ApiResponse::fail('VALIDATION_ERROR', 'Missing planId');
            return;
        }

        $result = $this->applyPlan->execute($body['planId']);

        if (isset($result['error']) && $result['error'] === 'PLAN_NOT_FOUND') {
            ApiResponse::fail('PLAN_NOT_FOUND', 'Plan not found', null, 404);
            return;
        }

        ApiResponse::ok([
            'applyId' => $result['applyId'],
            'status' => $result['status'],
            'createdAt' => $result['createdAt']
        ], 201);
    }

    public function status(string $applyId): void
    {
        $apply = $this->getApplyStatus->execute($applyId);

        if (!$apply) {
            ApiResponse::fail('APPLY_NOT_FOUND', 'Apply not found', null, 404);
            return;
        }

        ApiResponse::ok($apply);
    }

    public function undo(array $body): void
    {
        if (empty($body['applyId'])) {
            ApiResponse::fail('VALIDATION_ERROR', 'Missing applyId');
            return;
        }

        $result = $this->undoApply->execute($body['applyId']);

        if (isset($result['error']) && $result['error'] === 'APPLY_NOT_FOUND') {
            ApiResponse::fail('APPLY_NOT_FOUND', 'Apply not found', null, 404);
            return;
        }

        ApiResponse::ok($result);
    }
}
