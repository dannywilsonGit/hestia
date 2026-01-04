<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Persistence\File;

use Hestia\Domain\Repository\ApplyRunRepository;

final class FileApplyRunRepository implements ApplyRunRepository
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    public function create(array $apply): void
    {
        $this->write($apply['applyId'], $apply);
    }

    public function find(string $applyId): ?array
    {
        $path = $this->path($applyId);
        if (!is_file($path)) return null;

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public function update(string $applyId, array $apply): void
    {
        $this->write($applyId, $apply);
    }

    private function write(string $id, array $payload): void
    {
        file_put_contents(
            $this->path($id),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function path(string $applyId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $applyId);
        return rtrim($this->dir, "\\/") . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}
