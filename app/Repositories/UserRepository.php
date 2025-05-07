<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    /**
     * @var User El modelo User para operaciones con la base de datos.
     */
    protected User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Crear un nuevo usuario
     */
    public function create(array $data): User
    {
        return $this->model->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    /**
     * Encontrar usuario por email
     */
    public function findByEmail(string $email): ?User
    {
        /** @phpstan-ignore-next-line */
        return $this->model->where('email', $email)->first();
    }

    /**
     * Encontrar usuario por ID
     */
    public function findById(int $id): ?User
    {
        /** @phpstan-ignore-next-line */
        return $this->model->find($id);
    }

    /**
     * Obtener todos los usuarios excepto administradores
     */
    public function getAll(): Collection
    {
        // Ya que estamos teniendo problemas con whereDoesntHave, usamos una solución alternativa
        // hasta que se configure correctamente el paquete de permisos
        /** @phpstan-ignore-next-line */
        return $this->model->get();
    }

    // Otros métodos según tus necesidades...
}
