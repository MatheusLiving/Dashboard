<?php

declare(strict_types=1);

/**
 * Colunas do quadro Kanban.
 * Apenas "Concluído" tem `is_concluida = 1`: é a coluna que fecha a tarefa
 * e preenche automaticamente a data de conclusão.
 */

use App\Core\Database;

return static function (): void {
    $colunas = [
        ['nome' => 'Backlog',   'ordem' => 1, 'cor' => '#94A3B8', 'is_concluida' => 0],
        ['nome' => 'A Fazer',   'ordem' => 2, 'cor' => '#3B82F6', 'is_concluida' => 0],
        ['nome' => 'Em Curso',  'ordem' => 3, 'cor' => '#F59E0B', 'is_concluida' => 0],
        ['nome' => 'Bloqueado', 'ordem' => 4, 'cor' => '#EF4444', 'is_concluida' => 0],
        ['nome' => 'Concluído', 'ordem' => 5, 'cor' => '#10B981', 'is_concluida' => 1],
    ];

    foreach ($colunas as $coluna) {
        $existe = Database::valor(
            'SELECT id FROM board_columns WHERE nome = :nome LIMIT 1',
            [':nome' => $coluna['nome']]
        );

        if ($existe !== null) {
            Database::atualizar('board_columns', (int) $existe, $coluna);
            echo '    · ' . $coluna['nome'] . ' (atualizada)' . PHP_EOL;
            continue;
        }

        Database::inserir('board_columns', $coluna);
        echo '    · ' . $coluna['nome'] . PHP_EOL;
    }
};
