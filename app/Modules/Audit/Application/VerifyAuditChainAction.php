<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditChain;
use App\Modules\Audit\Infrastructure\Models\AuditLog;

/**
 * Walks the whole trail in order and reports the first entry that does not
 * match its signature or does not follow the previous one. A deleted entry
 * shows up as a broken link at the entry that followed it.
 */
final class VerifyAuditChainAction
{
    /**
     * @return array{valid: bool, checked: int, broken_at: int|null, reason: string|null}
     */
    public function execute(): array
    {
        $previous = AuditChain::GENESIS;
        $checked = 0;
        $key = RecordAuditEntryAction::key();

        $result = ['valid' => true, 'checked' => 0, 'broken_at' => null, 'reason' => null];

        AuditLog::query()->orderBy('id')->chunkById(500, function ($entries) use (&$previous, &$checked, $key, &$result) {
            foreach ($entries as $entry) {
                if ($entry->previous_hash !== $previous) {
                    $result = ['valid' => false, 'checked' => $checked, 'broken_at' => $entry->id, 'reason' => 'Chaînage rompu : une entrée précédente a été supprimée ou déplacée.'];

                    return false;
                }

                if (! AuditChain::isValid($entry->signedContent(), $previous, $entry->hash, $key)) {
                    $result = ['valid' => false, 'checked' => $checked, 'broken_at' => $entry->id, 'reason' => 'Signature invalide : le contenu de cette entrée a été modifié.'];

                    return false;
                }

                $previous = $entry->hash;
                $checked++;
            }

            return true;
        });

        $result['checked'] = $result['valid'] ? $checked : $result['checked'];

        return $result;
    }
}
