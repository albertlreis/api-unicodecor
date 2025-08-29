<?php

namespace App\Policies;

use App\Models\Usuario;

class GaleriaPolicy
{
    /**
     * Apenas Administrador (perfil_id = 1) pode gerenciar.
     *
     * @param  Usuario  $user
     * @return bool
     */
    public function manage(Usuario $user): bool
    {
        return (int) $user->id_perfil === 1;
    }
}
