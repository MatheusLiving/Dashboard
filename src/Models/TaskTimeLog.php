<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `task_time_logs`.
 *
 * Cada registo é tempo que um colaborador dedicou a uma tarefa num dia.
 * A soma destes registos dentro da semana preenche a coluna "Tempo dedicado"
 * da secção 2 do relatório.
 */
final class TaskTimeLog
{
    /**
     * Registos de tempo de uma tarefa, do mais recente para o mais antigo.
     *
     * @return list<array<string, mixed>>
     */
    public static function daTarefa(int $taskId): array
    {
        return Database::todos(
            'SELECT l.id, l.data, l.minutos, l.nota, l.user_id, l.created_at, u.nome AS utilizador
             FROM task_time_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.task_id = :task_id
             ORDER BY l.data DESC, l.id DESC',
            [':task_id' => $taskId]
        );
    }

    /**
     * Um registo pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT id, task_id, user_id, data, minutos, nota FROM task_time_logs WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Cria um registo de tempo e devolve o identificador gerado.
     */
    public static function criar(int $taskId, int $userId, string $data, int $minutos, ?string $nota): int
    {
        return Database::inserir('task_time_logs', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'data'    => $data,
            'minutos' => $minutos,
            'nota'    => $nota,
        ]);
    }

    /**
     * Elimina um registo de tempo.
     */
    public static function eliminar(int $id): int
    {
        return Database::executar('DELETE FROM task_time_logs WHERE id = :id', [':id' => $id]);
    }

    /**
     * Total de minutos registados numa tarefa.
     */
    public static function totalDaTarefa(int $taskId): int
    {
        return (int) Database::valor(
            'SELECT COALESCE(SUM(minutos), 0) FROM task_time_logs WHERE task_id = :task_id',
            [':task_id' => $taskId]
        );
    }

    /**
     * Total de minutos registados por um utilizador num intervalo de datas.
     */
    public static function totalDoUtilizador(int $userId, string $inicio, string $fim): int
    {
        return (int) Database::valor(
            'SELECT COALESCE(SUM(minutos), 0) FROM task_time_logs
             WHERE user_id = :uid AND data BETWEEN :inicio AND :fim',
            [':uid' => $userId, ':inicio' => $inicio, ':fim' => $fim]
        );
    }

    /**
     * Converte um texto de duração em minutos.
     *
     * Aceita as formas usadas na prática pela equipa: "90", "1h30", "1h 30m",
     * "2h", "45m", "1:30". Devolve null quando não consegue interpretar.
     */
    public static function interpretarDuracao(string $texto): ?int
    {
        $texto = mb_strtolower(trim($texto));

        if ($texto === '') {
            return null;
        }

        // Apenas dígitos: são minutos.
        if (preg_match('/^\d+$/', $texto) === 1) {
            return (int) $texto;
        }

        // Formato "1:30".
        if (preg_match('/^(\d+):([0-5]?\d)$/', $texto, $partes) === 1) {
            return ((int) $partes[1] * 60) + (int) $partes[2];
        }

        // Formatos com "h" e/ou "m": "1h30", "1h 30m", "2h", "45m".
        if (preg_match('/^(?:(\d+)\s*h)?\s*(?:(\d+)\s*m?)?$/', $texto, $partes) === 1) {
            $horas   = isset($partes[1]) && $partes[1] !== '' ? (int) $partes[1] : 0;
            $minutos = isset($partes[2]) && $partes[2] !== '' ? (int) $partes[2] : 0;

            if ($horas === 0 && $minutos === 0) {
                return null;
            }

            return ($horas * 60) + $minutos;
        }

        return null;
    }
}
