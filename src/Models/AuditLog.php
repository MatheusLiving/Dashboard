<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Consulta da tabela `audit_log`.
 *
 * A escrita é feita pelo serviço App\Services\AuditLogger; aqui só se lê,
 * para a página de auditoria.
 */
final class AuditLog
{
    /** Registos apresentados por página. */
    public const POR_PAGINA = 50;

    /**
     * Registos filtrados, com o nome de quem agiu.
     *
     * @param array{entidade?: ?string, user_id?: ?int, acao?: ?string, de?: ?string, ate?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public static function listar(array $filtros = [], int $pagina = 1): array
    {
        [$onde, $parametros] = self::condicoes($filtros);

        $pagina = max(1, $pagina);
        $salto  = ($pagina - 1) * self::POR_PAGINA;

        // LIMIT e OFFSET não aceitam marcadores em prepared statements reais;
        // os valores são inteiros calculados aqui, nunca texto do pedido.
        return Database::todos(
            'SELECT a.*, u.nome AS utilizador
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id'
            . $onde .
            ' ORDER BY a.created_at DESC, a.id DESC'
            . sprintf(' LIMIT %d OFFSET %d', self::POR_PAGINA, $salto),
            $parametros
        );
    }

    /**
     * Total de registos que correspondem aos filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public static function total(array $filtros = []): int
    {
        [$onde, $parametros] = self::condicoes($filtros);

        return (int) Database::valor('SELECT COUNT(*) FROM audit_log a' . $onde, $parametros);
    }

    /**
     * Entidades presentes no registo, para o seletor de filtro.
     *
     * @return list<string>
     */
    public static function entidades(): array
    {
        $linhas = Database::todos('SELECT DISTINCT entidade FROM audit_log ORDER BY entidade ASC');

        return array_map(static fn (array $l): string => (string) $l['entidade'], $linhas);
    }

    /**
     * Ações presentes no registo, para o seletor de filtro.
     *
     * @return list<string>
     */
    public static function acoes(): array
    {
        $linhas = Database::todos('SELECT DISTINCT acao FROM audit_log ORDER BY acao ASC');

        return array_map(static fn (array $l): string => (string) $l['acao'], $linhas);
    }

    /**
     * Número de registos por ação, para o resumo do topo da página.
     *
     * @param array<string, mixed> $filtros
     * @return list<array{acao: string, total: int}>
     */
    public static function resumoPorAcao(array $filtros = []): array
    {
        [$onde, $parametros] = self::condicoes($filtros);

        $linhas = Database::todos(
            'SELECT a.acao, COUNT(*) AS total FROM audit_log a'
            . $onde .
            ' GROUP BY a.acao ORDER BY total DESC LIMIT 8',
            $parametros
        );

        return array_map(
            static fn (array $l): array => ['acao' => (string) $l['acao'], 'total' => (int) $l['total']],
            $linhas
        );
    }

    /**
     * Monta as condições e os parâmetros a partir dos filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function condicoes(array $filtros): array
    {
        $condicoes  = [];
        $parametros = [];

        if (!empty($filtros['entidade'])) {
            $condicoes[]             = 'a.entidade = :entidade';
            $parametros[':entidade'] = (string) $filtros['entidade'];
        }

        if (!empty($filtros['acao'])) {
            $condicoes[]         = 'a.acao = :acao';
            $parametros[':acao'] = (string) $filtros['acao'];
        }

        if (!empty($filtros['user_id'])) {
            $condicoes[]        = 'a.user_id = :uid';
            $parametros[':uid'] = (int) $filtros['user_id'];
        }

        if (!empty($filtros['de'])) {
            $condicoes[]      = 'a.created_at >= :de';
            $parametros[':de'] = $filtros['de'] . ' 00:00:00';
        }

        if (!empty($filtros['ate'])) {
            $condicoes[]        = 'a.created_at <= :ate';
            $parametros[':ate'] = $filtros['ate'] . ' 23:59:59';
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return [$onde, $parametros];
    }

    /**
     * Rótulos legíveis das ações.
     *
     * @return array<string, string>
     */
    public static function rotulosAcao(): array
    {
        return [
            'criar'        => 'Criação',
            'atualizar'    => 'Alteração',
            'eliminar'     => 'Eliminação',
            'mover'        => 'Movimento',
            'entregar'     => 'Entrega',
            'gerar'        => 'Geração',
            'ativar'       => 'Ativação',
            'desativar'    => 'Desativação',
            'arquivar'     => 'Arquivo',
            'desarquivar'  => 'Reposição',
        ];
    }

    /**
     * Rótulos legíveis das entidades.
     *
     * @return array<string, string>
     */
    public static function rotulosEntidade(): array
    {
        return [
            'tarefa'         => 'Tarefa',
            'projeto'        => 'Projeto',
            'etiqueta'       => 'Etiqueta',
            'utilizador'     => 'Utilizador',
            'relatorio'      => 'Relatório',
            'registo_tempo'  => 'Registo de tempo',
            'configuracao'   => 'Configuração',
        ];
    }
}
