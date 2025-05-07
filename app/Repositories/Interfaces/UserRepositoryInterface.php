<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Crear un nuevo usuario
     */
    public function create(array $data): User;

    /**
     * Encontrar usuario por email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Encontrar usuario por ID
     */
    public function findById(int $id): ?User;
}
