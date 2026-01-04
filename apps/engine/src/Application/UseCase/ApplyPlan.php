<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\PlanRepository;
use Hestia\Domain\Repository\ApplyRunRepository;
use Hestia\Domain\Service\IdGenerator;
use Hestia\Domain\Service\Filesystem;

final class ApplyPlan
{
    public function __construct(
        private PlanRepository $planRepo,
        private ApplyRunRepository $applyRepo,
        private IdGenerator $ids,
        private Filesystem $fs
    ) {}

    public function execute(string $planId): array
    {
        $plan = $this->planRepo->find($planId);
        if (!$plan) {
            return ['error' => 'PLAN_NOT_FOUND'];
        }

        $root = (string)($plan['root'] ?? '');
        $actions = $plan['actions'] ?? [];
        if (!is_array($actions)) $actions = [];

        $applyId = $this->ids->generateApplyId();
        $now = date(DATE_ATOM);

        $apply = [
            'applyId' => $applyId,
            'planId' => $planId,
            'status' => 'running',
            'progress' => [
                'done' => 0,
                'total' => count($actions),
                'percent' => 0,
            ],
            'summary' => [
                'createdFolders' => 0,
                'moved' => 0,
                'renamed' => 0,
                'errors' => 0,
            ],
            // journal pour undo (liste d’opérations inverses)
            'journal' => [
                'reverseOps' => [],
            ],
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        // On persiste tout de suite
        $this->applyRepo->create($apply);

        try {
            $total = max(1, count($actions));
            $done = 0;

            foreach ($actions as $a) {
                $type = $a['type'] ?? '';
                if ($type === 'mkdir') {
                    $to = (string)($a['to'] ?? '');
                    $this->assertInsideRoot($root, $to);

                    $beforeExists = is_dir($to);
                    $this->fs->ensureDir($to);

                    if (!$beforeExists) {
                        $apply['summary']['createdFolders']++;
                        // Undo: supprimer si vide
                        $apply['journal']['reverseOps'][] = ['type' => 'rmdir_if_empty', 'dir' => $to];
                    }
                }

                if ($type === 'move') {
                    $from = (string)($a['from'] ?? '');
                    $to = (string)($a['to'] ?? '');

                    $this->assertInsideRoot($root, $from);
                    $this->assertInsideRoot($root, $to);

                    if (!$this->fs->fileExists($from)) {
                        $apply['summary']['errors']++;
                        // On log l’erreur, mais on continue (choix MVP)
                        $apply['journal']['reverseOps'][] = ['type' => 'noop'];
                    } else {
                        $this->fs->move($from, $to);
                        $apply['summary']['moved']++;

                        // Undo: move inverse
                        $apply['journal']['reverseOps'][] = ['type' => 'move', 'from' => $to, 'to' => $from];
                    }
                }

                // (rename) pas encore en v1 réel (A ajouter plus tard)

                $done++;
                $apply['progress']['done'] = $done;
                $apply['progress']['percent'] = (int)floor(($done / $total) * 100);
                $apply['updatedAt'] = date(DATE_ATOM);

                $this->applyRepo->update($applyId, $apply);
            }

            $apply['status'] = 'done';
            $apply['progress']['percent'] = 100;
            $apply['updatedAt'] = date(DATE_ATOM);
            $this->applyRepo->update($applyId, $apply);

            return $apply;
        } catch (\Throwable $e) {
            $apply['status'] = 'failed';
            $apply['summary']['errors']++;
            $apply['errorMessage'] = $e->getMessage();
            $apply['updatedAt'] = date(DATE_ATOM);
            $this->applyRepo->update($applyId, $apply);

            return $apply;
        }
    }

    private function assertInsideRoot(string $root, string $path): void
    {
        if ($root === '') {
            throw new \RuntimeException("Invalid root");
        }
        if (!$this->fs->isPathInsideRoot($root, $path)) {
            throw new \RuntimeException("PATH_NOT_ALLOWED: $path");
        }
    }
}
