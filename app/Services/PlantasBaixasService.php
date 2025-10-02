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
    public const STORAGE_DIR = 'plantas'; // padroniza como em prêmios (sem "uploads/")

    public function __construct(
        private readonly HashedFileStorage $hashStorage
    ) {}

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
            $idEmp   = $emp->idEmpreendimentos ?? $emp->id;

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
                'id'        => $planta->idPlantasBaixas ?? $planta->id,
                'titulo'    => $planta->titulo,
                'descricao' => $planta->descricao,
                'nome'      => $planta->nome,
                'arquivo'   => $planta->arquivo, // Resource aplica URL
            ];
        }

        return array_values($agrupado);
    }

    /** Cria planta baixa com upload de DWG (hash.ext). */
    public function criar(array $payload, UploadedFile $dwg): PlantaBaixa
    {
        $nomeArmazenado = $this->armazenarDwgComHash($dwg);

        $planta = new PlantaBaixa();
        $planta->idEmpreendimentos = (int) $payload['idEmpreendimentos'];
        $planta->titulo            = $payload['titulo'];
        $planta->descricao         = $payload['descricao'] ?? null;
        $planta->nome              = $payload['nome'] ?? pathinfo($dwg->getClientOriginalName(), PATHINFO_FILENAME);
        $planta->arquivo           = $nomeArmazenado; // salva só "hash.dwg"
        $planta->status            = 1;
        $planta->save();

        return $planta->fresh();
    }

    /** Atualiza planta e substitui DWG, quando enviado. */
    public function atualizar(PlantaBaixa $planta, array $payload, ?UploadedFile $dwg = null): PlantaBaixa
    {
        if ($dwg) {
            $this->apagarArquivoSeExistir($planta->arquivo);
            $planta->arquivo = $this->armazenarDwgComHash($dwg);
        }

        foreach (['idEmpreendimentos','titulo','descricao','nome','status'] as $campo) {
            if (array_key_exists($campo, $payload)) {
                $planta->{$campo} = $payload[$campo];
            }
        }

        $planta->save();
        return $planta->fresh();
    }

    public function excluir(PlantaBaixa $planta): void
    {
        $this->apagarArquivoSeExistir($planta->arquivo);
        $planta->delete();
    }

    /** Salva DWG como "hash.dwg" em disk public/plantas */
    private function armazenarDwgComHash(UploadedFile $dwg): string
    {
        // força extensão .dwg no nome final
        return $this->hashStorage->putWithHash($dwg, 'public', self::STORAGE_DIR, 'dwg');
    }

    private function apagarArquivoSeExistir(?string $nomeArquivo): void
    {
        if ($nomeArquivo) {
            $path = self::STORAGE_DIR.'/'.ltrim($nomeArquivo, '/');
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
