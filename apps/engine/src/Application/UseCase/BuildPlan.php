<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ScanJobRepository;
use Hestia\Domain\Repository\PlanRepository;
use Hestia\Domain\Service\IdGenerator;

final class BuildPlan
{
    private const IMAGE_EXT = ['png','jpg','jpeg','gif','webp','bmp','tiff'];
    private const DOC_EXT   = ['odt','doc','docx','pdf','txt','rtf','md'];
    private const ARCH_EXT  = ['zip','rar','7z','tar','gz'];
    
    public function __construct(
        private ScanJobRepository $scanRepo,
        private PlanRepository $planRepo,
        private IdGenerator $ids
    ) {}

    /* public function execute(string $scanId, string $template): array
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
    } */

        public function execute(string $scanId, string $template): array
    {
        $scan = $this->scanRepo->find($scanId);
        if (!$scan) {
            return ['error' => 'SCAN_NOT_FOUND'];
        }

        $root = rtrim((string)$scan['path'], "\\/");

        $files = $scan['files'] ?? [];
        if (!is_array($files)) $files = [];

        $actions = [];
        $mkdirNeeded = [];

        foreach ($files as $f) {
            if (!isset($f['path'], $f['name'], $f['ext'])) continue;

            $ext = strtolower((string)$f['ext']);
            $category = $this->categoryForExt($ext);

            $destDir = $root . DIRECTORY_SEPARATOR . $category;
            $mkdirNeeded[$destDir] = true;

            $from = (string)$f['path'];
            $to = $destDir . DIRECTORY_SEPARATOR . (string)$f['name'];

            // On ne renomme pas en v1 (réel). On ne fait que déplacer.
            $actions[] = [
                'type' => 'move',
                'from' => $from,
                'to' => $to,
            ];
        }

        // mkdir actions (une fois par dossier)
        foreach (array_keys($mkdirNeeded) as $dir) {
            $actions[] = ['type' => 'mkdir', 'to' => $dir];
        }

        // On met mkdir d'abord (plus logique pour apply réel futur)
        usort($actions, function ($a, $b) {
            return ($a['type'] === 'mkdir' ? 0 : 1) <=> ($b['type'] === 'mkdir' ? 0 : 1);
        });

        $planId = $this->ids->generatePlanId();
        $now = date(DATE_ATOM);

        $plan = [
            'planId' => $planId,
            'scanId' => $scanId,
            'status' => 'ready',
            'template' => $template,
            'root' => $root,
            'actions' => $actions,
            'warnings' => [],
            'createdAt' => $now,
        ];

        $this->planRepo->create($plan);

        return $plan;
    }

    private function categoryForExt(string $ext): string
    {
        if ($ext === '' || $ext === 'no_ext') return 'Autres';

        if (in_array($ext, self::IMAGE_EXT, true)) return 'Images';
        if (in_array($ext, self::DOC_EXT, true)) return 'Documents';
        if (in_array($ext, self::ARCH_EXT, true)) return 'Archives';

        return 'Autres';
    }
}
