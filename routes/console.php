<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('clovic:owner {email}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email.');

        return 1;
    }
    $user = User::where('email', $email)->first();
    if ($user) {
        if (! $this->confirm('Grant owner access to this existing account?', false)) {
            return 1;
        }
    } else {
        $name = $this->ask('Owner name');
        $password = $this->secret('Password (at least 12 characters, letters and numbers)');
        if (strlen($password) < 12 || ! preg_match('/[a-z]/i', $password) || ! preg_match('/[0-9]/', $password)) {
            $this->error('Password does not meet the requirements.');

            return 1;
        }
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
    }
    $user->forceFill(['role' => 'owner', 'active' => true])->save();
    $this->info('Owner access granted.');
})->purpose('Create or promote a store owner without default credentials');
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->daily();
