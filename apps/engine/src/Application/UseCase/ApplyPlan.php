<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\PlanRepository;
use Hestia\Domain\Repository\ApplyRunRepository;
use Hestia\Domain\Service\IdGenerator;

final class ApplyPlan
{
    public function __construct(
        private PlanRepository $planRepo,
        private ApplyRunRepository $applyRepo,
        private IdGenerator $ids
    ) {}

    public function execute(string $planId): array
    {
        $plan = $this->planRepo->find($planId);
        if (!$plan) {
            return ['error' => 'PLAN_NOT_FOUND'];
        }

        $applyId = $this->ids->generateApplyId();

        $apply = [
            'applyId' => $applyId,
            'planId' => $planId,
            'status' => 'queued',
            'progress' => [
                'done' => 0,
                'total' => max(1, count($plan['actions'])),
                'percent' => 0
            ],
            'summary' => [
                'createdFolders' => 0,
                'moved' => 0,
                'renamed' => 0,
                'errors' => 0
            ],
            'createdAt' => date(DATE_ATOM),
            'updatedAt' => date(DATE_ATOM),
        ];

        $this->applyRepo->create($apply);

        return $apply;
    }
}
