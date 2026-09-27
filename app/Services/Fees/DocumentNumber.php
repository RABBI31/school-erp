<?php

namespace App\Services\Fees;

use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Sequential invoice and receipt numbers per school per year, e.g.
 * R-2026-000123 (business-rules §3). Call inside a transaction: the school
 * row stays locked until commit, so two requests never take the same number.
 * The unique index on (school_id, number) is the backstop.
 */
class DocumentNumber
{
    public const INVOICE = 'INV';

    public const RECEIPT = 'R';

    private const COLUMNS = [
        self::INVOICE => ['invoices', 'invoice_no'],
        self::RECEIPT => ['payments', 'receipt_no'],
    ];

    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * Lock the school's numbering until the transaction commits.
     */
    public function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be taken inside a transaction.');
        }

        School::query()->whereKey($this->currentSchool->id())->lockForUpdate()->first();
    }

    /**
     * @return list<string> $count consecutive numbers
     */
    public function take(string $kind, int $year, int $count = 1): array
    {
        $this->lock();

        if ($count < 1) {
            return [];
        }

        [$table, $column] = self::COLUMNS[$kind];
        $schoolId = $this->currentSchool->id();
        $prefix = "{$kind}-{$year}-";

        // Soft-deleted rows keep their numbers, so they are counted too.
        $last = DB::table($table)->where('school_id', $schoolId)->where($column, 'like', $prefix.'%')->max($column);
        $next = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return array_map(fn (int $n) => $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT), range($next, $next + $count - 1));
    }
}
