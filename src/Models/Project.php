<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `projects`.
 *
 * Nesta fase serve sobretudo os seletores do quadro Kanban; a gestão
 * completa de projetos entra na fase 3.
 */
final class Project
{
    /** Estados possíveis de um projeto. */
    public const ESTADOS = ['planeado', 'em_curso', 'pausado', 'concluido'];

    /**
     * Rótulos legíveis dos estados.
     *
     * @return array<string, string>
     */
    public static function rotulosEstado(): array
    {
        return [
            'planeado'  => 'Planeado',
            'em_curso'  => 'Em curso',
            'pausado'   => 'Pausado',
            'concluido' => 'Concluído',
        ];
    }

    /**
     * Projetos não arquivados, para seletores.
     *
     * @return list<array<string, mixed>>
     */
    public static function ativos(): array
    {
        return Database::todos(
            'SELECT id, nome, status, progresso_pct, prioridade, responsavel_id
             FROM projects
             WHERE arquivado = 0
             ORDER BY nome ASC'
        );
    }

    /**
     * Um projeto pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT p.*, u.nome AS responsavel_nome
             FROM projects p
             LEFT JOIN users u ON u.id = p.responsavel_id
             WHERE p.id = :id
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Indica se um projeto existe e não está arquivado.
     */
    public static function existeAtivo(int $id): bool
    {
        return Database::valor(
            'SELECT 1 FROM projects WHERE id = :id AND arquivado = 0 LIMIT 1',
            [':id' => $id]
        ) !== null;
    }
}
