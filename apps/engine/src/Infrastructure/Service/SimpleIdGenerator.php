<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\IdGenerator;

final class SimpleIdGenerator implements IdGenerator
{
    public function generateScanId(): string
    {
        return 'scn_' . bin2hex(random_bytes(8));
    }

    public function generatePlanId(): string
    {
        return 'pln_' . bin2hex(random_bytes(8));
    }

    public function generateApplyId(): string
    {
        return 'app_' . bin2hex(random_bytes(8));
    }
}
