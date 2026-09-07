<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `projects`.
 *
 * Os projetos agrupam tarefas e alimentam a secção 4 do relatório semanal,
 * «Projetos em Curso». O `progresso_pct` é o valor que o responsável declara;
 * a par dele existe sempre um progresso calculado a partir das tarefas
 * concluídas, para que os dois possam ser comparados.
 */
final class Project
{
    /** Estados possíveis de um projeto. */
    public const ESTADOS = ['planeado', 'em_curso', 'pausado', 'concluido'];

    /** Prioridades possíveis, iguais às das tarefas. */
    public const PRIORIDADES = ['baixa', 'media', 'alta', 'critica'];

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
     * Cores dos estados, usadas nas etiquetas da listagem.
     *
     * @return array<string, string>
     */
    public static function coresEstado(): array
    {
        return [
            'planeado'  => '#94A3B8',
            'em_curso'  => '#2563EB',
            'pausado'   => '#D97706',
            'concluido' => '#16A34A',
        ];
    }

    /**
     * Rótulos legíveis das prioridades.
     *
     * @return array<string, string>
     */
    public static function rotulosPrioridade(): array
    {
        return Task::rotulosPrioridade();
    }

    /**
     * Lista de projetos com responsável e contagem de tarefas.
     *
     * @param array{status?: ?string, responsavel?: ?int, arquivados?: bool, procura?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public static function todos(array $filtros = []): array
    {
        $condicoes  = [];
        $parametros = [];

        // Por omissão os arquivados ficam de fora da listagem.
        if (empty($filtros['arquivados'])) {
            $condicoes[] = 'p.arquivado = 0';
        }

        if (!empty($filtros['status'])) {
            $condicoes[]           = 'p.status = :status';
            $parametros[':status'] = (string) $filtros['status'];
        }

        if (!empty($filtros['responsavel'])) {
            $condicoes[]                = 'p.responsavel_id = :responsavel';
            $parametros[':responsavel'] = (int) $filtros['responsavel'];
        }

        if (!empty($filtros['procura'])) {
            // Cada marcador nomeado só pode aparecer uma vez na consulta.
            $condicoes[]                      = '(p.nome LIKE :procura_nome OR p.descricao LIKE :procura_descricao)';
            $parametros[':procura_nome']      = '%' . $filtros['procura'] . '%';
            $parametros[':procura_descricao'] = '%' . $filtros['procura'] . '%';
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return Database::todos(
            'SELECT p.*,
                    u.nome AS responsavel_nome,
                    COALESCE(t.total, 0)      AS total_tarefas,
                    COALESCE(t.concluidas, 0) AS tarefas_concluidas
             FROM projects p
             LEFT JOIN users u ON u.id = p.responsavel_id
             LEFT JOIN (
                SELECT tk.project_id,
                       COUNT(*) AS total,
                       SUM(CASE WHEN c.is_concluida = 1 THEN 1 ELSE 0 END) AS concluidas
                FROM tasks tk
                INNER JOIN board_columns c ON c.id = tk.column_id
                GROUP BY tk.project_id
             ) t ON t.project_id = p.id'
            . $onde .
            ' ORDER BY p.arquivado ASC, FIELD(p.status, \'em_curso\', \'planeado\', \'pausado\', \'concluido\'), p.nome ASC',
            $parametros
        );
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
     * Um projeto pelo identificador, com o nome do responsável.
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

    /**
     * Indica se já existe um projeto com o mesmo nome.
     */
    public static function nomeExiste(string $nome, ?int $exceto = null): bool
    {
        $sql        = 'SELECT 1 FROM projects WHERE nome = :nome';
        $parametros = [':nome' => trim($nome)];

        if ($exceto !== null) {
            $sql .= ' AND id <> :exceto';
            $parametros[':exceto'] = $exceto;
        }

        return Database::valor($sql . ' LIMIT 1', $parametros) !== null;
    }

    /**
     * Cria um projeto e devolve o identificador gerado.
     *
     * @param array<string, mixed> $dados
     */
    public static function criar(array $dados): int
    {
        return Database::inserir('projects', [
            'nome'           => $dados['nome'],
            'descricao'      => $dados['descricao'] ?? null,
            'responsavel_id' => $dados['responsavel_id'] ?? null,
            'status'         => $dados['status'] ?? 'planeado',
            'progresso_pct'  => $dados['progresso_pct'] ?? 0,
            'prioridade'     => $dados['prioridade'] ?? 'media',
            'data_inicio'    => $dados['data_inicio'] ?? null,
            'prazo'          => $dados['prazo'] ?? null,
            'arquivado'      => 0,
        ]);
    }

    /**
     * Atualiza um projeto.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        return Database::atualizar('projects', $id, $dados);
    }

    /**
     * Arquiva ou desarquiva um projeto.
     *
     * Os projetos não são apagados: arquivar tira-os dos seletores sem
     * desligar as tarefas que já lhes estão associadas.
     */
    public static function definirArquivado(int $id, bool $arquivado): int
    {
        return Database::atualizar('projects', $id, ['arquivado' => $arquivado ? 1 : 0]);
    }

    /**
     * Tarefas de um projeto, com coluna e responsável.
     *
     * @return list<array<string, mixed>>
     */
    public static function tarefasDe(int $id): array
    {
        return Database::todos(
            'SELECT t.id, t.titulo, t.prioridade, t.column_id, t.data_inicio, t.data_conclusao,
                    t.estimativa_min,
                    c.nome AS coluna_nome, c.cor AS coluna_cor, c.is_concluida, c.ordem AS coluna_ordem,
                    u.nome AS responsavel_nome,
                    COALESCE(SUM(l.minutos), 0) AS total_minutos
             FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             LEFT JOIN users u ON u.id = t.assignee_id
             LEFT JOIN task_time_logs l ON l.task_id = t.id
             WHERE t.project_id = :id
             GROUP BY t.id, t.titulo, t.prioridade, t.column_id, t.data_inicio, t.data_conclusao,
                      t.estimativa_min, c.nome, c.cor, c.is_concluida, c.ordem, u.nome
             ORDER BY c.ordem ASC, t.posicao ASC',
            [':id' => $id]
        );
    }

    /**
     * Números de um projeto: tarefas, conclusões e tempo dedicado.
     *
     * @return array{total: int, concluidas: int, minutos: int, progresso_calculado: int}
     */
    public static function estatisticas(int $id): array
    {
        $linha = Database::primeiro(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN c.is_concluida = 1 THEN 1 ELSE 0 END) AS concluidas
             FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             WHERE t.project_id = :id',
            [':id' => $id]
        );

        $total      = (int) ($linha['total'] ?? 0);
        $concluidas = (int) ($linha['concluidas'] ?? 0);

        $minutos = (int) Database::valor(
            'SELECT COALESCE(SUM(l.minutos), 0)
             FROM task_time_logs l
             INNER JOIN tasks t ON t.id = l.task_id
             WHERE t.project_id = :id',
            [':id' => $id]
        );

        return [
            'total'               => $total,
            'concluidas'          => $concluidas,
            'minutos'             => $minutos,
            // Sem tarefas não há progresso a calcular: fica em zero.
            'progresso_calculado' => $total === 0 ? 0 : (int) round(($concluidas / $total) * 100),
        ];
    }

    /**
     * Progresso calculado de vários projetos de uma só vez.
     *
     * @param array<string, mixed> $projeto Linha vinda de todos()
     */
    public static function progressoCalculado(array $projeto): int
    {
        $total = (int) ($projeto['total_tarefas'] ?? 0);

        if ($total === 0) {
            return 0;
        }

        return (int) round(((int) ($projeto['tarefas_concluidas'] ?? 0) / $total) * 100);
    }
}
