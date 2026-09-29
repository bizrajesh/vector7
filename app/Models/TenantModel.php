<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every tenant-owned record: tenant isolation + audit trail.
 * tenant_id is never mass-assignable; it is stamped from the tenant context.
 */
abstract class TenantModel extends Model
{
    use Auditable;
    use BelongsToTenant;
}
