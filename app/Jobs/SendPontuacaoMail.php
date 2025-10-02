<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable as BaseMailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Job para envio resiliente de e-mails de pontuação.
 *
 * @phpstan-type MailableType Mailable|BaseMailable
 */
class SendPontuacaoMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    public string $to;

    /** @var Mailable|BaseMailable */
    public Mailable|BaseMailable $mailable;

    /** @var int Número de tentativas */
    public int $tries = 3;

    /** @var array<int,int> backoff progressivo (segundos) */
    public array $backoff = [10, 60, 180];

    /**
     * @param string $to
     * @param Mailable|BaseMailable $mailable
     */
    public function __construct(string $to, Mailable|BaseMailable $mailable)
    {
        $this->to = $to;
        $this->mailable = $mailable;
    }

    /**
     * Executa o envio do e-mail.
     *
     * @return void
     * @throws \Throwable
     */
    public function handle(): void
    {
        try {
            Mail::to($this->to)->send($this->mailable);
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar e-mail (SendPontuacaoMail).', [
                'to'    => $this->to,
                'error' => $e->getMessage(),
            ]);
            // Lança para permitir retry quando houver worker
            throw $e;
        }
    }
}
