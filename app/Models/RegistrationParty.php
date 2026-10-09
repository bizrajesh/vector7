<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RegistrationParty extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $hidden = ['pan_encrypted'];

    public function setPan(?string $pan): void
    {
        $this->pan_encrypted = $pan ? \Illuminate\Support\Facades\Crypt::encryptString(strtoupper(trim($pan))) : null;
    }

    public function pan(): ?string
    {
        return $this->pan_encrypted ? \Illuminate\Support\Facades\Crypt::decryptString($this->pan_encrypted) : null;
    }

    /** Masked PAN (ABCDE****F) unless the viewer is an admin. */
    public function panFor(?User $viewer): ?string
    {
        $pan = $this->pan();
        if (! $pan) { return null; }
        if ($viewer && $viewer->isAdmin()) { return $pan; }
        return substr($pan, 0, 5).'****'.substr($pan, -1);
    }
}
