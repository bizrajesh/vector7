<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Layout gallery photos (projects.gallery JSON) become listing_images rows, so layouts and listings share one media system (spec 17.3). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('projects')->whereNotNull('gallery')->get(['id', 'tenant_id', 'gallery']) as $p) {
            $ids = json_decode($p->gallery, true) ?: [];
            if (! $ids || DB::table('listing_images')->where('project_id', $p->id)->exists()) {
                continue;
            }
            $sort = 0;
            foreach (DB::table('storage_files')->whereIn('id', $ids)->orderBy('id')->get() as $f) {
                DB::table('listing_images')->insert(['tenant_id' => $p->tenant_id, 'project_id' => $p->id, 'file_id' => $f->id, 'thumb_file_id' => null,
                    'sort' => ++$sort, 'is_cover' => $sort === 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Gallery JSON is left untouched by up(); nothing to undo.
    }
};
