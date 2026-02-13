<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:super-admin {email : E-Mail-Adresse des Benutzers} {--revoke : Entzieht Super-Admin-Rechte}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vergibt oder entzieht Super-Admin-Rechte fuer einen Benutzer.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $normalizedEmail = mb_strtolower($email);
        $revoke = (bool) $this->option('revoke');
        $targetState = ! $revoke;

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if (! $user) {
            $this->components->error("Kein Benutzer mit E-Mail {$email} gefunden.");

            return self::FAILURE;
        }

        if ((bool) $user->is_super_admin === $targetState) {
            $this->components->info($targetState
                ? "Benutzer {$user->email} ist bereits Super-Admin."
                : "Benutzer {$user->email} hat bereits keine Super-Admin-Rechte.");

            return self::SUCCESS;
        }

        $user->forceFill([
            'is_super_admin' => $targetState,
        ])->save();

        $this->components->info($targetState
            ? "Super-Admin-Rechte fuer {$user->email} wurden vergeben."
            : "Super-Admin-Rechte fuer {$user->email} wurden entzogen.");

        return self::SUCCESS;
    }
}
