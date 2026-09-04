<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `task_activity`.
 *
 * Além de alimentar o histórico apresentado no detalhe da tarefa, esta
 * tabela é a principal fonte do pré-preenchimento do relatório semanal:
 * é por ela que se descobre em que tarefas cada colaborador trabalhou.
 */
final class TaskActivity
{
    public const CRIACAO    = 'criacao';
    public const MOVIMENTO  = 'movimento';
    public const COMENTARIO = 'comentario';
    public const EDICAO     = 'edicao';
    public const CONCLUSAO  = 'conclusao';

    /**
     * Regista uma entrada no histórico de uma tarefa.
     */
    public static function registar(
        int $taskId,
        ?int $userId,
        string $tipo,
        ?string $descricao = null,
        ?int $deColuna = null,
        ?int $paraColuna = null
    ): int {
        return Database::inserir('task_activity', [
            'task_id'        => $taskId,
            'user_id'        => $userId,
            'tipo'           => $tipo,
            'de_column_id'   => $deColuna,
            'para_column_id' => $paraColuna,
            'descricao'      => $descricao === null ? null : mb_substr($descricao, 0, 500),
        ]);
    }

    /**
     * Histórico de uma tarefa, do mais recente para o mais antigo.
     *
     * @return list<array<string, mixed>>
     */
    public static function daTarefa(int $taskId, int $limite = 50): array
    {
        return Database::todos(
            'SELECT a.id, a.tipo, a.descricao, a.created_at,
                    u.nome AS utilizador,
                    co.nome AS coluna_origem,
                    cd.nome AS coluna_destino
             FROM task_activity a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN board_columns co ON co.id = a.de_column_id
             LEFT JOIN board_columns cd ON cd.id = a.para_column_id
             WHERE a.task_id = :task_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . max(1, min($limite, 200)),
            [':task_id' => $taskId]
        );
    }

    /**
     * Rótulos legíveis dos tipos de atividade.
     *
     * @return array<string, string>
     */
    public static function rotulos(): array
    {
        return [
            self::CRIACAO    => 'Criação',
            self::MOVIMENTO  => 'Movimento',
            self::COMENTARIO => 'Comentário',
            self::EDICAO     => 'Edição',
            self::CONCLUSAO  => 'Conclusão',
        ];
    }
}
