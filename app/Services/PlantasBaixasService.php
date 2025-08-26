<?php

namespace App\Services;

use App\Models\PlantaBaixa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Serviço responsável por consolidar e manipular Plantas Baixas.
 */
class PlantasBaixasService
{
    public const STORAGE_DIR = 'uploads/plantas-baixas';

    /** @return array<int, array<string,mixed>> */
    public function listarAgrupado(): array
    {
        $plantas = PlantaBaixa::with(['empreendimento.construtora'])
            ->where('status', 1)
            ->get();

        $agrupado = [];

        foreach ($plantas as $planta) {
            $const = $planta->empreendimento->construtora;
            $emp   = $planta->empreendimento;

            $idConst = $const->idConstrutoras;
            $idEmp   = $emp->idEmpreendimentos;

            $agrupado[$idConst] ??= [
                'id_construtora'  => $idConst,
                'razao_social'    => $const->razao_social,
                'imagem'          => $const->imagem,
                'empreendimentos' => [],
            ];

            $agrupado[$idConst]['empreendimentos'][$idEmp] ??= [
                'id_empreendimento' => $idEmp,
                'nome'              => $emp->nome,
                'imagem'            => $emp->imagem,
                'plantas'           => [],
            ];

            $agrupado[$idConst]['empreendimentos'][$idEmp]['plantas'][] = [
                'id'        => $planta->idPlantasBaixas,
                'titulo'    => $planta->titulo,
                'descricao' => $planta->descricao,
                'nome'      => $planta->nome,
                'arquivo'   => $planta->arquivo, // Resource aplicará URL pública
            ];
        }

        return array_values($agrupado);
    }

    /**
     * Cria uma planta baixa com upload de PDF.
     *
     * @param array<string,mixed> $payload
     * @param UploadedFile $arquivo
     * @return PlantaBaixa
     */
    public function criar(array $payload, UploadedFile $arquivo): PlantaBaixa
    {
        $caminho = $this->armazenarPdf($arquivo);

        $planta = new PlantaBaixa();
        $planta->idEmpreendimentos = (int) $payload['idEmpreendimentos'];
        $planta->titulo            = $payload['titulo'];
        $planta->descricao         = $payload['descricao'] ?? null;
        $planta->nome              = $payload['nome'] ?? pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME);
        $planta->arquivo           = $caminho; // salvar caminho relativo no disco
        $planta->status            = 1;
        $planta->save();

        return $planta->fresh();
    }

    /**
     * Atualiza a planta e, se enviado, substitui o PDF (apagando o antigo).
     *
     * @param PlantaBaixa $planta
     * @param array<string,mixed> $payload
     * @param UploadedFile|null $arquivo
     * @return PlantaBaixa
     */
    public function atualizar(PlantaBaixa $planta, array $payload, ?UploadedFile $arquivo = null): PlantaBaixa
    {
        if ($arquivo) {
            $this->apagarArquivoSeExistir($planta->arquivo);
            $planta->arquivo = $this->armazenarPdf($arquivo);
        }

        foreach (['idEmpreendimentos','titulo','descricao','nome','status'] as $campo) {
            if (array_key_exists($campo, $payload)) {
                $planta->{$campo} = $payload[$campo];
            }
        }

        $planta->save();
        return $planta->fresh();
    }

    /** Exclui a planta e remove o PDF do disco. */
    public function excluir(PlantaBaixa $planta): void
    {
        $this->apagarArquivoSeExistir($planta->arquivo);
        $planta->delete();
    }

    /** @internal Armazena PDF no disco "public" */
    private function armazenarPdf(UploadedFile $arquivo): string
    {
        $nomeUnico = uniqid().'_'.preg_replace('/\s+/', '_', $arquivo->getClientOriginalName());
        $arquivo->storeAs(self::STORAGE_DIR, $nomeUnico, ['disk' => 'public']);

        // salva somente o nome
        return $nomeUnico;
    }

    /** @internal Apaga arquivo antigo, se existir */
    private function apagarArquivoSeExistir(?string $nomeArquivo): void
    {
        if ($nomeArquivo) {
            $path = self::STORAGE_DIR.'/'.$nomeArquivo;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
