<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ApplyRunRepository;
use Hestia\Domain\Service\Filesystem;

final class UndoApply
{
    public function __construct(
        private ApplyRunRepository $applyRepo,
        private Filesystem $fs
    ) {}

    public function execute(string $applyId): array
    {
        $apply = $this->applyRepo->find($applyId);
        if (!$apply) {
            return ['error' => 'APPLY_NOT_FOUND'];
        }

        $journal = $apply['journal']['reverseOps'] ?? [];
        if (!is_array($journal) || count($journal) === 0) {
            // rien à annuler
            return ['applyId' => $applyId, 'status' => 'done'];
        }

        // On exécute en ordre inverse (rollback)
        $ops = array_reverse($journal);

        $apply['undo'] = [
            'status' => 'running',
            'createdAt' => date(DATE_ATOM),
            'updatedAt' => date(DATE_ATOM),
        ];
        $this->applyRepo->update($applyId, $apply);

        try {
            foreach ($ops as $op) {
                $type = $op['type'] ?? '';
                if ($type === 'move') {
                    $from = (string)($op['from'] ?? '');
                    $to = (string)($op['to'] ?? '');

                    // Undo move seulement si le "from" existe
                    if ($this->fs->fileExists($from)) {
                        $this->fs->move($from, $to);
                    }
                }

                if ($type === 'rmdir_if_empty') {
                    $dir = (string)($op['dir'] ?? '');
                    $this->fs->removeDirIfEmpty($dir);
                }
            }

            $apply['undo']['status'] = 'done';
            $apply['undo']['updatedAt'] = date(DATE_ATOM);
            $this->applyRepo->update($applyId, $apply);

            return ['applyId' => $applyId, 'status' => 'done'];
        } catch (\Throwable $e) {
            $apply['undo']['status'] = 'failed';
            $apply['undo']['updatedAt'] = date(DATE_ATOM);
            $apply['undo']['errorMessage'] = $e->getMessage();
            $this->applyRepo->update($applyId, $apply);

            return ['applyId' => $applyId, 'status' => 'failed'];
        }
    }
}
