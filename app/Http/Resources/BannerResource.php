<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property int         $idBanners
 * @property string      $titulo
 * @property string|null $imagem     // pode ser URL legada OU apenas o hash/arquivo
 * @property string|null $link
 * @property string|null $descricao
 * @property int         $status
 */
class BannerResource extends JsonResource
{
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
        $relative = Storage::disk('public')->url('banners/' . $filename);

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
     * @inheritDoc
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->idBanners,
            'titulo'    => $this->titulo,
            'imagem'    => $this->resolveImageUrl($this->imagem),
            'link'      => $this->link,
            'descricao' => $this->descricao,
            'status'    => $this->status,
        ];
    }
}
