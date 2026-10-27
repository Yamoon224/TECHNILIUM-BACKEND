<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\AuditChain;
use App\Modules\Audit\Infrastructure\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single way an entry enters the trail. It appends under a lock on the
 * chain's tail, so two simultaneous requests cannot both sign against the
 * same previous entry and fork the chain.
 */
final class RecordAuditEntryAction
{
    /**
     * @param  array<string, mixed>  $attributes  user_id, user_name, roles, method, path, route, status, payload, ip, user_agent
     */
    public function execute(array $attributes, ?Carbon $at = null): AuditLog
    {
        return DB::transaction(function () use ($attributes, $at) {
            $tail = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();
            $previous = $tail?->hash ?? AuditChain::GENESIS;

            $entry = new AuditLog($attributes);
            // Second precision, as stored, so what is signed is what is read back.
            $entry->occurred_at = ($at ?? now())->startOfSecond();

            $entry->previous_hash = $previous;
            $entry->hash = AuditChain::sign($entry->signedContent(), $previous, self::key());
            $entry->save();

            return $entry;
        });
    }

    public static function key(): string
    {
        return (string) config('app.key');
    }
}
