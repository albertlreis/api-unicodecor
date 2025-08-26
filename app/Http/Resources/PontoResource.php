<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Ponto
 */
class PontoResource extends JsonResource
{
    /**
     * Transforma o recurso em um array para retorno da API.
     *
     * Campos principais:
     * - valor (float) e valor_fmt (string)
     * - dt_referencia (Y-m-d) e dt_referencia_fmt (d/m/Y)
     * - dt_cadastro (Y-m-d H:i:s) e dt_cadastro_fmt (d/m/Y)
     * - dt_edicao (Y-m-d H:i:s) e dt_edicao_fmt (d/m/Y)
     * - status (int), status_text (string) e status_label_html (compat)
     * - conveniências: profissional_nome, loja_nome, cliente_nome
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $dtRefRaw = $this->dt_referencia ? Carbon::parse($this->dt_referencia)->format('Y-m-d') : null;
        $dtCadRaw = $this->dt_cadastro   ? Carbon::parse($this->dt_cadastro)->format('Y-m-d H:i:s') : null;
        $dtEdiRaw = $this->dt_edicao     ? Carbon::parse($this->dt_edicao)->format('Y-m-d H:i:s') : null;

        $statusInt  = $this->status !== null ? (int) $this->status : null;
        $statusText = match ((int) ($statusInt ?? -1)) {
            0       => 'Desabilitado',
            1       => 'Ativo',
            2       => 'Excluído',
            default => 'Desconhecido',
        };

        return [
            'id'               => (int) $this->id,
            'valor'            => $this->valor !== null ? (float) $this->valor : null,
            'valor_fmt'        => $this->when(isset($this->valor_formatado), $this->valor_formatado),
            'valor_formatado'  => $this->when(isset($this->valor_formatado), $this->valor_formatado), // compat

            // Datas — cru + formatado
            'dt_referencia'     => $dtRefRaw,
            'dt_referencia_fmt' => $this->when(isset($this->dt_referencia_formatado), $this->dt_referencia_formatado),

            'dt_cadastro'       => $dtCadRaw,
            'dt_cadastro_fmt'   => $this->when(isset($this->dt_cadastro_formatado), $this->dt_cadastro_formatado),

            'dt_edicao'         => $dtEdiRaw,
            'dt_edicao_fmt'     => $this->when($this->dt_edicao, Carbon::parse($this->dt_edicao)->format('d/m/Y')),

            // Status
            'status'            => $statusInt,
            'status_text'       => $statusText,
            'status_label_html' => $this->when(isset($this->status_label), $this->status_label), // compat com admin web

            'orcamento'         => $this->orcamento,

            // Conveniências planas (ajudam no mobile)
            'profissional_nome' => $this->when($this->relationLoaded('profissional') || $this->profissional, $this->profissional?->nome),
            'loja_nome'         => $this->when($this->relationLoaded('loja') || $this->loja, $this->loja?->nome),
            'cliente_nome'      => $this->when($this->relationLoaded('cliente') || $this->cliente, $this->cliente?->nome),

            // Relacionamentos completos (somente se carregados)
            'profissional' => $this->whenLoaded('profissional', fn () => [
                'id'    => $this->profissional?->id,
                'nome'  => $this->profissional?->nome,
                'email' => $this->profissional?->email,
            ]),
            'lojista' => $this->whenLoaded('lojista', fn () => [
                'id'    => $this->lojista?->id,
                'nome'  => $this->lojista?->nome,
                'email' => $this->lojista?->email,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id'   => $this->cliente?->id,
                'nome' => $this->cliente?->nome,
            ]),
            'loja' => $this->whenLoaded('loja', fn () => [
                'id'    => $this->loja?->id,
                'nome'  => $this->loja?->nome,
                'razao' => $this->loja?->razao,
                'email' => $this->loja?->email,
            ]),
        ];
    }
}
