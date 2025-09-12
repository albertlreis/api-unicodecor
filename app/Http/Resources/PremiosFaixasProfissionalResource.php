<?php

namespace App\Http\Resources;

use App\Models\Premio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resource de resposta para /me/premios (visão PROFISSIONAL).
 *
 * Aceita dados vindo do Service tanto como Eloquent Models quanto como arrays já mapeados.
 *
 * @phpstan-type ProximaCampanha array{
 *   id:int,
 *   titulo:string|null,
 *   banner?:string|null,
 *   regulamento?:string|null,
 *   pontos:int,
 *   pontos_formatado:string,
 *   faltam:int,
 *   faltam_formatado:string
 * }
 *
 * @phpstan-type FaixaOut array{
 *   id:int,
 *   descricao:string|null,
 *   pontos_min:int,
 *   pontos_min_formatado:string,
 *   pontos_max:int|null,
 *   pontos_max_formatado:string|null,
 *   pontos_range:string,
 *   acompanhante:bool,
 *   acompanhante_texto:string,
 *   vl_viagem:float|null,
 *   vl_viagem_formatado:string|null
 * }
 *
 * @phpstan-type CampanhaOut array{
 *   id:int,
 *   titulo:string|null,
 *   banner:string|null,
 *   regulamento:string|null,
 *   dt_inicio_iso:string|null,
 *   dt_fim_iso:string|null,
 *   dt_inicio:string|null,
 *   dt_fim:string|null,
 *   periodo:string|null
 * }
 *
 * @property-read array $resource
 */
class PremiosFaixasProfissionalResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        /** @var array{
         *   pontuacao_total: float|int,
         *   dias_restantes: int,
         *   campanha: mixed,
         *   faixa_atual: mixed,
         *   proxima_faixa: mixed,
         *   proximas_faixas?: array<int, mixed>,
         *   proximas_campanhas?: array<int, ProximaCampanha>
         * } $data
         */
        $data = $this->resource;

        $pontuacaoTotal = (float) ($data['pontuacao_total'] ?? 0);

        return [
            'pontuacao_total'           => $pontuacaoTotal,
            'pontuacao_total_formatado' => number_format($pontuacaoTotal, 0, ',', '.'),
            'dias_restantes'            => (int)($data['dias_restantes'] ?? 0),

            'campanha'                  => $this->normalizeCampanha($data['campanha'] ?? null),

            'faixa_atual'               => $this->normalizeFaixa($data['faixa_atual'] ?? null),
            'proxima_faixa'             => $this->normalizeFaixa($data['proxima_faixa'] ?? null),

            'proximas_faixas'           => array_values($data['proximas_faixas'] ?? []),

            'proximas_campanhas'        => array_values(
                array_map(
                    fn ($c) => $this->normalizeCampanha($c) ?? $c,
                    $data['proximas_campanhas'] ?? []
                )
            ),
        ];
    }

    /**
     * Normaliza campanha (Model ou array) para garantir URLs de banner/regulamento do Storage.
     * NÃO altera nomes das chaves.
     *
     * @param mixed $campanha
     * @return CampanhaOut|null
     */
    private function normalizeCampanha($campanha): ?array
    {
        if (!$campanha) {
            return null;
        }

        if (is_array($campanha)) {
            return [
                'id'            => (int)($campanha['id'] ?? 0),
                'titulo'        => $campanha['titulo'] ?? null,
                'banner'        => $this->fileToStorageUrl($campanha['banner'] ?? null, Premio::BANNER_DIR),
                'regulamento'   => $campanha['regulamento'],
                'dt_inicio_iso' => $campanha['dt_inicio_iso'] ?? $this->toIso($campanha['dt_inicio_iso'] ?? null),
                'dt_fim_iso'    => $campanha['dt_fim_iso'] ?? $this->toIso($campanha['dt_fim_iso'] ?? null),
                'dt_inicio'     => $campanha['dt_inicio'] ?? null,
                'dt_fim'        => $campanha['dt_fim'] ?? null,
                'periodo'       => $campanha['periodo'] ?? null,
            ];
        }

        return [
            'id'            => $campanha->id ?? 0,
            'titulo'        => $campanha->titulo ?? null,
            'banner'        => $this->fileToStorageUrl($campanha->banner ?? null, Premio::BANNER_DIR),
            'regulamento'   => $campanha['regulamento'],
            'dt_inicio_iso' => self::toIso($campanha->dt_inicio ?? null),
            'dt_fim_iso'    => self::toIso($campanha->dt_fim ?? null),
            'dt_inicio'     => self::brDate($campanha->dt_inicio ?? null),
            'dt_fim'        => self::brDate($campanha->dt_fim ?? null),
            'periodo'       => self::periodo($campanha->dt_inicio ?? null, $campanha->dt_fim ?? null),
        ];
    }

    /**
     * Normaliza faixa vinda como Eloquent Model ou Array.
     * @param mixed $faixa
     * @return array<string,mixed>|null
     */
    private function normalizeFaixa($faixa): ?array
    {
        if (!$faixa) {
            return null;
        }

        if (is_array($faixa) && isset($faixa['id'])) {
            $min = isset($faixa['pontos_min']) ? (int)$faixa['pontos_min'] : 0;
            $max = array_key_exists('pontos_max', $faixa) ? ($faixa['pontos_max'] !== null ? (int)$faixa['pontos_max'] : null) : null;
            $premioId = isset($faixa['premio_id'])
                ? (int)$faixa['premio_id']
                : (isset($faixa['id_premio']) ? (int)$faixa['id_premio'] : 0);

            return [
                'id'                   => (int)$faixa['id'],
                'descricao'            => $faixa['descricao'] ?? null,
                'pontos_min'           => $min,
                'pontos_min_formatado' => $faixa['pontos_min_formatado'] ?? number_format($min, 0, ',', '.'),
                'pontos_max'           => $max,
                'pontos_max_formatado' => $faixa['pontos_max_formatado'] ?? ($max !== null ? number_format($max, 0, ',', '.') : null),
                'pontos_range'         => $faixa['pontos_range'] ?? self::rangeText($min, $max),
                'acompanhante'         => (bool)($faixa['acompanhante'] ?? false),
                'acompanhante_texto'   => $faixa['acompanhante_texto'] ?? ((bool)($faixa['acompanhante'] ?? false) ? 'Com acompanhante' : 'Somente profissional'),
                'vl_viagem'            => isset($faixa['vl_viagem']) ? ($faixa['vl_viagem'] !== null ? (float)$faixa['vl_viagem'] : null) : null,
                'vl_viagem_formatado'  => $faixa['vl_viagem_formatado'] ?? (isset($faixa['vl_viagem']) && $faixa['vl_viagem'] !== null
                        ? number_format((float)$faixa['vl_viagem'], 2, ',', '.')
                        : null),
                'premio_id'            => $premioId,
            ];
        }

        $min = $faixa->pontos_min ?? 0;
        $max = isset($faixa->pontos_max) ? ($faixa->pontos_max !== null ? (int)$faixa->pontos_max : null) : null;
        $premioId = $faixa->premio_id ?? $faixa->id_premio ?? 0;

        return [
            'id'                   => $faixa->id ?? 0,
            'descricao'            => $faixa->descricao ?? null,
            'pontos_min'           => $min,
            'pontos_min_formatado' => number_format($min, 0, ',', '.'),
            'pontos_max'           => $max,
            'pontos_max_formatado' => $max !== null ? number_format($max, 0, ',', '.') : null,
            'pontos_range'         => self::rangeText($min, $max),
            'acompanhante'         => $faixa->acompanhante ?? false,
            'acompanhante_texto'   => $faixa->acompanhante ?? false ? 'Com acompanhante' : 'Somente profissional',
            'vl_viagem'            => isset($faixa->vl_viagem) ? ($faixa->vl_viagem !== null ? (float)$faixa->vl_viagem : null) : null,
            'vl_viagem_formatado'  => isset($faixa->vl_viagem) && $faixa->vl_viagem !== null
                ? number_format((float)$faixa->vl_viagem, 2, ',', '.')
                : null,
            'premio_id'            => $premioId,
        ];
    }

    // ------------------------
    // Helpers de data/strings
    // ------------------------

    private static function brDate($date): ?string
    {
        if (!$date) return null;
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }

    private static function toIso($date): ?string
    {
        if (!$date) return null;
        try {
            return Carbon::parse($date)->toDateString(); // Y-m-d
        } catch (Throwable) {
            return null;
        }
    }

    private static function periodo($inicio, $fim): ?string
    {
        $i = self::brDate($inicio);
        $f = self::brDate($fim);
        return ($i && $f) ? ($i . ' até ' . $f) : null;
    }

    /**
     * Monta o texto de faixa considerando max nulo (intervalo aberto).
     * Ex.: "a partir de 10.000" ou "10.000 a 20.000"
     * @param float|int $min
     * @param float|int|null $max
     */
    private static function rangeText($min, $max): string
    {
        $minF = number_format((float)$min, 0, ',', '.');
        if ($max === null || $max === '') {
            return "a partir de {$minF}";
        }
        $maxF = number_format((float)$max, 0, ',', '.');
        return "{$minF} a {$maxF}";
    }

    // ------------------------
    // Helpers de arquivo/URL
    // ------------------------

    /**
     * Converte valor legado (URL absoluta, 'public/...', 'storage/...', ou apenas nome)
     * para **URL pública do Storage** no diretório informado.
     *
     * @param  string|null $value
     * @param  string      $dir   Ex.: Premio::BANNER_DIR
     * @return string|null
     */
    private function fileToStorageUrl(?string $value, string $dir): ?string
    {
        $normalized = $this->normalizeFile($value, $dir);
        return $normalized ? Storage::disk('public')->url($normalized) : null;
    }

    /**
     * Normaliza um caminho de arquivo relativo ao disco 'public'.
     * - Se for URL absoluta, extrai apenas o basename.
     * - Remove 'public/' e 'storage/'.
     * - Se vier apenas o nome do arquivo, prefixa o diretório padrão.
     *
     * @param  string|null $value
     * @param  string      $baseDir
     * @return string|null
     */
    private function normalizeFile(?string $value, string $baseDir): ?string
    {
        if ($value === null) return null;
        $raw = trim($value);
        if ($raw === '') return null;

        // URL absoluta -> fica só o arquivo
        if (Str::startsWith($raw, ['http://', 'https://'])) {
            $raw = basename(parse_url($raw, PHP_URL_PATH) ?: $raw);
        }

        // Limpa prefixos e barras iniciais
        $raw = ltrim($raw, '/');
        $raw = preg_replace('#^(public/|storage/)#', '', $raw);

        // Prefixa diretório se não houver subpasta
        if ($raw !== '' && !str_contains($raw, '/')) {
            $raw = trim($baseDir, '/') . '/' . $raw;
        }

        // Evita retornar diretório sem arquivo
        $ext = pathinfo($raw, PATHINFO_EXTENSION);
        if ($raw === '' || $ext === '') {
            return null;
        }

        return $raw;
    }
}
