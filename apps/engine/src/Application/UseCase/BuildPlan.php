<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ScanJobRepository;
use Hestia\Domain\Repository\PlanRepository;
use Hestia\Domain\Service\IdGenerator;

final class BuildPlan
{
    public function __construct(
        private ScanJobRepository $scanRepo,
        private PlanRepository $planRepo,
        private IdGenerator $ids
    ) {}

    public function execute(string $scanId, string $template): array
    {
        $scan = $this->scanRepo->find($scanId);
        if (!$scan) {
            return ['error' => 'SCAN_NOT_FOUND'];
        }

        $planId = $this->ids->generatePlanId();
        $root = $scan['path'];

        // Stub d’actions (exemples)
        $actions = [
            ['type' => 'mkdir', 'to' => $root . '\\Images\\2026\\01'],
            ['type' => 'move', 'from' => $root . '\\IMG_1234.jpg', 'to' => $root . '\\Images\\2026\\01\\IMG_1234.jpg'],
            ['type' => 'rename', 'from' => $root . '\\facture.pdf', 'to' => $root . '\\Administratif\\Factures\\2026-01-04-facture.pdf'],
        ];

        $plan = [
            'planId' => $planId,
            'scanId' => $scanId,
            'status' => 'ready',
            'template' => $template,
            'root' => $root,
            'actions' => $actions,
            'warnings' => [],
            'createdAt' => date(DATE_ATOM),
        ];

        $this->planRepo->create($plan);

        return $plan;
    }
}
