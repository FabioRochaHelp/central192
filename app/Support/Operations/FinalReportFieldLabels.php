<?php

declare(strict_types=1);

namespace App\Support\Operations;

/** Rótulos legíveis para valores armazenados nos relatórios finais CB. */
final class FinalReportFieldLabels
{
    public static function format(?string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('Sim') : __('Não');
        }

        $raw = (string) $value;

        return match ($field) {
            'vegetation_type' => match ($raw) {
                'cerrado' => __('Cerrado'),
                'mata_atlantica' => __('Mata atlântica'),
                'pasto' => __('Pasto'),
                'capoeira' => __('Capoeira'),
                'eucalipto' => __('Eucalipto'),
                'outro' => __('Outro'),
                default => self::humanize($raw),
            },
            'fire_behavior' => match ($raw) {
                'superficial' => __('Superficial'),
                'copa' => __('Copa'),
                'salto' => __('Salto (spotting)'),
                'misto' => __('Misto'),
                default => self::humanize($raw),
            },
            'probable_cause', 'fb_probable_cause' => match ($raw) {
                'raio' => __('Raio'),
                'descuido_humano', 'descuido' => __('Descuido humano'),
                'criminoso' => __('Criminoso'),
                'operacional' => __('Operacional'),
                'falha_eletrica' => __('Falha elétrica'),
                'vazamento_gas' => __('Vazamento de gás'),
                'explosao' => __('Explosão'),
                'curto' => __('Curto-circuito'),
                'indeterminado' => __('Indeterminado'),
                default => self::humanize($raw),
            },
            'discovery_source' => match ($raw) {
                'vigilancia_aerea' => __('Vigilância aérea'),
                'denuncia' => __('Denúncia'),
                'inpe' => __('INPE'),
                'rondante' => __('Rondante'),
                'outro' => __('Outro'),
                default => self::humanize($raw),
            },
            'final_status' => match ($raw) {
                'extinto' => __('Extinto'),
                'controlado' => __('Controlado'),
                'monitoramento' => __('Em monitoramento'),
                'repassado' => __('Repassado'),
                'transferido' => __('Transferido'),
                default => self::humanize($raw),
            },
            'building_type' => match ($raw) {
                'residencial' => __('Residencial'),
                'comercial' => __('Comercial'),
                'industrial' => __('Industrial'),
                'institucional' => __('Institucional'),
                'misto' => __('Misto'),
                'veiculo' => __('Veículo'),
                default => self::humanize($raw),
            },
            'construction_type' => match ($raw) {
                'alvenaria' => __('Alvenaria'),
                'madeira' => __('Madeira'),
                'metalica' => __('Metálica'),
                default => self::humanize($raw),
            },
            'damage_level' => match ($raw) {
                'parcial_leve' => __('Parcial leve (<25%)'),
                'parcial_grave' => __('Parcial grave (26–75%)'),
                'total' => __('Total (>75%)'),
                default => self::humanize($raw),
            },
            'animal_category' => match ($raw) {
                'domestico' => __('Doméstico'),
                'silvestre' => __('Silvestre'),
                'de_producao' => __('De produção'),
                default => self::humanize($raw),
            },
            'animal_species' => match ($raw) {
                'cao' => __('Cão'),
                'gato' => __('Gato'),
                'cavalo' => __('Cavalo'),
                'boi' => __('Boi/Bovino'),
                'serpente' => __('Serpente'),
                'ave' => __('Ave'),
                'jacare' => __('Jacaré'),
                'outro' => __('Outro'),
                default => self::humanize($raw),
            },
            'animal_size' => match ($raw) {
                'pequeno' => __('Pequeno'),
                'medio' => __('Médio'),
                'grande' => __('Grande'),
                default => self::humanize($raw),
            },
            'entrapment_type' => match ($raw) {
                'arvore' => __('Árvore'),
                'buraco' => __('Buraco / Vala'),
                'cisterna_poco' => __('Cisterna / Poço'),
                'via_aquatica' => __('Via aquática'),
                'veiculo' => __('Veículo'),
                'estrutura' => __('Estrutura'),
                'cerca_cabo' => __('Cerca / Cabo'),
                'elevado' => __('Elevado'),
                'outro' => __('Outro'),
                default => self::humanize($raw),
            },
            'animal_condition_arrival' => match ($raw) {
                'calmo' => __('Calmo'),
                'agitado' => __('Agitado'),
                'ferido' => __('Ferido'),
                'inconsciente' => __('Inconsciente'),
                'obito_chegada' => __('Óbito na chegada'),
                default => self::humanize($raw),
            },
            'outcome', 'ra_outcome' => match ($raw) {
                'resgatado_tutor' => __('Resgatado — entregue ao tutor'),
                'resgatado_abrigo' => __('Resgatado — abrigo'),
                'resgatado_veterinario' => __('Resgatado — veterinário'),
                'solto_silvestre' => __('Solto (silvestre)'),
                'nao_localizado' => __('Não localizado'),
                'obito' => __('Óbito'),
                'resgatado_ileso' => __('Resgatado ileso'),
                'resgatado_ferido' => __('Resgatado ferido'),
                'obito_local' => __('Óbito no local'),
                default => self::humanize($raw),
            },
            'insect_type' => match ($raw) {
                'abelhas' => __('Abelhas'),
                'marimbondos' => __('Marimbondos'),
                'vespas' => __('Vespas'),
                'maribondo_tatu' => __('Maribondo-tatu'),
                'outro' => __('Outro'),
                default => self::humanize($raw),
            },
            'colony_size_estimate' => match ($raw) {
                'pequena' => __('Pequena'),
                'media' => __('Média'),
                'grande' => __('Grande'),
                'indeterminada' => __('Indeterminada'),
                default => self::humanize($raw),
            },
            'technique_used' => match ($raw) {
                'captura_realocacao' => __('Captura e realocação'),
                'exterminacao_quimica' => __('Exterminação química'),
                'exterminacao_fisica' => __('Exterminação física'),
                'nao_realizado' => __('Não realizado'),
                default => self::humanize($raw),
            },
            'colony_destination' => match ($raw) {
                'apicultor' => __('Apicultor'),
                'exterminada' => __('Exterminada'),
                'realocada' => __('Realocada'),
                'abandono_local' => __('Abandono no local'),
                default => self::humanize($raw),
            },
            'sting_severity' => match ($raw) {
                'sem_atendimento' => __('Sem atendimento'),
                'leve' => __('Leve'),
                'moderado_hospitalar' => __('Moderado — hospitalar'),
                'grave' => __('Grave'),
                default => self::humanize($raw),
            },
            'rescue_subtype' => match ($raw) {
                'aquatico' => __('Aquático'),
                'altura' => __('Em altura'),
                'colapso_estrutural' => __('Colapso estrutural'),
                'desencarceramento' => __('Desencarceramento veicular'),
                'espaco_confinado' => __('Espaço confinado'),
                'elevador' => __('Elevador'),
                'outro' => __('Outro'),
                default => self::humanize($raw),
            },
            'victim_condition' => match ($raw) {
                'ileso' => __('Ileso'),
                'ferido_leve' => __('Ferido leve'),
                'ferido_grave' => __('Ferido grave'),
                'obito' => __('Óbito'),
                default => self::humanize($raw),
            },
            default => self::humanize($raw),
        };
    }

    private static function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
