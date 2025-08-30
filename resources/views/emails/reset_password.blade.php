<!doctype html>
<html lang="pt-br">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#333;">
<div style="text-align:center;padding:16px;background:#f9f9f9;">
    <img src="https://arearestrita.momentounicodecor.com.br/assets/img/barra-unico.jpg" alt="Único Decor" style="max-width: 300px;height:auto;" />
</div>
<div style="max-width:600px;margin:0 auto;padding:20px;background:#fff;">
    <p>Olá {{ $nome }},</p>
    <p>Você solicitou a redefinição da sua senha.</p>
    <p>Clique no link abaixo para criar uma nova senha (expira em 1 hora):</p>
    <p><a href="{{ $url }}" target="_blank">{{ $url }}</a></p>
    <hr>
    <p style="font-size:12px;color:#777;">Se você não solicitou, ignore este e-mail.</p>
</div>
</body>
</html>
