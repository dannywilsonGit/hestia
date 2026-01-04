<?php
declare(strict_types=1);

namespace Hestia\Domain\Repository;

interface ScanJobRepository
{
    public function create(array $scan): void;

    public function find(string $scanId): ?array;

    public function update(string $scanId, array $data): void;
}
