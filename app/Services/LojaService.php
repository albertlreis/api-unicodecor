<?php

namespace App\Services;

use App\Models\Loja;
use App\Repositories\LojaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LojaService
{
    public function __construct(
        private readonly LojaRepository $repo
    ) {}

    public function listarPaginado(array $filters): LengthAwarePaginator
    {
        return $this->repo->paginate($filters);
    }

    public function criar(array $payload): Loja
    {
        $data = $this->mapApiToDb($payload);
        $data = $this->processarUpload($data);
        return $this->repo->create($data);
    }

    public function atualizar(Loja $loja, array $payload): Loja
    {
        $data = $this->mapApiToDb($payload);
        $data = $this->processarUpload($data, $loja);
        return $this->repo->update($loja, $data);
    }

    public function remover(Loja $loja): void
    {
        $this->repo->delete($loja);
    }

    /** Converte nomes modernos da API para colunas legadas do DB. */
    private function mapApiToDb(array $data): array
    {
        $map = [
            'razao_social'  => 'razao',
            'nome_fantasia' => 'nome',
            'site'          => 'eletronico',
        ];

        foreach ($map as $apiKey => $dbKey) {
            if (array_key_exists($apiKey, $data)) {
                $data[$dbKey] = $data[$apiKey];
                unset($data[$apiKey]);
            }
        }

        if (!array_key_exists('status', $data)) {
            $data['status'] = 1;
        }

        return $data;
    }

    /** Upload no disco public ou preserva URL absoluta vinda do legado. */
    private function processarUpload(array $data, ?Loja $loja = null): array
    {
        /** @var UploadedFile|string|null $arquivo */
        $arquivo = $data['logomarca'] ?? null;
        $remover = (bool)($data['remover_logomarca'] ?? false);

        // Se vier uma string http(s) do legado, mantém
        if (is_string($arquivo) && preg_match('~^https?://~i', $arquivo)) {
            // mantemos como está
        } elseif ($arquivo instanceof UploadedFile) {
            if ($loja?->logomarca && !preg_match('~^https?://~i', (string)$loja->logomarca)) {
                Storage::disk('public')->delete($loja->logomarca);
            }
            $data['logomarca'] = $arquivo->store('lojas', 'public');
        } else {
            unset($data['logomarca']); // não mexer se não enviado
        }

        if ($remover) {
            if ($loja?->logomarca && !preg_match('~^https?://~i', (string)$loja->logomarca)) {
                Storage::disk('public')->delete($loja->logomarca);
            }
            $data['logomarca'] = null;
        }

        return $data;
    }
}
