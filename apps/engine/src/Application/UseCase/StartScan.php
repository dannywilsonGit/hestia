<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ScanJobRepository;
use Hestia\Domain\Service\IdGenerator;
use Hestia\Domain\Service\Filesystem;
use Hestia\Domain\Service\TextExtractor;
use Hestia\Domain\Service\DocumentClassifier;


final class StartScan
{
    public function __construct(
        private ScanJobRepository $repository,
        private IdGenerator $idGenerator,
        private Filesystem $filesystem,
        private TextExtractor $textExtractor,
        private int $maxDepth,
        private array $excludeNames,
        private DocumentClassifier $classifier
        
    ) {}

    public function execute(string $path): array
    {
        $scanId = $this->idGenerator->generateScanId();

        $files = $this->filesystem->listFiles($path, $this->maxDepth, $this->excludeNames);

        // Enrichissement IA (MVP): preview texte pour txt/md
        foreach ($files as &$f) {
            $preview = $this->textExtractor->extractPreview((string) $f['path']);
            if ($preview === null) {
                $f['content'] = ['status' => 'none', 'mime' => 'unknown', 'preview' => ''];
            } else {
                $f['content'] = $preview;
            }


            if (($preview['status'] ?? '') === 'extracted') {
                $f['classification'] = $this->classifier->classify(
                    (string) ($preview['preview'] ?? ''),
                    [
                        'name' => (string) ($f['name'] ?? ''),
                        'ext' => (string) ($f['ext'] ?? ''),
                        'path' => (string) ($f['path'] ?? ''),
                    ]
                );
            } else {
                $f['classification'] = [
                    'category' => 'Autres',
                    'confidence' => 0.0,
                    'signals' => ['content not extracted'],
                ];
            }
        }
        unset($f);

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
