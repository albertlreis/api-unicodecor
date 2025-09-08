<?php

namespace App\Services;

use App\Models\Loja;
use App\Repositories\LojaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Camada de aplicação para Lojas.
 */
class LojaService
{
    public function __construct(
        private readonly LojaRepository $repo
    ) {}

    /**
     * Lista paginada com filtros.
     *
     * @param array{q?:string|null,status?:int|string|null,page?:int|string|null,per_page?:int|string|null} $filters
     * @return LengthAwarePaginator
     */
    public function listarPaginado(array $filters): LengthAwarePaginator
    {
        // Normaliza tipos e limites
        $q        = $filters['q'] ?? null;
        $status   = isset($filters['status']) && $filters['status'] !== '' ? (int) $filters['status'] : null;
        $page     = max(1, (int) ($filters['page'] ?? 1));
        $perPage  = (int) ($filters['per_page'] ?? 15);
        $perPage  = max(1, min(100, $perPage)); // evita exageros

        return $this->repo->paginate([
            'q'        => $q,
            'status'   => $status,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Lista todas as ativas (sem paginação).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,Loja>
     */
    public function listarAtivas()
    {
        return $this->repo->allAtivas();
    }

    /**
     * Cria loja.
     *
     * @param array $payload
     * @return Loja
     */
    public function criar(array $payload): Loja
    {
        $data = $this->mapApiToDb($payload);
        $data = $this->processarUpload($data, null);
        return $this->repo->create($data);
    }


    /**
     * Atualiza loja.
     *
     * @param Loja $loja
     * @param array $payload
     * @return Loja
     * @throws \Illuminate\Validation\ValidationException
     */
    public function atualizar(Loja $loja, array $payload): Loja
    {
        logger()->info('LojaService.atualizar: payload (bruto)', ['payload' => $payload]);

        // Para update: NÃO aplicar defaults automáticos
        $data = $this->mapApiToDb($payload, isUpdate: true);

        logger()->info('LojaService.atualizar: após mapApiToDb', ['data' => $data]);

        $data = $this->processarUpload($data, $loja);

        logger()->info('LojaService.atualizar: após processarUpload', ['data' => $data]);

        // Remove chaves de controle que não são colunas
        unset($data['remover_logomarca']);

        // Se não sobrou nada para atualizar, não faça update
        if (empty($data)) {
            throw ValidationException::withMessages([
                'payload' => ['Nenhum campo para atualizar.'],
            ]);
        }

        return $this->repo->update($loja, $data);
    }

    /**
     * Remove loja (delete real).
     *
     * @param Loja $loja
     * @return void
     */
    public function remover(Loja $loja): void
    {
        $this->repo->deleteComArquivo($loja);
    }

    /**
     * Mapeia payload da API para colunas do DB
     * e normaliza CNPJ para apenas dígitos.
     *
     * @param array $data
     * @param bool  $isUpdate Quando true, não aplica defaults (ex.: status=1).
     * @return array
     */
    private function mapApiToDb(array $data, bool $isUpdate = false): array
    {
        $map = [
            'razao_social'  => 'razao',
            'nome_fantasia' => 'nome',
            'site'          => 'eletronico',
            'cnpj'          => 'cnpj',
            'fone'          => 'fone',
            'endereco'      => 'endereco',
            'email'         => 'email',
            'apresentacao'  => 'apresentacao',
            'status'        => 'status',
        ];

        $out = [];

        foreach ($map as $apiKey => $dbKey) {
            if (array_key_exists($apiKey, $data)) {
                $out[$dbKey] = $data[$apiKey];
            }
        }

        // Em UPDATE: não aplicar default de status
        // Em CREATE: o default é tratado no método criar()

        // Normaliza CNPJ: só dígitos
        if (array_key_exists('cnpj', $out) && !empty($out['cnpj'])) {
            $out['cnpj'] = preg_replace('/\D+/', '', (string) $out['cnpj']);
        }

        // Pass-through de controle de upload
        if (array_key_exists('logomarca', $data)) {
            $out['logomarca'] = $data['logomarca'];
        }
        if (array_key_exists('remover_logomarca', $data)) {
            $out['remover_logomarca'] = (bool) $data['remover_logomarca'];
        }

        return $out;
    }

    /**
     * Processa upload da logomarca (verifica request()->file() quando necessário).
     *
     * @param array     $data
     * @param Loja|null $loja
     * @return array
     */
    private function processarUpload(array $data, ?Loja $loja = null): array
    {
        /** @var \Illuminate\Http\UploadedFile|string|null $arquivo */
        $arquivo = $data['logomarca'] ?? null;

        if (!$arquivo) {
            $arquivo = request()->file('logomarca');
        }

        $remover = (bool) ($data['remover_logomarca'] ?? false);

        if ($remover) {
            $this->repo->removerArquivoSeNosso($loja?->logomarca);
            $data['logomarca'] = null;
            // manter remover_logomarca até o unset no final do atualizar()
            return $data;
        }

        // String (URL absoluta mantida; string não-URL ignorada)
        if (is_string($arquivo)) {
            if (preg_match('~^https?://~i', $arquivo)) {
                return $data;
            }
            unset($data['logomarca']);
            return $data;
        }

        // UploadedFile
        if ($arquivo instanceof UploadedFile) {
            $filename = $this->repo->armazenarLogomarca($arquivo);

            // Remove arquivo anterior se era nosso e diferente
            if ($loja?->logomarca && $loja->logomarca !== $filename) {
                $this->repo->removerArquivoSeNosso($loja->logomarca);
            }

            $data['logomarca'] = $filename;
        } else {
            unset($data['logomarca']);
        }

        return $data;
    }
}
