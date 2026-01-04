<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ApplyRunRepository;

final class GetApplyStatus
{
    public function __construct(
        private ApplyRunRepository $repo
    ) {}

    /* public function execute(string $applyId): ?array
    {
        $apply = $this->repo->find($applyId);
        if (!$apply) return null;

        if ($apply['status'] !== 'done') {
            $apply['status'] = 'running';

            $total = max(1, (int)$apply['progress']['total']);
            $done = (int)$apply['progress']['done'];

            $step = rand(1, 5);
            $done = min($total, $done + $step);

            $apply['progress']['done'] = $done;
            $apply['progress']['percent'] = (int)floor(($done / $total) * 100);
            $apply['updatedAt'] = date(DATE_ATOM);

            // Fake stats
            $apply['summary']['createdFolders'] = min($done, 3);
            $apply['summary']['moved'] = min($done, 10);
            $apply['summary']['renamed'] = min($done, 5);

            if ($done >= $total) {
                $apply['status'] = 'done';
                $apply['progress']['percent'] = 100;
            }

            $this->repo->update($applyId, $apply);
        }

        return $apply;
    } */

    public function execute(string $applyId): ?array
    {
        return $this->repo->find($applyId);
    }
}
