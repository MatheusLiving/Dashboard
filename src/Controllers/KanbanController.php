<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;

/**
 * Quadro Kanban: colunas, cartões e filtros.
 */
final class KanbanController extends Controller
{
    public function index(): void
    {
        $filtros = $this->filtros();
        $colunas = BoardColumn::todas();
        $tarefas = Task::paraQuadro($filtros);

        // As etiquetas de todas as tarefas são pedidas de uma só vez, para
        // não fazer uma consulta por cartão.
        $etiquetas = Task::tagsDeVarias(
            array_map(static fn (array $t): int => (int) $t['id'], $tarefas)
        );

        // Agrupa os cartões pela coluna a que pertencem.
        $porColuna = [];
        foreach ($colunas as $coluna) {
            $porColuna[(int) $coluna['id']] = [];
        }

        foreach ($tarefas as $tarefa) {
            $colunaId = (int) $tarefa['column_id'];

            if (!isset($porColuna[$colunaId])) {
                continue;
            }

            $tarefa['tags']            = $etiquetas[(int) $tarefa['id']] ?? [];
            $porColuna[$colunaId][]    = $tarefa;
        }

        $this->ver('kanban/index', [
            'colunas'     => $colunas,
            'porColuna'   => $porColuna,
            'filtros'     => $filtros,
            'temFiltros'  => array_filter($filtros, static fn (mixed $v): bool => $v !== null && $v !== '') !== [],
            'utilizadores' => User::todos(),
            'projetos'    => Project::ativos(),
            'etiquetas'   => Tag::todas(),
            'prioridades' => Task::rotulosPrioridade(),
            'cores'       => Task::coresPrioridade(),
            'totalCartoes' => count($tarefas),
        ]);
    }

    /**
     * Filtros aceites na barra de topo, lidos da query string.
     *
     * @return array{responsavel: ?int, tag: ?int, projeto: ?int, prioridade: ?string, procura: ?string}
     */
    private function filtros(): array
    {
        $prioridade = Request::query('prioridade');

        return [
            'responsavel' => Request::queryInt('responsavel'),
            'tag'         => Request::queryInt('tag'),
            'projeto'     => Request::queryInt('projeto'),
            // Um valor fora da lista é simplesmente ignorado.
            'prioridade'  => in_array($prioridade, Task::PRIORIDADES, true) ? $prioridade : null,
            'procura'     => Request::query('procura') ?: null,
        ];
    }
}
