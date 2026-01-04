<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Persistence\File;

use Hestia\Domain\Repository\ScanJobRepository;

final class FileScanJobRepository implements ScanJobRepository
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    public function create(array $scan): void
    {
        $this->write($scan['scanId'], $scan);
    }

    public function find(string $scanId): ?array
    {
        $path = $this->path($scanId);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public function update(string $scanId, array $data): void
    {
        // On remplace l’objet entier (simple et robuste pour le stub)
        $this->write($scanId, $data);
    }

    private function write(string $id, array $payload): void
    {
        file_put_contents(
            $this->path($id),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function path(string $scanId): string
    {
        // sécurité minimale de nom de fichier
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $scanId);
        return rtrim($this->dir, "\\/") . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}
