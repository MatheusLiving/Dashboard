<?php

declare(strict_types=1);

/**
 * Departamentos iniciais do quadro de assistências.
 *
 * São os departamentos habituais de uma empresa; a equipa ajusta a lista na
 * própria página, que é onde faz sentido fazê-lo. O seed não apaga nada: um
 * departamento acrescentado ou renomeado pela equipa fica como está.
 */

use App\Core\Database;

return static function (): void {
    $departamentos = [
        ['nome' => 'Financeiro',         'slug' => 'financeiro',         'cor_hex' => '#0D9488'],
        ['nome' => 'Recursos Humanos',   'slug' => 'recursos-humanos',   'cor_hex' => '#7C3AED'],
        ['nome' => 'Comercial',          'slug' => 'comercial',          'cor_hex' => '#2563EB'],
        ['nome' => 'Logística',          'slug' => 'logistica',          'cor_hex' => '#D97706'],
        ['nome' => 'Produção',           'slug' => 'producao',           'cor_hex' => '#DC2626'],
        ['nome' => 'Qualidade',          'slug' => 'qualidade',          'cor_hex' => '#65A30D'],
        ['nome' => 'Marketing',          'slug' => 'marketing',          'cor_hex' => '#DB2777'],
        ['nome' => 'Direção',            'slug' => 'direcao',            'cor_hex' => '#1F3864'],
    ];

    foreach ($departamentos as $departamento) {
        // Sem ON DUPLICATE KEY UPDATE do nome: se a equipa renomeou o
        // departamento, quem manda é a equipa, não o seed.
        Database::executar(
            'INSERT INTO departments (nome, slug, cor_hex, ativo)
             VALUES (:nome, :slug, :cor_hex, 1)
             ON DUPLICATE KEY UPDATE id = id',
            [
                ':nome'    => $departamento['nome'],
                ':slug'    => $departamento['slug'],
                ':cor_hex' => $departamento['cor_hex'],
            ]
        );

        echo '    · ' . $departamento['nome'] . PHP_EOL;
    }
};
