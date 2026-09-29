<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates the platform Super Admin interactively (no default credentials exist anywhere). */
class CreateSuperAdmin extends Command
{
    protected $signature = 'vector7:create-super-admin';

    protected $description = 'Create a platform Super Admin account';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password (min 10 chars, upper, lower, number, symbol)');

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = Role::SuperAdmin;
        $user->status = 'active';
        $user->tenant_id = null;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Super Admin {$email} created.");

        return self::SUCCESS;
    }
}
