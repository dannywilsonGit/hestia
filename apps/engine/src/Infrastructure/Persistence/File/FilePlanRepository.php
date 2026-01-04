<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Persistence\File;

use Hestia\Domain\Repository\PlanRepository;

final class FilePlanRepository implements PlanRepository
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    public function create(array $plan): void
    {
        $this->write($plan['planId'], $plan);
    }

    public function find(string $planId): ?array
    {
        $path = $this->path($planId);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function write(string $id, array $payload): void
    {
        file_put_contents(
            $this->path($id),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function path(string $planId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $planId);
        return rtrim($this->dir, "\\/") . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}
