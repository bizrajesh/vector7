<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Instalment;
use App\Models\Plot;
use App\Support\Format;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/** Jobs run by the single cron entry (php artisan schedule:run every minute). */
class Scheduler
{
    /** Release bookings whose 1st instalment was not paid within the validity period; remind 1 working day before. */
    public static function bookings(): array
    {
        return app(Tenancy::class)->withoutScope(function () {
            $released = 0;
            $reminded = 0;
            foreach (Booking::where('status', 'active')->with(['plot', 'project', 'customer'])->get() as $b) {
                $data = [
                    'name' => $b->customer->name, 'booking_no' => $b->booking_no, 'plot_no' => $b->plot->plot_no, 'project' => $b->project->name,
                    'valid_till' => Format::date($b->valid_till), 'first_amount' => Format::inr($b->firstInstalmentAmount()),
                ];
                if (today()->gt($b->valid_till)) {
                    SalesService::releaseBooking($b, 'expired', 'Booking expired — 1st instalment not paid');
                    Notify::send('booking_released', [$b->customer->email], $data, $b->tenant_id);
                    Notify::groups($b->tenant_id, ['Sales Team'], 'booking_released', $data);
                    $released++;
                } elseif (! $b->reminder_sent_at && today()->gte(WorkingDays::for($b->tenant_id)->previous($b->valid_till))) {
                    Notify::send('booking_expiring', [$b->customer->email], $data, $b->tenant_id);
                    Notify::groups($b->tenant_id, ['Sales Team'], 'booking_expiring', $data);
                    $b->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                    $reminded++;
                }
            }

            return ['released' => $released, 'reminded' => $reminded];
        });
    }

    /** Nightly: hide expired offers (the actual price shows again). */
    public static function offers(): int
    {
        return app(Tenancy::class)->withoutScope(function () {
            Plot::whereNotNull('offer_valid_till')->whereDate('offer_valid_till', '>=', today())->whereNotNull('offer_rate_per_sqft')->update(['offer_active' => true]);

            return Plot::where('offer_active', true)->whereDate('offer_valid_till', '<', today())->update(['offer_active' => false]);
        });
    }

    /** Instalment due reminders (1 working day before) and overdue notices. */
    public static function instalments(): array
    {
        return app(Tenancy::class)->withoutScope(function () {
            $due = 0;
            $overdue = 0;
            foreach (Instalment::where('status', '!=', 'paid')->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))->with(['sale.customer', 'sale.plot', 'sale.project'])->get() as $i) {
                $s = $i->sale;
                $data = ['name' => $s->customer->name, 'instalment' => $i->name, 'amount' => Format::inr($i->amount), 'balance' => Format::inr($i->balance()),
                    'plot_no' => $s->plot->plot_no, 'project' => $s->project->name, 'due_date' => Format::date($i->due_date), 'sale_no' => $s->sale_no];
                if ($i->due_date->lt(today()) && ! $i->overdue_notified_at) {
                    Notify::send('instalment_overdue', [$s->customer->email], $data, $s->tenant_id);
                    Notify::groups($s->tenant_id, ['Sales Team', 'Accounts'], 'instalment_overdue', $data);
                    $i->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                    $overdue++;
                } elseif (! $i->reminder_sent_at && $i->due_date->gte(today()) && today()->gte(WorkingDays::for($s->tenant_id)->previous($i->due_date))) {
                    Notify::send('instalment_due', [$s->customer->email], $data, $s->tenant_id);
                    $i->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                    $due++;
                }
            }

            return ['due' => $due, 'overdue' => $overdue];
        });
    }

    /** Daily backup: database dump + uploaded files, zipped, saved to the selected storage driver; 7 local copies kept. */
    public static function backup(): string
    {
        $dir = storage_path('app/private/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stamp = now()->format('Ymd-His');
        $sqlPath = "$dir/db-$stamp.sql";
        $fh = fopen($sqlPath, 'w');
        fwrite($fh, "-- vector7 backup $stamp\nSET FOREIGN_KEY_CHECKS=0;\n");
        $pdo = DB::connection()->getPdo();
        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $create = (array) DB::selectOne("SHOW CREATE TABLE `$table`");
            fwrite($fh, "\nDROP TABLE IF EXISTS `$table`;\n".array_values($create)[1].";\n");
            foreach (DB::table($table)->cursor() as $r) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $r);
                fwrite($fh, "INSERT INTO `$table` VALUES (".implode(',', $vals).");\n");
            }
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        $zipPath = "$dir/vector7-backup-$stamp.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sqlPath, 'database.sql');
        $base = storage_path('app/private');
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $rel = substr($file->getPathname(), strlen($base) + 1);
            if (! str_starts_with($rel, 'backups') && ! str_starts_with($rel, 'receipts')) {
                $zip->addFile($file->getPathname(), 'files/'.$rel);
            }
        }
        $zip->close();
        @unlink($sqlPath);

        if (AppSettings::get('storage.driver', 'local') !== 'local') {
            FileStore::driver()->put('backups/'.basename($zipPath), (string) file_get_contents($zipPath), 'application/zip');
        }
        $old = collect(glob("$dir/vector7-backup-*.zip"))->sort()->values();
        foreach ($old->slice(0, max(0, $old->count() - 7)) as $f) {
            @unlink($f);
        }

        return $zipPath;
    }
}
