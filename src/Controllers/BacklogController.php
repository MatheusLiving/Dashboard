<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Visão de backlog.
 *
 * Reúne as tarefas que ainda não entraram no fluxo de trabalho — as que estão
 * na primeira coluna do quadro — agrupadas por projeto, para que possam ser
 * arrastadas diretamente para as colunas ativas.
 */
final class BacklogController extends Controller
{
    public function index(): void
    {
        $colunas = BoardColumn::todas();

        if ($colunas === []) {
            $this->ver('backlog/index', [
                'grupos'       => [],
                'colunas'      => [],
                'colunaOrigem' => null,
                'filtros'      => [],
                'temFiltros'   => false,
                'utilizadores' => [],
                'prioridades'  => Task::rotulosPrioridade(),
                'cores'        => Task::coresPrioridade(),
                'total'        => 0,
            ]);

            return;
        }

        // A primeira coluna do quadro é, por convenção, o backlog.
        $origem  = $colunas[0];
        $destinos = array_values(array_filter(
            $colunas,
            static fn (array $c): bool => (int) $c['id'] !== (int) $origem['id']
        ));

        $filtros = $this->filtros();
        $tarefas = $this->tarefasDoBacklog((int) $origem['id'], $filtros);

        $etiquetas = Task::tagsDeVarias(
            array_map(static fn (array $t): int => (int) $t['id'], $tarefas)
        );

        foreach ($tarefas as $indice => $tarefa) {
            $tarefas[$indice]['tags'] = $etiquetas[(int) $tarefa['id']] ?? [];
        }

        $this->ver('backlog/index', [
            'grupos'       => $this->agruparPorProjeto($tarefas),
            'colunas'      => $destinos,
            'colunaOrigem' => $origem,
            'filtros'      => $filtros,
            'temFiltros'   => $filtros['responsavel'] !== null
                || $filtros['prioridade'] !== null
                || $filtros['procura'] !== null,
            'utilizadores' => User::todos(),
            'projetos'     => Project::ativos(),
            'prioridades'  => Task::rotulosPrioridade(),
            'cores'        => Task::coresPrioridade(),
            'total'        => count($tarefas),
        ]);
    }

    /**
     * Tarefas da coluna de backlog, com projeto e responsável resolvidos.
     *
     * @param array<string, mixed> $filtros
     * @return list<array<string, mixed>>
     */
    private function tarefasDoBacklog(int $colunaId, array $filtros): array
    {
        $condicoes  = ['t.column_id = :coluna'];
        $parametros = [':coluna' => $colunaId];

        if ($filtros['responsavel'] !== null) {
            $condicoes[]                = 't.assignee_id = :responsavel';
            $parametros[':responsavel'] = $filtros['responsavel'];
        }

        if ($filtros['prioridade'] !== null) {
            $condicoes[]               = 't.prioridade = :prioridade';
            $parametros[':prioridade'] = $filtros['prioridade'];
        }

        if ($filtros['procura'] !== null) {
            $condicoes[]                      = '(t.titulo LIKE :procura_titulo OR t.descricao LIKE :procura_descricao)';
            $parametros[':procura_titulo']    = '%' . $filtros['procura'] . '%';
            $parametros[':procura_descricao'] = '%' . $filtros['procura'] . '%';
        }

        return Database::todos(
            'SELECT t.id, t.titulo, t.descricao, t.column_id, t.project_id, t.assignee_id,
                    t.prioridade, t.posicao, t.estimativa_min, t.data_inicio, t.data_conclusao,
                    u.nome AS responsavel_nome,
                    p.nome AS projeto_nome,
                    p.status AS projeto_status,
                    COALESCE(l.total_minutos, 0) AS total_minutos
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assignee_id
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN (
                SELECT task_id, SUM(minutos) AS total_minutos
                FROM task_time_logs
                GROUP BY task_id
             ) l ON l.task_id = t.id
             WHERE ' . implode(' AND ', $condicoes) .
            ' ORDER BY p.nome IS NULL, p.nome ASC, t.posicao ASC, t.id ASC',
            $parametros
        );
    }

    /**
     * Agrupa as tarefas por projeto, deixando as sem projeto no fim.
     *
     * @param list<array<string, mixed>> $tarefas
     * @return list<array{id: ?int, nome: string, status: ?string, tarefas: list<array<string, mixed>>}>
     */
    private function agruparPorProjeto(array $tarefas): array
    {
        $grupos = [];

        foreach ($tarefas as $tarefa) {
            $projetoId = $tarefa['project_id'] === null ? 0 : (int) $tarefa['project_id'];

            if (!isset($grupos[$projetoId])) {
                $grupos[$projetoId] = [
                    'id'      => $projetoId === 0 ? null : $projetoId,
                    'nome'    => $projetoId === 0 ? 'Sem projeto' : (string) $tarefa['projeto_nome'],
                    'status'  => $projetoId === 0 ? null : ($tarefa['projeto_status'] ?? null),
                    'tarefas' => [],
                ];
            }

            $grupos[$projetoId]['tarefas'][] = $tarefa;
        }

        // As tarefas sem projeto ficam sempre no fim da página.
        $semProjeto = $grupos[0] ?? null;
        unset($grupos[0]);

        $lista = array_values($grupos);

        if ($semProjeto !== null) {
            $lista[] = $semProjeto;
        }

        return $lista;
    }

    /**
     * Filtros aceites na visão de backlog.
     *
     * @return array{responsavel: ?int, prioridade: ?string, procura: ?string}
     */
    private function filtros(): array
    {
        $prioridade = Request::query('prioridade');

        return [
            'responsavel' => Request::queryInt('responsavel'),
            'prioridade'  => in_array($prioridade, Task::PRIORIDADES, true) ? $prioridade : null,
            'procura'     => Request::query('procura') ?: null,
        ];
    }
}
