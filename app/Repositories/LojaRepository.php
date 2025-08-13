<?php

namespace App\Repositories;

use App\Models\Loja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LojaRepository
{
    /** Lista com filtros e paginação, usando campos legados. */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q       = $filters['q'] ?? null;
        $status  = $filters['status'] ?? null;
        $perPage = (int)($filters['per_page'] ?? 15);

        return Loja::query()
            ->when($q, function ($query, $q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nome', 'like', "%{$q}%")
                        ->orWhere('razao', 'like', "%{$q}%")
                        ->orWhere('cnpj', 'like', "%{$q}%");
                });
            })
            ->when($status !== null, fn($query) => $query->where('status', (int)$status))
            ->orderBy('nome')
            ->paginate($perPage);
    }

    /** Para dropdowns. */
    public function allAtivas(): Collection
    {
        return Loja::ativas()->orderBy('nome')->get();
    }

    public function find(int $id): ?Loja
    {
        return Loja::find($id);
    }

    public function create(array $data): Loja
    {
        return Loja::create($data);
    }

    public function update(Loja $loja, array $data): Loja
    {
        $loja->fill($data)->save();
        return $loja;
    }

    public function delete(Loja $loja): void
    {
        $loja->delete();
    }
}
