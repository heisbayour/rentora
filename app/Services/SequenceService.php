<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sequence;
use App\Support\CurrentOrganization;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free yearly document numbers per organization (spec 5.1):
 * INV-2026-000001, RCT-2026-000001, MR-2026-000001.
 */
class SequenceService
{
    public const INVOICE = 'invoice';

    public const RECEIPT = 'receipt';

    public const MAINTENANCE = 'maintenance';

    private const PREFIXES = [
        self::INVOICE => 'INV',
        self::RECEIPT => 'RCT',
        self::MAINTENANCE => 'MR',
    ];

    public function __construct(private readonly CurrentOrganization $organization) {}

    public function next(string $key, ?CarbonInterface $date = null, ?int $organizationId = null): string
    {
        $prefix = self::PREFIXES[$key] ?? throw new \InvalidArgumentException("Unknown sequence [{$key}].");
        $organizationId ??= $this->organization->id() ?? throw new \LogicException('Sequences need an organization.');
        $year = (int) ($date ?? now())->format('Y');

        $value = DB::transaction(function () use ($organizationId, $key, $year): int {
            $row = $this->lockedRow($organizationId, $key, $year);
            $value = $row->next_value;
            $row->next_value = $value + 1;
            $row->save();

            return $value;
        });

        return sprintf('%s-%d-%06d', $prefix, $year, $value);
    }

    private function lockedRow(int $organizationId, string $key, int $year): Sequence
    {
        $query = fn () => Sequence::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('key', $key)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $row = $query();
        if ($row !== null) {
            return $row;
        }

        try {
            Sequence::query()->withoutGlobalScopes()->create([
                'organization_id' => $organizationId, 'key' => $key, 'year' => $year, 'next_value' => 1,
            ]);
        } catch (QueryException) {
            // Another request created the row first; the unique key guarantees one row.
        }

        return $query() ?? throw new \RuntimeException('Sequence row could not be locked.');
    }
}
