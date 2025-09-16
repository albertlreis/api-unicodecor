<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $titulo
 * @property string|null $regras
 * @property string|null $regulamento
 * @property string|null $site
 * @property string|null $banner
 * @property float|null $pontos
 * @property \Illuminate\Support\Carbon|string|null $dt_inicio
 * @property \Illuminate\Support\Carbon|string|null $dt_fim
 * @property int|null $status
 */
class PremioResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'titulo'              => $this->titulo,
            'regras'              => $this->regras,
            'regulamento'         => $this->regulamento,
            'site'                => $this->site,
            'banner'              => $this->resolveImageUrl($this->banner),
            'pontos'              => $this->pontos,
            'dt_inicio'           => $this->dt_inicio ? $this->dt_inicio->format('Y-m-d') : null,
            'dt_fim'              => $this->dt_fim ? $this->dt_fim->format('Y-m-d') : null,
            'dt_inicio_formatado' => $this->dt_inicio ? $this->dt_inicio->format('d/m/Y') : null,
            'dt_fim_formatado'    => $this->dt_fim ? $this->dt_fim->format('d/m/Y') : null,
            'status'              => $this->status,
            'faixas'              => $this->whenLoaded('faixas', function () {
                return $this->faixas->map(function ($f) {
                    return [
                        'id'                     => $f->id,
                        'pontos_min'             => $f->pontos_min,
                        'pontos_max'             => $f->pontos_max,
                        'vl_viagem'              => $f->vl_viagem,
                        'acompanhante'           => (int) $f->acompanhante,
                        'range'                  => $f->pontos_range_formatado,
                        'acompanhante_label'     => $f->acompanhante_label,
                        'valor_viagem_formatado' => $f->valor_viagem_formatado,
                        'descricao'              => $f->descricao,
                    ];
                });
            }),
        ];
    }

    /**
     * Força o esquema (http/https) de acordo com o ambiente atual.
     * - local  => http
     * - outros => https
     *
     * @param  string $url
     * @return string
     */
    private function forceScheme(string $url): string
    {
        $isLocal = app()->environment('local');

        if ($isLocal) {
            // Local sempre http
            $url = preg_replace('~^https://~i', 'http://', $url);
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'http://' . ltrim($url, '/');
            }
        } else {
            // Produção/Staging sempre https
            $url = preg_replace('~^http://~i', 'https://', $url);
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
        }
        return $url;
    }

    /**
     * Resolve a URL pública da imagem do banner.
     *
     * Regras:
     * - Se for URL absoluta (legada): força https.
     * - Se for apenas hash/arquivo: monta /storage/banners/{arquivo} e escolhe o esquema:
     *   - env local  => http
     *   - outras env => https
     *
     * @param  string|null $value
     * @return string|null
     */
    private function resolveImageUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // 1) URL legada (já absoluta) => apenas troca http->https
        if (preg_match('~^https?://~i', $value)) {
            return preg_replace('~^http://~i', 'https://', $value);
        }

        // 2) Apenas hash/arquivo => construir a URL pública do storage
        $filename = ltrim($value, '/');

        // Caminho público do disk "public" (normalmente vira "/storage/banners/{file}")
        // Ex.: php artisan storage:link
        $relative = Storage::disk('public')->url('premios/' . $filename);

        // Base do app (APP_URL), sem barra final
        $base = rtrim((string) config('app.url'), '/');

        // Se a Storage::url() já retornou absoluta, só ajusta o esquema e retorna
        if (preg_match('~^https?://~i', $relative)) {
            return $this->forceScheme($relative);
        }

        // Garante que a base tenha esquema coerente com o ambiente
        $base = $this->forceScheme($base);

        // Concatena base + caminho relativo
        $absolute = rtrim($base, '/') . (str_starts_with($relative, '/') ? '' : '/') . $relative;

        // Por segurança, força novamente o esquema correto no resultado final
        return $this->forceScheme($absolute);
    }
}
