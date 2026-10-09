<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginService;
use App\Support\Excel;
use Illuminate\Http\Request;

/**
 * "Generate password" on IAM screens (spec 4.5): 12-character password shown once with Copy,
 * optional email, optional forced change at next login, audit-logged, other sessions logged out.
 * Bulk: one-time Excel of name, email, password (streamed, never stored on the server).
 */
trait GeneratesPasswords
{
    protected function generateFor(Request $request, User|Customer $target)
    {
        $actor = $request->user('web');
        abort_unless($actor->canGeneratePasswordFor($target), 403, 'You cannot generate a password for this account.');
        $plain = LoginService::generatePassword($target, $request->boolean('must_change', true), $request->boolean('email_user'), $actor);

        return back()->with('generated', [
            'name' => $target->name,
            'email' => $target->email,
            'password' => $plain,
            'emailed' => $request->boolean('email_user'),
            'must_change' => $request->boolean('must_change', true),
        ]);
    }

    /** @param iterable<User|Customer> $targets */
    protected function bulkGenerate(Request $request, iterable $targets, string $filename)
    {
        $actor = $request->user('web');
        $rows = [];
        foreach ($targets as $t) {
            if (! $actor->canGeneratePasswordFor($t)) {
                continue;
            }
            $rows[] = [$t->name, $t->email, LoginService::generatePassword($t, $request->boolean('must_change', true), false, $actor)];
        }
        if (! $rows) {
            return back()->with('error', 'No selected account can receive a generated password.');
        }
        AuditLogger::log('bulk_passwords_generated', null, null, ['count' => count($rows)], $actor->tenant_id);

        return Excel::download($filename, ['Name', 'Email', 'Password'], $rows, 'One-time passwords — keep this file safe and delete it after sharing');
    }
}
