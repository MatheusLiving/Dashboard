<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Semana;
use App\Models\Setting;

/**
 * Pré-preenchimento do relatório semanal.
 *
 * Reúne, a partir do trabalho registado no quadro, aquilo que um colaborador
 * teria de escrever à mão nas secções 2, 3, 4 e 7. Tudo o que sai daqui é
 * uma proposta: o utilizador edita, remove e acrescenta linhas antes de
 * gravar. Nada é escrito em base de dados por esta classe.
 */
final class ReportBuilder
{
    private string $inicio;
    private string $fim;

    public function __construct(
        private readonly int $userId,
        private readonly int $ano,
        private readonly int $semana
    ) {
        $intervalo    = Semana::intervalo($ano, $semana);
        $this->inicio = $intervalo['inicio'];
        $this->fim    = $intervalo['fim'];
    }

    /**
     * Todas as secções pré-preenchidas.
     *
     * @return array{
     *     report_activities: list<array<string, mixed>>,
     *     report_incidents: list<array<string, mixed>>,
     *     report_projects: list<array<string, mixed>>,
     *     report_next_week: list<array<string, mixed>>,
     *     dificuldades: string
     * }
     */
    public function construir(): array
    {
        return [
            'report_activities' => $this->atividades(),
            'report_incidents'  => $this->incidentes(),
            'report_projects'   => $this->projetos(),
            'report_next_week'  => $this->proximaSemana(),
            'dificuldades'      => $this->dificuldades(),
        ];
    }

    /**
     * Secção 2 — Atividades Realizadas.
     *
     * Considera as tarefas em que o colaborador teve atividade registada na
     * semana, seja por movimento no quadro, seja por registo de tempo. A data
     * apresentada é a do trabalho, não a de criação da tarefa.
     *
     * @return list<array<string, mixed>>
     */
    public function atividades(): array
    {
        $linhas = Database::todos(
            'SELECT t.id AS task_id,
                    t.titulo,
                    c.nome AS estado,
                    p.nome AS projeto_nome,
                    tempo.minutos AS tempo_dedicado_min,
                    COALESCE(tempo.primeira_data, mov.primeira_data) AS data_trabalho
             FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN (
                SELECT task_id, SUM(minutos) AS minutos, MIN(data) AS primeira_data
                FROM task_time_logs
                WHERE user_id = :uid_logs AND data BETWEEN :inicio_logs AND :fim_logs
                GROUP BY task_id
             ) tempo ON tempo.task_id = t.id
             LEFT JOIN (
                SELECT task_id, MIN(DATE(created_at)) AS primeira_data
                FROM task_activity
                WHERE user_id = :uid_act AND DATE(created_at) BETWEEN :inicio_act AND :fim_act
                GROUP BY task_id
             ) mov ON mov.task_id = t.id
             WHERE tempo.task_id IS NOT NULL OR mov.task_id IS NOT NULL
             ORDER BY data_trabalho ASC, t.titulo ASC',
            [
                ':uid_logs'    => $this->userId,
                ':inicio_logs' => $this->inicio,
                ':fim_logs'    => $this->fim,
                ':uid_act'     => $this->userId,
                ':inicio_act'  => $this->inicio,
                ':fim_act'     => $this->fim,
            ]
        );

        $atividades = [];

        foreach ($linhas as $linha) {
            $atividades[] = [
                'task_id'            => (int) $linha['task_id'],
                'data'               => $linha['data_trabalho'],
                'descricao'          => (string) $linha['titulo'],
                // Sem projeto associado, as etiquetas identificam a área.
                'projeto_area'       => $linha['projeto_nome'] ?? $this->areaPorEtiquetas((int) $linha['task_id']),
                'estado'             => (string) $linha['estado'],
                'tempo_dedicado_min' => $linha['tempo_dedicado_min'] === null
                    ? null
                    : (int) $linha['tempo_dedicado_min'],
            ];
        }

        return $atividades;
    }

    /**
     * Secção 3 — Incidentes e Pedidos de Suporte.
     *
     * São as tarefas da semana marcadas com as etiquetas configuradas como
     * indicadoras de incidente (por omissão «suporte» e «incidente»).
     *
     * @return list<array<string, mixed>>
     */
    public function incidentes(): array
    {
        $slugs = Setting::tagsIncidente();

        if ($slugs === []) {
            return [];
        }

        // Um marcador por slug: a lista é variável mas cada valor vai ligado.
        $marcadores = [];
        $parametros = [
            ':uid_logs'    => $this->userId,
            ':inicio_logs' => $this->inicio,
            ':fim_logs'    => $this->fim,
            ':uid_act'     => $this->userId,
            ':inicio_act'  => $this->inicio,
            ':fim_act'     => $this->fim,
        ];

        foreach (array_values($slugs) as $indice => $slug) {
            $marcador              = ':slug' . $indice;
            $marcadores[]          = $marcador;
            $parametros[$marcador] = $slug;
        }

        $linhas = Database::todos(
            'SELECT DISTINCT t.id AS task_id, t.titulo, t.descricao, t.prioridade,
                    c.nome AS estado, c.is_concluida
             FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             INNER JOIN task_tag tt ON tt.task_id = t.id
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE tg.slug IN (' . implode(', ', $marcadores) . ')
               AND (
                    EXISTS (
                        SELECT 1 FROM task_time_logs l
                        WHERE l.task_id = t.id AND l.user_id = :uid_logs
                          AND l.data BETWEEN :inicio_logs AND :fim_logs
                    )
                    OR EXISTS (
                        SELECT 1 FROM task_activity a
                        WHERE a.task_id = t.id AND a.user_id = :uid_act
                          AND DATE(a.created_at) BETWEEN :inicio_act AND :fim_act
                    )
               )
             ORDER BY FIELD(t.prioridade, \'critica\', \'alta\', \'media\', \'baixa\'), t.titulo ASC',
            $parametros
        );

        $incidentes = [];

        foreach ($linhas as $linha) {
            $incidentes[] = [
                'task_id'         => (int) $linha['task_id'],
                'descricao'       => (string) $linha['titulo'],
                'prioridade'      => ucfirst((string) $linha['prioridade']),
                'estado'          => (string) $linha['estado'],
                // A nota de resolução vem do histórico de conclusão, se houver.
                'resolucao_notas' => $this->notaDeResolucao((int) $linha['task_id']),
            ];
        }

        return $incidentes;
    }

    /**
     * Secção 4 — Projetos em Curso.
     *
     * Projetos ativos de que o colaborador é responsável, ou onde teve
     * tarefas com trabalho registado durante a semana.
     *
     * @return list<array<string, mixed>>
     */
    public function projetos(): array
    {
        $linhas = Database::todos(
            'SELECT DISTINCT p.id AS project_id, p.nome, p.progresso_pct, p.status, p.prazo,
                    p.responsavel_id
             FROM projects p
             LEFT JOIN tasks t ON t.project_id = p.id
             WHERE p.arquivado = 0
               AND (
                    p.responsavel_id = :uid_resp
                    OR EXISTS (
                        SELECT 1 FROM task_time_logs l
                        WHERE l.task_id = t.id AND l.user_id = :uid_logs
                          AND l.data BETWEEN :inicio_logs AND :fim_logs
                    )
                    OR EXISTS (
                        SELECT 1 FROM task_activity a
                        WHERE a.task_id = t.id AND a.user_id = :uid_act
                          AND DATE(a.created_at) BETWEEN :inicio_act AND :fim_act
                    )
               )
             ORDER BY FIELD(p.status, \'em_curso\', \'planeado\', \'pausado\', \'concluido\'), p.nome ASC',
            [
                ':uid_resp'    => $this->userId,
                ':uid_logs'    => $this->userId,
                ':inicio_logs' => $this->inicio,
                ':fim_logs'    => $this->fim,
                ':uid_act'     => $this->userId,
                ':inicio_act'  => $this->inicio,
                ':fim_act'     => $this->fim,
            ]
        );

        $projetos = [];

        foreach ($linhas as $linha) {
            $projetoId = (int) $linha['project_id'];

            $projetos[] = [
                'project_id'      => $projetoId,
                // O nome é copiado: se o projeto for renomeado depois da
                // entrega, o relatório mantém o nome que tinha na altura.
                'nome_snapshot'   => (string) $linha['nome'],
                'progresso'       => $linha['progresso_pct'] . '%',
                'proximos_passos' => $this->proximosPassosDe($projetoId),
                'observacoes'     => $this->observacoesDe($linha),
            ];
        }

        return $projetos;
    }

    /**
     * Secção 7 — Planeamento para a Próxima Semana.
     *
     * Tarefas atribuídas ao colaborador que estão em colunas ativas (nem a
     * primeira do quadro, nem terminal) ou que têm prazo à frente.
     *
     * @return list<array<string, mixed>>
     */
    public function proximaSemana(): array
    {
        $linhas = Database::todos(
            'SELECT t.id AS task_id, t.titulo, t.prioridade, p.prazo AS prazo_projeto,
                    c.nome AS coluna_nome, c.ordem
             FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             LEFT JOIN projects p ON p.id = t.project_id
             WHERE t.assignee_id = :uid
               AND c.is_concluida = 0
               AND c.ordem > (SELECT MIN(ordem) FROM board_columns)
             ORDER BY FIELD(t.prioridade, \'critica\', \'alta\', \'media\', \'baixa\'), c.ordem DESC, t.posicao ASC',
            [':uid' => $this->userId]
        );

        $proximas = [];

        foreach ($linhas as $linha) {
            $proximas[] = [
                'task_id'    => (int) $linha['task_id'],
                'tarefa'     => (string) $linha['titulo'],
                'prioridade' => ucfirst((string) $linha['prioridade']),
                'prazo'      => $linha['prazo_projeto'],
            ];
        }

        return $proximas;
    }

    /**
     * Texto para a secção de dificuldades, reunindo o que foi anotado nas
     * tarefas em que o colaborador trabalhou durante a semana.
     */
    public function dificuldades(): string
    {
        $linhas = Database::todos(
            'SELECT DISTINCT t.titulo, t.dificuldades
             FROM tasks t
             WHERE t.dificuldades IS NOT NULL AND t.dificuldades <> \'\'
               AND (
                    EXISTS (
                        SELECT 1 FROM task_time_logs l
                        WHERE l.task_id = t.id AND l.user_id = :uid_logs
                          AND l.data BETWEEN :inicio_logs AND :fim_logs
                    )
                    OR EXISTS (
                        SELECT 1 FROM task_activity a
                        WHERE a.task_id = t.id AND a.user_id = :uid_act
                          AND DATE(a.created_at) BETWEEN :inicio_act AND :fim_act
                    )
               )
             ORDER BY t.titulo ASC',
            [
                ':uid_logs'    => $this->userId,
                ':inicio_logs' => $this->inicio,
                ':fim_logs'    => $this->fim,
                ':uid_act'     => $this->userId,
                ':inicio_act'  => $this->inicio,
                ':fim_act'     => $this->fim,
            ]
        );

        $partes = [];

        foreach ($linhas as $linha) {
            $partes[] = sprintf('%s: %s', $linha['titulo'], $linha['dificuldades']);
        }

        return implode("\n", $partes);
    }

    /**
     * Números da semana, apresentados no topo do formulário.
     *
     * @return array{tarefas: int, concluidas: int, minutos: int, incidentes: int}
     */
    public function resumo(): array
    {
        $atividades = $this->atividades();

        $concluidas = (int) Database::valor(
            'SELECT COUNT(*) FROM tasks
             WHERE assignee_id = :uid AND data_conclusao BETWEEN :inicio AND :fim',
            [':uid' => $this->userId, ':inicio' => $this->inicio, ':fim' => $this->fim]
        );

        $minutos = (int) Database::valor(
            'SELECT COALESCE(SUM(minutos), 0) FROM task_time_logs
             WHERE user_id = :uid AND data BETWEEN :inicio AND :fim',
            [':uid' => $this->userId, ':inicio' => $this->inicio, ':fim' => $this->fim]
        );

        return [
            'tarefas'    => count($atividades),
            'concluidas' => $concluidas,
            'minutos'    => $minutos,
            'incidentes' => count($this->incidentes()),
        ];
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Etiquetas de uma tarefa, usadas como área quando não há projeto.
     */
    private function areaPorEtiquetas(int $taskId): ?string
    {
        $linhas = Database::todos(
            'SELECT tg.nome
             FROM task_tag tt
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE tt.task_id = :tid
             ORDER BY tg.nome ASC',
            [':tid' => $taskId]
        );

        if ($linhas === []) {
            return null;
        }

        return implode(', ', array_map(static fn (array $l): string => (string) $l['nome'], $linhas));
    }

    /**
     * Descrição do registo de conclusão de uma tarefa, se existir.
     */
    private function notaDeResolucao(int $taskId): ?string
    {
        $nota = Database::valor(
            'SELECT descricao FROM task_activity
             WHERE task_id = :tid AND tipo = \'conclusao\'
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            [':tid' => $taskId]
        );

        return is_string($nota) && $nota !== '' ? $nota : null;
    }

    /**
     * Próximos passos de um projeto: as tarefas ainda por concluir.
     */
    private function proximosPassosDe(int $projetoId): ?string
    {
        $linhas = Database::todos(
            'SELECT t.titulo
             FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             WHERE t.project_id = :pid AND c.is_concluida = 0
             ORDER BY FIELD(t.prioridade, \'critica\', \'alta\', \'media\', \'baixa\'), c.ordem DESC
             LIMIT 3',
            [':pid' => $projetoId]
        );

        if ($linhas === []) {
            return null;
        }

        return implode('; ', array_map(static fn (array $l): string => (string) $l['titulo'], $linhas));
    }

    /**
     * Observação automática sobre o estado de um projeto.
     *
     * @param array<string, mixed> $projeto
     */
    private function observacoesDe(array $projeto): ?string
    {
        $notas = [];

        if ($projeto['status'] === 'pausado') {
            $notas[] = 'Projeto pausado.';
        }

        if (
            $projeto['prazo'] !== null
            && $projeto['status'] !== 'concluido'
            && strtotime((string) $projeto['prazo']) < strtotime($this->fim)
        ) {
            $notas[] = sprintf('Prazo ultrapassado (%s).', date('d/m/Y', strtotime((string) $projeto['prazo'])));
        }

        return $notas === [] ? null : implode(' ', $notas);
    }
}
