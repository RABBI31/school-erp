<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Models\School;
use App\Services\Fees\InvoiceGenerator;
use App\Support\CurrentSchool;
use Illuminate\Console\Command;

/**
 * Monthly invoices for every active school (or one). Safe to run again: a
 * month already billed is skipped.
 */
class GenerateFees extends Command
{
    protected $signature = 'fees:generate {month : YYYY-MM} {--school= : Only this school (slug)}';

    protected $description = 'Generate monthly fee invoices';

    public function handle(InvoiceGenerator $generator, CurrentSchool $currentSchool): int
    {
        $month = (string) $this->argument('month');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('The month must look like 2026-03.');

            return self::FAILURE;
        }

        $schools = School::query()
            ->where('is_active', true)
            ->when($this->option('school'), fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('id')
            ->get();

        if ($schools->isEmpty()) {
            $this->error('No matching active school.');

            return self::FAILURE;
        }

        $previous = $currentSchool->get();

        try {
            foreach ($schools as $school) {
                $currentSchool->set($school);

                try {
                    $result = $generator->generate($month);
                    $this->info("{$school->slug}: {$result['created']} created, {$result['skipped']} already billed.");
                } catch (BusinessRuleException $e) {
                    $this->warn("{$school->slug}: {$e->getMessage()}");
                }
            }
        } finally {
            $currentSchool->set($previous);
        }

        return self::SUCCESS;
    }
}
