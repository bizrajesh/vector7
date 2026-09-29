<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlotStatusHistory extends Model
{
    use AppendOnly;
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'plot_status_history';

    protected $fillable = ['plot_id', 'from_status', 'to_status', 'reason', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
