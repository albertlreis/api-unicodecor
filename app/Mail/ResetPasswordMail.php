<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de redefinição de senha.
 */
class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nome,
        public string $url
    ) {}

    public function build(): self
    {
        return $this->subject('Redefinição de Senha - Único Decor')
            ->view('emails.reset_password')
            ->with([
                'nome' => $this->nome,
                'url'  => $this->url,
            ]);
    }
}
