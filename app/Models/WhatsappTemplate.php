<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    /** Replace {name}, {listing}, {promoter} placeholders. */
    public function render(array $vars): string
    {
        return strtr($this->body, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }
}
