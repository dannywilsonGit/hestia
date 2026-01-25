<?php
declare(strict_types=1);

namespace Hestia\Application\Service;

use Hestia\Domain\Service\PlanTemplate;

final class TemplateRegistry
{
    /** @var array<string, PlanTemplate> */
    private array $templates = [];

    /** @param PlanTemplate[] $templates */
    public function __construct(array $templates)
    {
        foreach ($templates as $t) {
            $this->templates[$t->id()] = $t;
        }
    }

    public function get(string $id): ?PlanTemplate
    {
        return $this->templates[$id] ?? null;
    }

    /** @return string[] */
    public function listIds(): array
    {
        return array_keys($this->templates);
    }
}