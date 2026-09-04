<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Semana;

/**
 * Painel inicial: resumo da semana corrente para o utilizador autenticado.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $utilizadorId = (int) Auth::id();
        $semana       = Semana::corrente();
        $intervalo    = Semana::intervalo($semana['ano'], $semana['semana']);

        $this->ver('dashboard/index', [
            'semana'     => $semana,
            'rotulo'     => Semana::rotulo($semana['ano'], $semana['semana']),
            'resumo'     => $this->resumo($utilizadorId, $intervalo['inicio'], $intervalo['fim']),
            'porColuna'  => $this->porColuna($utilizadorId),
            'atividade'  => $this->atividadeRecente($utilizadorId),
        ]);
    }

    /**
     * Números da semana: tarefas atribuídas, concluídas e tempo registado.
     *
     * @return array{atribuidas: int, concluidas: int, minutos: int, incidentes: int}
     */
    private function resumo(int $utilizadorId, string $inicio, string $fim): array
    {
        $atribuidas = (int) Database::valor(
            'SELECT COUNT(*) FROM tasks t
             INNER JOIN board_columns c ON c.id = t.column_id
             WHERE t.assignee_id = :uid AND c.is_concluida = 0',
            [':uid' => $utilizadorId]
        );

        $concluidas = (int) Database::valor(
            'SELECT COUNT(*) FROM tasks
             WHERE assignee_id = :uid AND data_conclusao BETWEEN :inicio AND :fim',
            [':uid' => $utilizadorId, ':inicio' => $inicio, ':fim' => $fim]
        );

        $minutos = (int) Database::valor(
            'SELECT COALESCE(SUM(minutos), 0) FROM task_time_logs
             WHERE user_id = :uid AND data BETWEEN :inicio AND :fim',
            [':uid' => $utilizadorId, ':inicio' => $inicio, ':fim' => $fim]
        );

        $incidentes = (int) Database::valor(
            "SELECT COUNT(DISTINCT t.id) FROM tasks t
             INNER JOIN task_tag tt ON tt.task_id = t.id
             INNER JOIN tags tg ON tg.id = tt.tag_id
             WHERE t.assignee_id = :uid AND tg.slug IN ('suporte', 'incidente')",
            [':uid' => $utilizadorId]
        );

        return [
            'atribuidas' => $atribuidas,
            'concluidas' => $concluidas,
            'minutos'    => $minutos,
            'incidentes' => $incidentes,
        ];
    }

    /**
     * Distribuição das tarefas do utilizador pelas colunas do quadro.
     *
     * @return list<array<string, mixed>>
     */
    private function porColuna(int $utilizadorId): array
    {
        return Database::todos(
            'SELECT c.id, c.nome, c.cor, COUNT(t.id) AS total
             FROM board_columns c
             LEFT JOIN tasks t ON t.column_id = c.id AND t.assignee_id = :uid
             GROUP BY c.id, c.nome, c.cor, c.ordem
             ORDER BY c.ordem ASC',
            [':uid' => $utilizadorId]
        );
    }

    /**
     * Últimos movimentos registados nas tarefas do utilizador.
     *
     * @return list<array<string, mixed>>
     */
    private function atividadeRecente(int $utilizadorId): array
    {
        return Database::todos(
            'SELECT a.tipo, a.descricao, a.created_at, t.titulo
             FROM task_activity a
             INNER JOIN tasks t ON t.id = a.task_id
             WHERE a.user_id = :uid
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 8',
            [':uid' => $utilizadorId]
        );
    }
}
