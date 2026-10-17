<?php

namespace App\Services;

/** Starter plans for property listers (used by the migration on existing databases and by PlatformSeeder). */
class ListerPlans
{
    /** @return array<int, array{0:string,1:string,2:string,3:int,4:bool,5:array<string,int>}> */
    public static function defaults(): array
    {
        $base = ['projects' => 0, 'plots' => 0, 'users' => 1, 'users_tenant_admin' => -1, 'users_tenant_manager' => -1, 'users_tenant_sales' => -1, 'users_tenant_account' => -1, 'users_support' => -1];

        return [
            ['Lister Basic', 'lister-basic', 'For owners listing a few properties.', 199, true, $base + ['storage_mb' => 512, 'listings' => 10, 'images_per_listing' => 12, 'ai_credits' => 20]],
            ['Lister Plus', 'lister-plus', 'For agents and owners with many properties.', 499, false, $base + ['storage_mb' => 2048, 'listings' => 30, 'images_per_listing' => 20, 'ai_credits' => 50]],
            ['Lister Pro', 'lister-pro', 'For busy agents: more listings, photos and AI help.', 999, false, $base + ['storage_mb' => 5120, 'listings' => 100, 'images_per_listing' => 20, 'ai_credits' => 100]],
        ];
    }
}
