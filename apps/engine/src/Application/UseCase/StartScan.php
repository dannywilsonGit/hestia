<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ScanJobRepository;
use Hestia\Domain\Service\IdGenerator;
use Hestia\Domain\Service\Filesystem;

final class StartScan
{
    public function __construct(
        private ScanJobRepository $repository,
        private IdGenerator $idGenerator,
        private Filesystem $filesystem,
        private int $maxDepth,
        private array $excludeNames
    ) {}

    public function execute(string $path): array
    {
        $scanId = $this->idGenerator->generateScanId();

        $files = $this->filesystem->listFiles($path, $this->maxDepth, $this->excludeNames);

        $byExt = [];
        $totalBytes = 0;

        foreach ($files as $f) {
            $ext = $f['ext'] !== '' ? $f['ext'] : 'no_ext';
            $byExt[$ext] = ($byExt[$ext] ?? 0) + 1;
            $totalBytes += (int)$f['size'];
        }

        ksort($byExt);

        $now = date(DATE_ATOM);

        $scan = [
            'scanId' => $scanId,
            'path' => $path,
            'status' => 'done',
            'progress' => [
                'filesDiscovered' => count($files),
                'filesIndexed' => count($files),
                'percent' => 100,
            ],
            'summary' => [
                'totalFiles' => count($files),
                'totalBytes' => $totalBytes,
                'byExtension' => $byExt,
            ],
            // on garde une liste de fichiers pour le plan (v1). Plus tard: index SQLite.
            'files' => $files,
            'warnings' => [],
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $this->repository->create($scan);

        return $scan;
    }
}
