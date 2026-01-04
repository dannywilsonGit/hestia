<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\PlanRepository;

final class GetPlanPreview
{
    public function __construct(
        private PlanRepository $repo
    ) {}

    public function execute(string $planId): ?array
    {
        return $this->repo->find($planId);
    }
}
