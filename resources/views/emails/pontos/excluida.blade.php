@php use Carbon\Carbon; @endphp
<div style="font-family:Arial,sans-serif">
    <h3>Exclusão de Pontuação</h3>
    <p>Usuário: <strong>{{ $usuarioNome }}</strong></p>
    <p>Profissional: <strong>{{ $ponto->profissional?->nome }}</strong></p>
    <p>Loja: <strong>{{ $ponto->loja?->nome }}</strong></p>
    <p>Valor: R$ {{ number_format($ponto->valor,2,',','.') }}</p>
    <p>Data de referência: {{ Carbon::parse($ponto->dt_referencia)->format('d/m/Y') }}</p>
    <p>Orçamento: {{ $ponto->orcamento }}</p>
</div>
