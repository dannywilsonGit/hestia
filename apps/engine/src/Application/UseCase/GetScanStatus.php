<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ScanJobRepository;

final class GetScanStatus
{
    public function __construct(
        private ScanJobRepository $repository
    ) {}

    /* public function execute(string $scanId): ?array
    {
        $scan = $this->repository->find($scanId);

        if (!$scan) {
            return null;
        }

        // Fake progression
        if ($scan['status'] !== 'done') {
            $scan['status'] = 'running';
            $scan['progress']['filesDiscovered'] += rand(10, 50);
            $scan['progress']['filesIndexed'] += rand(5, 40);
            $scan['progress']['percent'] = min(100, $scan['progress']['percent'] + rand(5, 15));
            $scan['updatedAt'] = date(DATE_ATOM);

            if ($scan['progress']['percent'] >= 100) {
                $scan['status'] = 'done';
                $scan['progress']['percent'] = 100;
            }

            $this->repository->update($scanId, $scan);
        }

        return $scan;
    } */

        public function execute(string $scanId): ?array
    {
        return $this->repository->find($scanId);
    }
}
