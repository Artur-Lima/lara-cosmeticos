<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email : E-mail de um usuário já cadastrado} {--revoke : Volta o usuário para cliente}';

    protected $description = 'Promove (ou rebaixa com --revoke) um usuário a administrador da loja';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado. Ele precisa se cadastrar no site antes.');

            return self::FAILURE;
        }

        $user->role = $this->option('revoke') ? UserRole::Cliente : UserRole::Admin;
        $user->save();

        $this->info("{$user->email} agora é {$user->role->label()}.");

        return self::SUCCESS;
    }
}
