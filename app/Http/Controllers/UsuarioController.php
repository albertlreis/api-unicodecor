<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioAdminStoreRequest;
use App\Http\Requests\UsuarioAdminUpdateRequest;
use App\Http\Resources\UsuarioAdminResource;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\Usuario;
use Carbon\Carbon;

class UsuarioController extends Controller
{

    /**
     * Cria um usuário administrativo.
     * - status sempre = 1 (ativo)
     * - senha salva com md5 para compatibilizar com AuthController atual
     * - exige id_loja quando perfil = 3 (lojista)
     *
     * @param  UsuarioAdminStoreRequest $request
     * @return JsonResponse
     */
    public function store(UsuarioAdminStoreRequest $request): JsonResponse
    {
        $dados = $request->validated();

        $usuario = new Usuario();
        $usuario->nome      = $dados['nome'];
        $usuario->email     = $dados['email'];
        $usuario->cpf       = $dados['cpf'];
        $usuario->id_perfil = (int) $dados['id_perfil'];
        $usuario->id_loja   = $dados['id_loja'] ?? null;
        // compatibilidade com login atual:
        $usuario->senha     = md5($dados['senha']);
        $usuario->status    = 1; // sempre ativo no cadastro
        $usuario->login     = $dados['email']; // opcional: usar email como login

        $usuario->save();

        return response()->json([
            'sucesso' => true,
            'mensagem'=> 'Usuário criado com sucesso.',
            'dados'   => new UsuarioAdminResource($usuario),
        ], 201);
    }

    /**
     * Atualiza um usuário administrativo.
     * - nome, email, cpf, perfil obrigatórios
     * - senha opcional (se enviada, atualiza)
     * - id_loja obrigatório quando perfil = 3
     *
     * @param  UsuarioAdminUpdateRequest $request
     * @param  Usuario                   $usuario
     * @return JsonResponse
     */
    public function update(UsuarioAdminUpdateRequest $request, Usuario $usuario): JsonResponse
    {
        $dados = $request->validated();

        $usuario->nome      = $dados['nome'];
        $usuario->email     = $dados['email'];
        $usuario->cpf       = $dados['cpf'];
        $usuario->id_perfil = (int) $dados['id_perfil'];
        $usuario->id_loja   = $dados['id_loja'] ?? null;

        if (!empty($dados['senha'])) {
            // compatibilidade com AuthController
            $usuario->senha = md5($dados['senha']);
        }

        $usuario->save();

        return response()->json([
            'sucesso' => true,
            'mensagem'=> 'Usuário atualizado com sucesso.',
            'dados'   => new UsuarioAdminResource($usuario),
        ]);
    }

    /**
     * "Exclusão" lógica: apenas inativa o usuário.
     * - status = 0
     *
     * @param  Usuario $usuario
     * @return JsonResponse
     */
    public function destroy(Usuario $usuario): JsonResponse
    {
        $usuario->status = 0;
        $usuario->save();

        return response()->json([
            'sucesso' => true,
            'mensagem'=> 'Usuário inativado com sucesso.',
        ]);
    }

    /**
     * Retorna lista de clientes (usuários com perfil_id = 6).
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function usuarios(Request $request): JsonResponse
    {
        $perfilId = (int) ($request->input('id_perfil', 6));
        $user     = auth()->user();

        $query = Usuario::query()
            ->where('id_perfil', $perfilId)
            ->where('status', 1);

        // Mantém a mesma regra de visibilidade usada no projeto
        if (method_exists(Usuario::class, 'scopeVisiveisParaUsuario')) {
            $query->visiveisParaUsuario($user);
        }

        $clientes = $query
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf']);

        return response()->json([
            'sucesso' => true,
            'dados'   => $clientes,
        ]);
    }

    /**
     * Lista usuários administrativos.
     *
     * Perfis administrativos padrão: 1 (Administrador), 3 (Lojista), 5 (Secretaria).
     * Permite filtrar por nome (q) e por um perfil específico (id_perfil).
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function administrativos(Request $request): JsonResponse
    {
        $request->validate([
            'id_perfil' => ['nullable', 'integer', Rule::in([1, 3, 4, 5])],
            'q'         => ['nullable', 'string', 'max:150'],
            'status'    => ['nullable', Rule::in([0, 1])],
        ]);

        $adminPerfis = [1, 3, 4, 5];
        $perfilId    = $request->integer('id_perfil');
        $q           = $request->string('q')->toString();
        $status      = $request->has('status') ? (int) $request->input('status') : null;

        $query = Usuario::query()
            ->when($perfilId, fn($q2) => $q2->where('id_perfil', $perfilId))
            ->when(!$perfilId, fn($q2) => $q2->whereIn('id_perfil', $adminPerfis))
            ->when($status !== null, fn($q2) => $q2->where('status', $status))
            ->when($q, function ($q2) use ($q) {
                $like = "%{$q}%";
                $q2->where(function ($sub) use ($like) {
                    $sub->where('nome', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            });

        // Opcional: respeitar escopo de visibilidade se existir
        $user = auth()->user();
        if (method_exists(Usuario::class, 'scopeVisiveisParaUsuario')) {
            $query->visiveisParaUsuario($user);
        }

        $dados = $query
            ->orderBy('nome')
            ->get(['id', 'nome', 'email', 'id_perfil', 'status']);

        return response()->json([
            'sucesso' => true,
            'dados'   => $dados,
        ]);
    }

    /**
     * Lista aniversariantes do mês quando data_nascimento = "DD/MM".
     *
     * Query params:
     * - month: 1..12 (opcional, padrão = mês atual)
     *
     * Regras:
     * - status = 1
     * - data_nascimento não nulo e no formato DD/MM
     * - ordena por dia (numérico) e depois por nome
     * - "proximos_7_dias" = true se cair entre hoje e hoje+7
     */
    public function aniversariantes(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $today      = Carbon::now();
        $monthInput = (int) $request->input('month', $today->month);
        $year       = $today->year;

        // Zero pad (ex.: 7 -> "07")
        $monthStr = str_pad((string)$monthInput, 2, '0', STR_PAD_LEFT);

        // Filtro: RIGHT(data_nascimento, 2) = "MM"
        // Ordenação: dia (SUBSTRING_INDEX(..., '/', 1)) ASC, nome ASC
        $usuarios = Usuario::query()
            ->where('status', 1)
            ->where('id_perfil', 2)
            ->whereNotNull('dt_nasc')
            ->whereRaw("RIGHT(dt_nasc, 2) = ?", [$monthStr])
            ->orderByRaw("CAST(SUBSTRING_INDEX(dt_nasc, '/', 1) AS UNSIGNED) ASC, nome ASC")
            ->get(['id', 'nome', 'email', 'fone', 'dt_nasc']);

        $highlightStart = $today->copy()->startOfDay();
        $highlightEnd   = $today->copy()->addDays(7)->endOfDay();

        $dados = $usuarios->map(function (Usuario $u) use ($year, $highlightStart, $highlightEnd) {
            // data_nascimento esperado: "DD/MM"
            $raw = trim((string)$u->dt_nasc);

            // Extrai DD e MM com segurança
            [$dd, $mm] = array_pad(explode('/', $raw), 2, null);
            $dia = (int) ($dd ?? 0);
            $mes = (int) ($mm ?? 0);

            // Monta "próxima ocorrência" neste ano
            // (se já passou hoje, adiciona +1 ano para fins de destaque)
            $next = null;
            if ($dia >= 1 && $dia <= 31 && $mes >= 1 && $mes <= 12) {
                $next = Carbon::create($year, $mes, min($dia, 28), 0, 0, 0); // evita inválida (29-31) em meses curtos
                // Ajusta para o dia correto quando possível
                try {
                    $next = Carbon::create($year, $mes, $dia, 0, 0, 0);
                } catch (Exception) {
                    // fallback já está em min(dia, 28)
                }
                if ($next->lt(Carbon::now()->startOfDay())) {
                    $next->addYear();
                }
            }

            $proximos7 = $next && $next->betweenIncluded($highlightStart, $highlightEnd);

            return [
                'id'               => $u->id,
                'nome'             => $u->nome,
                'email'            => $u->email,
                'telefone'         => $u->fone,
                'data_nascimento'  => $raw,
                'dia'              => $dia,
                'mes'              => $mes,
                'proximos_7_dias'  => $proximos7,
            ];
        })->values();

        return response()->json([
            'sucesso' => true,
            'mes'     => $monthInput,
            'dados'   => $dados,
        ]);
    }
}
