<?php

namespace App\Repositories;

use App\Models\Loja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Repositório de Lojas.
 */
class LojaRepository
{
    /**
     * Lista com filtros e paginação.
     * Pesquisa por nome/razão e também por CNPJ (ignorando máscara).
     *
     * @param array{q?:string|null,status?:int|null,page?:int,per_page?:int} $filters
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q        = $filters['q'] ?? null;
        $status   = $filters['status'] ?? null;
        $perPage  = (int) ($filters['per_page'] ?? 15);

        // normaliza termo para busca por cnpj sem máscara
        $qDigits = $q ? preg_replace('/\D+/', '', (string) $q) : null;

        $query = Loja::query()
            ->when($q, function ($query, $q) use ($qDigits) {
                $query->where(function ($sub) use ($q, $qDigits) {
                    $sub->where('nome', 'like', "%{$q}%")
                        ->orWhere('razao', 'like', "%{$q}%");

                    if ($qDigits) {
                        // Busca CNPJ indiferente a máscara (MySQL compatível)
                        $sub->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '-', ''), '/', ''), '(', ''), ')', ''), ' ', '') LIKE ?",
                            ['%'.$qDigits.'%']
                        );
                    } else {
                        // fallback com LIKE simples
                        $sub->orWhere('cnpj', 'like', "%{$q}%");
                    }
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', (int) $status))
            ->orderBy('nome');

        return $query->paginate($perPage);
    }

    /**
     * Lista todas as ativas ordenadas por nome.
     *
     * @return Collection<int,Loja>
     */
    public function allAtivas(): Collection
    {
        return Loja::ativas()->orderBy('nome')->get();
    }

    /**
     * Busca uma loja.
     *
     * @param int $id
     * @return Loja|null
     */
    public function find(int $id): ?Loja
    {
        return Loja::find($id);
    }

    /**
     * Cria uma loja.
     *
     * @param array $data
     * @return Loja
     */
    public function create(array $data): Loja
    {
        return Loja::create($data);
    }

    /**
     * Atualiza uma loja.
     *
     * @param Loja $loja
     * @param array $data
     * @return Loja
     */
    public function update(Loja $loja, array $data): Loja
    {
        $loja->fill($data)->save();
        return $loja;
    }

    /**
     * Exclui uma loja (e deleta arquivo da logomarca se for nosso).
     *
     * @param Loja $loja
     * @return void
     */
    public function deleteComArquivo(Loja $loja): void
    {
        $this->removerArquivoSeNosso($loja->logomarca);
        $loja->delete();
    }

    /**
     * Remove arquivo de logomarca do disco público se o arquivo pertencer a nós.
     * Considera "nosso" quando NÃO é URL http(s).
     *
     * @param string|null $nomeArquivo
     * @return void
     */
    public function removerArquivoSeNosso(?string $nomeArquivo): void
    {
        if (!$nomeArquivo) {
            return;
        }
        if (preg_match('~^https?://~i', (string) $nomeArquivo)) {
            return; // não é nosso, ignorar
        }
        $path = 'lojas/'.$nomeArquivo;
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Armazena arquivo de logomarca e retorna o nome final (hash.ext).
     *
     * @param UploadedFile $arquivo
     * @return string nome do arquivo salvo (ex.: "a1b2c3...ff.png")
     */
    public function armazenarLogomarca(UploadedFile $arquivo): string
    {
        $contents = file_get_contents($arquivo->getRealPath());
        $hash = hash('sha256', $contents);

        $ext = strtolower($arquivo->getClientOriginalExtension() ?: ($arquivo->guessExtension() ?: 'bin'));
        $filename = $hash . '.' . $ext;
        $path = 'lojas/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->putFileAs('lojas', $arquivo, $filename);
        }

        return $filename;
    }
}
