<?php

namespace App\Mail;

use App\Models\Ponto;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica a exclusão (soft delete) de uma pontuação.
 */
class PontuacaoExcluidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ponto $ponto,
        public string $usuarioNome
    ) {}

    public function build(): self
    {
        return $this->subject('Pontuação excluída')
            ->view('emails.pontos.excluida');
    }
}
