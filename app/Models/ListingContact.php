<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Contact block of a listing or a layout (owner_type = listing | project). */
class ListingContact extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allow_call' => 'boolean', 'allow_whatsapp' => 'boolean', 'allow_email' => 'boolean'];
    }

    public function modes(): array
    {
        return array_keys(array_filter(['call' => $this->allow_call, 'whatsapp' => $this->allow_whatsapp, 'email' => $this->allow_email]));
    }

    /** 9840012345 → 98XXX XX345 */
    public static function mask(?string $mobile): string
    {
        $d = preg_replace('/\D/', '', (string) $mobile);
        if (strlen($d) < 10) {
            return 'XXXXX XXXXX';
        }
        $d = substr($d, -10);

        return substr($d, 0, 2).'XXX XX'.substr($d, -3);
    }

    public function whatsappNumber(): string
    {
        $d = preg_replace('/\D/', '', (string) ($this->whatsapp ?: $this->mobile));

        return '91'.substr($d, -10);
    }
}
