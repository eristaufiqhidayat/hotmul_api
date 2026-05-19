<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    /**
     * Create user baru
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Cari user berdasarkan email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Cari user berdasarkan ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Update user
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }
}
