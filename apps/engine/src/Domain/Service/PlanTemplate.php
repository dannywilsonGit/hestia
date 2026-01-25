<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface PlanTemplate
{
    /** Identifiant stable (ex: downloads_basic) */
    public function id(): string;

    /**
     * @param string $root Dossier racine scanné (ex: C:\Users\X\Downloads)
     * @param array<int, array{path:string,name:string,ext:string,size:int}> $files
     * @return array<int, array<string, mixed>> actions (mkdir/move/rename...)
     */
    public function buildActions(string $root, array $files): array;
}