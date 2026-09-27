<?php

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Services\Fees\PaymentService;
use Illuminate\Support\Facades\DB;

/*
 * Real concurrency: forked processes, each with its own MySQL connection,
 * collect payments at the same time. The data must be committed for the other
 * processes to see it, so this suite truncates instead of wrapping each test
 * in a transaction (see tests/Pest.php) and cleans up after itself.
 */

afterEach(function () {
    DB::reconnect();
    $this->truncateTablesForAllConnections();
});

// business-rules §3, required: receipt numbers are unique under concurrent requests
it('gives every payment a unique receipt number under concurrent requests', function () {
    feeWorld();
    generateInvoices('2026-01', '2026-02', '2026-03');

    $workers = 4;
    $perWorker = 5;
    DB::disconnect(); // children must not share the parent's socket

    $pids = [];
    for ($w = 0; $w < $workers; $w++) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            $code = 0;
            try {
                DB::reconnect();
                for ($i = 0; $i < $perWorker; $i++) {
                    app(PaymentService::class)->collect($this->enrollment, '10', PaymentMethod::Cash, $this->accountant);
                }
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage()."\n");
                $code = 1;
            }
            DB::disconnect();
            posix_kill(getmypid(), $code === 0 ? SIGKILL : SIGTERM); // skip PHPUnit's shutdown in the child
        }

        $pids[] = $pid;
    }

    $signals = [];
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $signals[] = pcntl_wtermsig($status);
    }

    DB::reconnect();

    expect($signals)->each->toBe(SIGKILL) // SIGTERM = a worker failed (e.g. duplicate receipt number)
        ->and(Payment::count())->toBe($workers * $perWorker)
        ->and(Payment::distinct()->count('receipt_no'))->toBe($workers * $perWorker)
        ->and(Payment::orderBy('receipt_no')->pluck('receipt_no')->last())->toBe(sprintf('R-2026-%06d', $workers * $perWorker))
        ->and($this->enrollment->invoices()->sum('paid_total'))->toEqual($workers * $perWorker * 10);
})->skip(! function_exists('pcntl_fork'), 'needs the pcntl extension');
