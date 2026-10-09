<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\StorageFile;
use App\Models\Tenant;
use App\Models\TicketMessage;
use App\Services\FileStore;
use Illuminate\Http\Request;

/** Files are never public: served through permission-checked routes. */
class FileController extends Controller
{
    /** Staff: App users may open any file; tenant users only files of their own tenant. */
    public function show(Request $request, StorageFile $file)
    {
        $user = $request->user('web');
        if (! $user->isAppUser() && (int) $file->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        return $this->stream($file, $request->boolean('download'));
    }

    /** Customers: only files attached to their own registration, payments or tickets. */
    public function customerFile(Request $request, StorageFile $file)
    {
        $c = $request->user('customer');
        $ok = match ($file->attachable_type) {
            Registration::class => Registration::withoutGlobalScopes()->where('id', $file->attachable_id)->where('customer_id', $c->id)->exists(),
            Payment::class => Payment::withoutGlobalScopes()->where('id', $file->attachable_id)->where('customer_id', $c->id)->exists(),
            TicketMessage::class => TicketMessage::where('id', $file->attachable_id)->whereHas('ticket', fn ($q) => $q->withoutGlobalScopes()->where('requester_type', 'customer')->where('requester_id', $c->id))->exists(),
            default => false,
        };
        abort_unless($ok, 404);

        return $this->stream($file, $request->boolean('download'));
    }

    /** Tenant logos appear on the public marketplace, emails and PDFs. */
    public function tenantLogo(Tenant $tenant)
    {
        $file = $tenant->logo_path ? StorageFile::where('tenant_id', $tenant->id)->where('path', $tenant->logo_path)->first() : null;
        abort_unless($file, 404);

        return response(FileStore::contents($file), 200, [
            'Content-Type' => $file->mime,
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function stream(StorageFile $file, bool $download)
    {
        $inline = ! $download && (str_starts_with($file->mime, 'image/') || $file->mime === 'application/pdf');
        $name = str_replace(['"', "\r", "\n"], '', $file->original_name);

        return response(FileStore::contents($file), 200, [
            'Content-Type' => $file->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
