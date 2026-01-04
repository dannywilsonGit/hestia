<?php
declare(strict_types=1);

namespace Hestia\Domain\Repository;

interface ApplyRunRepository
{
    public function create(array $apply): void;
    public function find(string $applyId): ?array;
    public function update(string $applyId, array $apply): void;
}
