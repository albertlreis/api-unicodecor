<div style="font-family:Arial,sans-serif">
    <h3>Alteração de Pontuação</h3>
    <p>Usuário: <strong>{{ $usuarioNome }}</strong></p>
    <p>Profissional: <strong>{{ $depois->profissional?->nome }}</strong></p>
    <p>Loja: <strong>{{ $depois->loja?->nome }}</strong></p>
    <p>Valor anterior: R$ {{ number_format($antes->valor,2,',','.') }}</p>
    <p>Valor novo: R$ {{ number_format($depois->valor,2,',','.') }}</p>
    <p>Referência anterior: {{ \Carbon\Carbon::parse($antes->dt_referencia)->format('d/m/Y') }}</p>
    <p>Referência nova: {{ \Carbon\Carbon::parse($depois->dt_referencia)->format('d/m/Y') }}</p>
</div>
