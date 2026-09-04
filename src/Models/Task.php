<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `tasks` e às suas relações.
 *
 * É a entidade central da aplicação: alimenta o quadro Kanban e, através do
 * histórico e dos registos de tempo, o pré-preenchimento do relatório semanal.
 */
final class Task
{
    /** Prioridades possíveis, da mais baixa para a mais alta. */
    public const PRIORIDADES = ['baixa', 'media', 'alta', 'critica'];

    /**
     * Rótulos legíveis das prioridades.
     *
     * @return array<string, string>
     */
    public static function rotulosPrioridade(): array
    {
        return [
            'baixa'   => 'Baixa',
            'media'   => 'Média',
            'alta'    => 'Alta',
            'critica' => 'Crítica',
        ];
    }

    /**
     * Cores das prioridades, usadas no indicador do cartão.
     *
     * @return array<string, string>
     */
    public static function coresPrioridade(): array
    {
        return [
            'baixa'   => '#94A3B8',
            'media'   => '#3B82F6',
            'alta'    => '#F59E0B',
            'critica' => '#DC2626',
        ];
    }

    /**
     * Tarefas do quadro, com responsável e projeto, aplicando os filtros do topo.
     *
     * @param array{responsavel?: ?int, tag?: ?int, projeto?: ?int, prioridade?: ?string, procura?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public static function paraQuadro(array $filtros = []): array
    {
        $condicoes  = [];
        $parametros = [];

        if (!empty($filtros['responsavel'])) {
            $condicoes[]              = 't.assignee_id = :responsavel';
            $parametros[':responsavel'] = (int) $filtros['responsavel'];
        }

        if (!empty($filtros['projeto'])) {
            $condicoes[]           = 't.project_id = :projeto';
            $parametros[':projeto'] = (int) $filtros['projeto'];
        }

        if (!empty($filtros['prioridade'])) {
            $condicoes[]              = 't.prioridade = :prioridade';
            $parametros[':prioridade'] = (string) $filtros['prioridade'];
        }

        if (!empty($filtros['tag'])) {
            // EXISTS em vez de JOIN: evita duplicar tarefas com várias etiquetas.
            $condicoes[]       = 'EXISTS (SELECT 1 FROM task_tag ft WHERE ft.task_id = t.id AND ft.tag_id = :tag)';
            $parametros[':tag'] = (int) $filtros['tag'];
        }

        if (!empty($filtros['procura'])) {
            $condicoes[]           = '(t.titulo LIKE :procura OR t.descricao LIKE :procura)';
            $parametros[':procura'] = '%' . $filtros['procura'] . '%';
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return Database::todos(
            'SELECT t.id, t.titulo, t.descricao, t.column_id, t.project_id, t.assignee_id,
                    t.prioridade, t.posicao, t.data_inicio, t.data_conclusao, t.estimativa_min,
                    t.dificuldades, t.created_at, t.updated_at,
                    u.nome AS responsavel_nome,
                    p.nome AS projeto_nome,
                    COALESCE(tl.total_minutos, 0) AS total_minutos
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assignee_id
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN (
                SELECT task_id, SUM(minutos) AS total_minutos
                FROM task_time_logs
                GROUP BY task_id
             ) tl ON tl.task_id = t.id'
            . $onde .
            ' ORDER BY t.posicao ASC, t.id ASC',
            $parametros
        );
    }

    /**
     * Etiquetas de um conjunto de tarefas, indexadas pelo identificador da tarefa.
     *
     * Evita N+1 consultas ao desenhar o quadro: pede-se tudo de uma vez.
     *
     * @param list<int> $taskIds
     * @return array<int, list<array<string, mixed>>>
     */
    public static function tagsDeVarias(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        // Os identificadores são inteiros validados, mas continuam a seguir
        // como marcadores ligados, um por valor.
        $marcadores = [];
        $parametros = [];

        foreach (array_values($taskIds) as $indice => $id) {
            $marcador               = ':id' . $indice;
            $marcadores[]           = $marcador;
            $parametros[$marcador]  = (int) $id;
        }

        $linhas = Database::todos(
            'SELECT tt.task_id, tg.id, tg.nome, tg.slug, tg.cor_hex, tg.ativa
             FROM task_tag tt
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE tt.task_id IN (' . implode(', ', $marcadores) . ')
             ORDER BY tg.nome ASC',
            $parametros
        );

        $mapa = [];

        foreach ($linhas as $linha) {
            $mapa[(int) $linha['task_id']][] = [
                'id'      => (int) $linha['id'],
                'nome'    => (string) $linha['nome'],
                'slug'    => (string) $linha['slug'],
                'cor_hex' => (string) $linha['cor_hex'],
                'ativa'   => (int) $linha['ativa'],
            ];
        }

        return $mapa;
    }

    /**
     * Uma tarefa com os dados de apoio já resolvidos.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT t.*, u.nome AS responsavel_nome, p.nome AS projeto_nome, c.nome AS coluna_nome,
                    c.is_concluida, cr.nome AS criador_nome
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assignee_id
             LEFT JOIN users cr ON cr.id = t.criado_por
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN board_columns c ON c.id = t.column_id
             WHERE t.id = :id
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Etiquetas de uma tarefa.
     *
     * @return list<array<string, mixed>>
     */
    public static function tagsDe(int $taskId): array
    {
        return Database::todos(
            'SELECT tg.id, tg.nome, tg.slug, tg.cor_hex, tg.ativa
             FROM task_tag tt
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE tt.task_id = :task_id
             ORDER BY tg.nome ASC',
            [':task_id' => $taskId]
        );
    }

    /**
     * Cria uma tarefa no fim da coluna indicada e devolve o identificador.
     *
     * @param array<string, mixed> $dados
     */
    public static function criar(array $dados): int
    {
        $colunaId = (int) $dados['column_id'];

        return Database::inserir('tasks', [
            'titulo'         => $dados['titulo'],
            'descricao'      => $dados['descricao'] ?? null,
            'column_id'      => $colunaId,
            'project_id'     => $dados['project_id'] ?? null,
            'assignee_id'    => $dados['assignee_id'] ?? null,
            'criado_por'     => $dados['criado_por'] ?? null,
            'prioridade'     => $dados['prioridade'] ?? 'media',
            'posicao'        => self::proximaPosicao($colunaId),
            'data_inicio'    => $dados['data_inicio'] ?? null,
            'data_conclusao' => $dados['data_conclusao'] ?? null,
            'estimativa_min' => $dados['estimativa_min'] ?? null,
            'dificuldades'   => $dados['dificuldades'] ?? null,
        ]);
    }

    /**
     * Atualiza os campos editáveis de uma tarefa.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        return Database::atualizar('tasks', $id, $dados);
    }

    /**
     * Elimina uma tarefa. As etiquetas, registos de tempo e histórico são
     * removidos em cascata pelas chaves estrangeiras.
     */
    public static function eliminar(int $id): int
    {
        return Database::executar('DELETE FROM tasks WHERE id = :id', [':id' => $id]);
    }

    /**
     * Substitui as etiquetas de uma tarefa pelo conjunto indicado.
     *
     * @param list<int> $tagIds
     */
    public static function sincronizarTags(int $taskId, array $tagIds): void
    {
        Database::executar('DELETE FROM task_tag WHERE task_id = :task_id', [':task_id' => $taskId]);

        foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
            if ($tagId <= 0) {
                continue;
            }

            Database::executar(
                'INSERT IGNORE INTO task_tag (task_id, tag_id) VALUES (:task_id, :tag_id)',
                [':task_id' => $taskId, ':tag_id' => $tagId]
            );
        }
    }

    /**
     * Próxima posição livre no fim de uma coluna.
     */
    public static function proximaPosicao(int $colunaId): int
    {
        $maxima = Database::valor(
            'SELECT MAX(posicao) FROM tasks WHERE column_id = :coluna',
            [':coluna' => $colunaId]
        );

        return $maxima === null ? 1 : ((int) $maxima + 1);
    }

    /**
     * Reordena uma coluna a partir da sequência de identificadores recebida.
     *
     * Só são atualizadas tarefas que realmente pertençam à coluna indicada:
     * a ordem vem do cliente e nunca é tomada como verdade sem verificação.
     *
     * @param list<int> $ordem
     */
    public static function reordenarColuna(int $colunaId, array $ordem): void
    {
        $posicao = 1;

        foreach ($ordem as $taskId) {
            $taskId = (int) $taskId;

            if ($taskId <= 0) {
                continue;
            }

            Database::executar(
                'UPDATE tasks SET posicao = :posicao WHERE id = :id AND column_id = :coluna',
                [':posicao' => $posicao, ':id' => $taskId, ':coluna' => $colunaId]
            );

            $posicao++;
        }
    }

    /**
     * Identificadores das tarefas de uma coluna, pela ordem atual.
     *
     * @return list<int>
     */
    public static function idsDaColuna(int $colunaId): array
    {
        $linhas = Database::todos(
            'SELECT id FROM tasks WHERE column_id = :coluna ORDER BY posicao ASC, id ASC',
            [':coluna' => $colunaId]
        );

        return array_map(static fn (array $l): int => (int) $l['id'], $linhas);
    }

    /**
     * Contagem de tarefas por coluna, respeitando os filtros aplicados.
     *
     * @param array<string, mixed> $filtros
     * @return array<int, int>
     */
    public static function contagemPorColuna(array $filtros = []): array
    {
        $contagem = [];

        foreach (self::paraQuadro($filtros) as $tarefa) {
            $colunaId            = (int) $tarefa['column_id'];
            $contagem[$colunaId] = ($contagem[$colunaId] ?? 0) + 1;
        }

        return $contagem;
    }
}
