<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ScanJobRepository;
use Hestia\Domain\Service\IdGenerator;

final class StartScan
{
    public function __construct(
        private ScanJobRepository $repository,
        private IdGenerator $idGenerator
    ) {}

    public function execute(string $path): array
    {
        $scanId = $this->idGenerator->generateScanId();

        $scan = [
            'scanId' => $scanId,
            'path' => $path,
            'status' => 'queued',
            'progress' => [
                'filesDiscovered' => 0,
                'filesIndexed' => 0,
                'percent' => 0
            ],
            'createdAt' => date(DATE_ATOM),
            'updatedAt' => date(DATE_ATOM)
        ];

        $this->repository->create($scan);

        return $scan;
    }
}
