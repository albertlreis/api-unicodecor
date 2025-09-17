<?php

namespace App\Services;

use App\Models\Galeria;
use App\Models\GaleriaImagem;
use Illuminate\Contracts\Filesystem\Factory as StorageFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function in_array;

/**
 * Regras de negócio das Galerias.
 *
 * Status:
 * - 1 = ativo
 * - 0 = inativo
 */
class GaleriaService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly StorageFactory $storage,
    ) {}

    /**
     * Lista galerias com filtros por status e quantidade mínima de imagens.
     *
     * Regras:
     * - Admin (perfil=1): vê todas por padrão; se ?status=0|1 vier, filtra; min_qtd opcional.
     * - Não-admin: apenas ativas (status=1) e com >=1 imagem ativa.
     *
     * @param  array{
     *   status?: int|null,
     *   min_qtd?: int|null,
     *   per_page?: int|null
     * } $filtros
     * @param  int  $perfilId
     * @return Collection<int, Galeria>|LengthAwarePaginator
     */
    public function listar(array $filtros, int $perfilId): Collection|LengthAwarePaginator
    {
        $statusRaw = $filtros['status'] ?? null;
        $status    = in_array($statusRaw, [0, 1], true) ? $statusRaw : null;
        $minQtd    = (int) ($filtros['min_qtd'] ?? 0);
        $perPage   = (int) ($filtros['per_page'] ?? 0);

        $query = Galeria::query()
            ->withCount(['imagens'])
            ->with('imagemCapa');

        if ($perfilId === 1) {
            if ($status !== null) {
                $query->where('status', $status);
            }
            if ($minQtd > 0) {
                $query->has('imagens', '>=', $minQtd);
            }
        } else {
            $query->where('status', 1)->has('imagens', '>=', 1);
        }

        $query->orderByDesc('dt_criacao');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Retorna uma galeria visível conforme o perfil.
     *
     * Admin pode ver ativa ou inativa.
     * Não-admin só vê ativa e com pelo menos 1 imagem ativa.
     *
     * @param  int $id
     * @param  int $perfilId
     * @return Galeria
     */
    public function detalhar(int $id, int $perfilId): Galeria
    {
        $q = Galeria::query()->where('idGalerias', $id);

        if ($perfilId !== 1) {
            $q->where('status', 1);
        }

        /** @var Galeria $galeria */
        $galeria = $q->firstOrFail();

        $galeria->load(['imagens' => function ($q) {
            $q->where('status', 1)->orderBy('dt_criacao', 'desc');
        }]);

        if ($perfilId !== 1 && $galeria->imagens->count() < 1) {
            abort(404);
        }

        return $galeria;
    }

    /**
     * Cria uma nova galeria ativa.
     *
     * @param  array{descricao:string} $data
     * @return Galeria
     */
    public function criar(array $data): Galeria
    {
        $galeria = new Galeria();
        $galeria->descricao = $data['descricao'];
        $galeria->status = 1;
        $galeria->save();

        return $galeria;
    }

    /**
     * Atualiza descrição da galeria.
     *
     * @param  Galeria                 $galeria
     * @param  array{descricao:string} $data
     * @return Galeria
     */
    public function atualizar(Galeria $galeria, array $data): Galeria
    {
        $galeria->descricao = $data['descricao'];
        $galeria->save();

        return $galeria;
    }

    /**
     * Altera o status da galeria.
     *
     * @param  Galeria $galeria
     * @param  int     $status 0=inativa, 1=ativa
     * @return Galeria
     */
    public function alterarStatus(Galeria $galeria, int $status): Galeria
    {
        $galeria->status = $status === 1 ? 1 : 0;
        $galeria->save();

        return $galeria;
    }

    /** @return string sha1 do conteúdo */
    private function sha1Of(UploadedFile $file): string
    {
        $ctx = hash_init('sha1');
        $stream = fopen($file->getRealPath(), 'rb');
        while (!feof($stream)) {
            $buf = fread($stream, 1024 * 1024);
            if ($buf !== false) {
                hash_update($ctx, $buf);
            }
        }
        fclose($stream);
        return hash_final($ctx);
    }

    /** @return string extensão coerente (jpg/png/webp) */
    private function resolveExtension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }
        $mime = strtolower((string) $file->getMimeType());
        if (str_contains($mime, 'jpeg')) return 'jpg';
        if (str_contains($mime, 'png'))  return 'png';
        if (str_contains($mime, 'webp')) return 'webp';
        return 'jpg';
    }

    /**
     * Envia/associa imagem (salva somente o nome do arquivo).
     *
     * @param  Galeria      $galeria
     * @param  UploadedFile $arquivo
     * @return GaleriaImagem
     */
    public function adicionarImagem(Galeria $galeria, UploadedFile $arquivo): GaleriaImagem
    {
        $hash = $this->sha1Of($arquivo);
        $ext  = $this->resolveExtension($arquivo);
        $filename = "$hash.$ext";
        $this->storage->disk('public')->putFileAs('galerias', $arquivo, $filename);

        $img = new GaleriaImagem();
        $img->idGalerias = $galeria->idGalerias;
        $img->arquivo = $filename;
        $img->status = 1;
        $img->save();

        $temCapa = !empty($galeria->idGaleriaImagens);
        if (!$temCapa) {
            $galeria->idGaleriaImagens = $img->idGaleriaImagens;
            $galeria->save();
        }

        return $img;
    }

    /**
     * Remove imagem (inativa, limpa capa se necessário e apaga arquivo físico).
     *
     * @param  Galeria       $galeria
     * @param  GaleriaImagem $img
     * @return void
     */
    public function removerImagem(Galeria $galeria, GaleriaImagem $img): void
    {
        DB::transaction(function () use ($galeria, $img) {
            if ((int) $galeria->idGaleriaImagens === $img->idGaleriaImagens) {
                $galeria->idGaleriaImagens = null;
                $galeria->save();
            }

            $img->delete();

            if ($img->arquivo && Storage::exists("public/galerias/{$img->arquivo}")) {
                Storage::delete("public/galerias/{$img->arquivo}");
            }
        });
    }

    /**
     * Define a capa da galeria.
     *
     * @param  Galeria       $galeria
     * @param  GaleriaImagem $img
     * @return void
     */
    public function definirCapa(Galeria $galeria, GaleriaImagem $img): void
    {
        $galeria->idGaleriaImagens = $img->idGaleriaImagens;
        $galeria->save();
    }

    /**
     * Monta URL pública para a imagem, garantindo HTTPS em produção.
     *
     * @param  string $filename
     * @return string
     */
    public function urlImagem(string $filename): string
    {
        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            // Se vier http, promove para https em produção
            if (app()->environment('production')) {
                return preg_replace('#^http://#', 'https://', $filename);
            }
            return $filename;
        }

        logger()->info("Imagem: ", [app()->environment('production')]);

        // Em produção, gera sempre https; em local usa o asset normal
        return app()->environment('production')
            ? secure_asset("storage/galerias/{$filename}")
            : asset("storage/galerias/{$filename}");
    }
}
