<?php

declare(strict_types=1);

/**
 * Etiquetas iniciais.
 *
 * "Suporte" e "Incidente" têm significado especial: as tarefas assim
 * marcadas alimentam a secção 3 do relatório, "Incidentes e Pedidos de Suporte".
 */

use App\Core\Database;

return static function (): void {
    $tags = [
        ['nome' => 'IT',             'slug' => 'it',             'cor_hex' => '#1F3864'],
        ['nome' => 'Suporte',        'slug' => 'suporte',        'cor_hex' => '#0EA5E9'],
        ['nome' => 'Infraestrutura', 'slug' => 'infraestrutura', 'cor_hex' => '#7C3AED'],
        ['nome' => 'Redes',          'slug' => 'redes',          'cor_hex' => '#0D9488'],
        ['nome' => 'Segurança',      'slug' => 'seguranca',      'cor_hex' => '#DC2626'],
        ['nome' => 'Manutenção',     'slug' => 'manutencao',     'cor_hex' => '#D97706'],
        ['nome' => 'Incidente',      'slug' => 'incidente',      'cor_hex' => '#BE123C'],
        // Estas duas abrem um Relatório de Alteração de Software quando são
        // postas numa tarefa — ver a configuração «tags_desenvolvimento».
        ['nome' => 'Desenvolvimento', 'slug' => 'desenvolvimento', 'cor_hex' => '#2563EB'],
        ['nome' => 'Melhoria',        'slug' => 'melhoria',        'cor_hex' => '#65A30D'],
    ];

    foreach ($tags as $tag) {
        Database::executar(
            'INSERT INTO tags (nome, slug, cor_hex, ativa)
             VALUES (:nome, :slug, :cor_hex, 1)
             ON DUPLICATE KEY UPDATE nome = VALUES(nome), cor_hex = VALUES(cor_hex), ativa = 1',
            [
                ':nome'    => $tag['nome'],
                ':slug'    => $tag['slug'],
                ':cor_hex' => $tag['cor_hex'],
            ]
        );

        echo '    · ' . $tag['nome'] . PHP_EOL;
    }
};
