<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Crear un nuevo usuario
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * Encontrar usuario por email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Encontrar usuario por ID
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User;
}
