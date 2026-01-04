<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Persistence\InMemory;

use Hestia\Domain\Repository\PlanRepository;

final class InMemoryPlanRepository implements PlanRepository
{
    private array $storage = [];

    public function create(array $plan): void
    {
        $this->storage[$plan['planId']] = $plan;
    }

    public function find(string $planId): ?array
    {
        return $this->storage[$planId] ?? null;
    }
}
