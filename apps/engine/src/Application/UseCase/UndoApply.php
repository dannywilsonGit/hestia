<?php
declare(strict_types=1);

namespace Hestia\Application\UseCase;

use Hestia\Domain\Repository\ApplyRunRepository;

final class UndoApply
{
    public function __construct(
        private ApplyRunRepository $applyRepo
    ) {}

    public function execute(string $applyId): array
    {
        $apply = $this->applyRepo->find($applyId);
        if (!$apply) {
            return ['error' => 'APPLY_NOT_FOUND'];
        }

        // Stub: on "annule" en mettant un flag
        $apply['undo'] = [
            'status' => 'queued',
            'createdAt' => date(DATE_ATOM),
            'updatedAt' => date(DATE_ATOM),
        ];

        $this->applyRepo->update($applyId, $apply);

        return [
            'applyId' => $applyId,
            'status' => 'queued'
        ];
    }
}
