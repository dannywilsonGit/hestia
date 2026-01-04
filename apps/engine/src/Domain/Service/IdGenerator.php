<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface IdGenerator
{
    public function generateScanId(): string;
    public function generatePlanId(): string;
    public function generateApplyId(): string;
}
