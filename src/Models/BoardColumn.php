<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `board_columns`.
 *
 * As colunas definem o fluxo de trabalho do quadro. A coluna marcada com
 * `is_concluida = 1` é terminal: ao receber uma tarefa, preenche-lhe
 * automaticamente a data de conclusão.
 */
final class BoardColumn
{
    /**
     * Todas as colunas, pela ordem do quadro.
     *
     * @return list<array<string, mixed>>
     */
    public static function todas(): array
    {
        return Database::todos(
            'SELECT id, nome, ordem, cor, is_concluida
             FROM board_columns
             ORDER BY ordem ASC, id ASC'
        );
    }

    /**
     * Uma coluna pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT id, nome, ordem, cor, is_concluida FROM board_columns WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Uma coluna pelo nome exato.
     *
     * @return array<string, mixed>|null
     */
    public static function porNome(string $nome): ?array
    {
        return Database::primeiro(
            'SELECT id, nome, ordem, cor, is_concluida FROM board_columns WHERE nome = :nome LIMIT 1',
            [':nome' => $nome]
        );
    }

    /**
     * Identificador da primeira coluna do quadro, usada como destino por
     * omissão de uma tarefa nova.
     */
    public static function primeiraId(): ?int
    {
        $id = Database::valor('SELECT id FROM board_columns ORDER BY ordem ASC, id ASC LIMIT 1');

        return $id === null ? null : (int) $id;
    }

    /**
     * Identificadores das colunas terminais (as que fecham a tarefa).
     *
     * @return list<int>
     */
    public static function idsConcluidas(): array
    {
        $linhas = Database::todos('SELECT id FROM board_columns WHERE is_concluida = 1');

        return array_map(static fn (array $l): int => (int) $l['id'], $linhas);
    }

    /**
     * Indica se a coluna fecha a tarefa.
     */
    public static function eConcluida(int $id): bool
    {
        return (int) Database::valor(
            'SELECT is_concluida FROM board_columns WHERE id = :id',
            [':id' => $id]
        ) === 1;
    }

    /**
     * Mapa identificador => nome, para consultas que precisam do rótulo.
     *
     * @return array<int, string>
     */
    public static function mapaNomes(): array
    {
        $mapa = [];

        foreach (self::todas() as $coluna) {
            $mapa[(int) $coluna['id']] = (string) $coluna['nome'];
        }

        return $mapa;
    }
}
