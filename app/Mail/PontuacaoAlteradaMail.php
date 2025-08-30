<?php

namespace App\Mail;

use App\Models\Ponto;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica a alteração de uma pontuação.
 */
class PontuacaoAlteradaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ponto $antes,
        public Ponto $depois,
        public string $usuarioNome
    ) {}

    public function build(): self
    {
        return $this->subject('Pontuação alterada')
            ->view('emails.pontos.alterada');
    }
}
