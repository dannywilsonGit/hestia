<?php
declare(strict_types=1);

namespace Hestia\Domain\Repository;

interface PlanRepository
{
    public function create(array $plan): void;

    public function find(string $planId): ?array;
}
