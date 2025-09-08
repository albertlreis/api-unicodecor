<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Loja;

class LojaUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Normaliza CNPJ e higieniza "logomarca" antes da validação.
     * - CNPJ sempre só dígitos
     * - Se remover_logomarca = true, zera logomarca
     * - Se logomarca for string vazia ou "null"/"undefined", remove do input
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('cnpj')) {
            $this->merge([
                'cnpj' => preg_replace('/\D+/', '', (string) $this->input('cnpj')),
            ]);
        }

        // Remoção explícita
        $remover = filter_var($this->input('remover_logomarca', false), FILTER_VALIDATE_BOOL);
        if ($remover) {
            $this->merge(['logomarca' => null, 'remover_logomarca' => true]);
            return;
        }

        // Se veio string vazia/placeholder, elimina para não cair na regra de file
        if ($this->has('logomarca') && is_string($this->input('logomarca'))) {
            $val = trim((string) $this->input('logomarca'));
            if ($val === '' || strtolower($val) === 'null' || strtolower($val) === 'undefined') {
                $this->request->remove('logomarca');
            }
        }
    }

    public function rules(): array
    {
        /** @var Loja|null $loja */
        $loja = $this->route('loja');
        $isPatch = $this->isMethod('PATCH');

        // helper: em PATCH não exige required
        $req = fn() => $isPatch ? ['sometimes'] : ['sometimes','required'];

        $rules = [
            'razao_social'      => array_merge($req(), ['string','max:100']),
            'nome_fantasia'     => array_merge($req(), ['string','max:100']),
            'cnpj'              => array_merge($req(), ['string','size:14', new Cnpj(),
                Rule::unique('lojas','cnpj')->ignore($loja?->id)]),
            'fone'              => array_merge($req(), ['string','max:25']),
            'endereco'          => ['sometimes','nullable','string','max:100'],
            'site'              => ['sometimes','nullable','url','max:100'],
            'email'             => array_merge($req(), ['email','max:50']),
            'apresentacao'      => ['sometimes','nullable','string'],
            'status'            => ['sometimes','nullable','integer','in:0,1'],
            'remover_logomarca' => ['sometimes','nullable','boolean'],
        ];

        if ($this->hasFile('logomarca')) {
            $rules['logomarca'] = ['sometimes','file','mimes:jpg,jpeg,png,webp','max:2048'];
        } elseif ($this->filled('logomarca') && is_string($this->input('logomarca')) && preg_match('~^https?://~i', (string) $this->input('logomarca'))) {
            $rules['logomarca'] = ['sometimes','nullable','url','max:255'];
        }

        return $rules;
    }
}
