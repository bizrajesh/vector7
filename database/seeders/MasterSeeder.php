<?php

namespace Database\Seeders;

use App\Models\StageMaster;
use App\Services\TemplateImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

/** App-level masters (tenant_id NULL) from the attached pre-configuration template. */
class MasterSeeder extends Seeder
{
    public function run(): void
    {
        if (StageMaster::withoutGlobalScopes()->whereNull('tenant_id')->exists()) {
            return;
        }
        $importer = TemplateImporter::fromFile(database_path('seeders/data/Vector7_Tenant_PreConfig_Template.xlsx'));
        if (! $importer->validate(false)) {
            throw new RuntimeException('Template invalid: '.json_encode(array_slice($importer->errors, 0, 5)));
        }
        $summary = $importer->import(null);
        $this->command?->info('App masters imported: '.json_encode($summary));
    }
}
