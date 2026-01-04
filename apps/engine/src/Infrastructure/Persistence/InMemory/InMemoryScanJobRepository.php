<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Persistence\InMemory;

use Hestia\Domain\Repository\ScanJobRepository;

final class InMemoryScanJobRepository implements ScanJobRepository
{
    private array $storage = [];

    public function create(array $scan): void
    {
        $this->storage[$scan['scanId']] = $scan;
    }

    public function find(string $scanId): ?array
    {
        return $this->storage[$scanId] ?? null;
    }

    public function update(string $scanId, array $data): void
    {
        if (!isset($this->storage[$scanId])) {
            return;
        }
        $this->storage[$scanId] = array_merge($this->storage[$scanId], $data);
    }
}
