<?php

declare(strict_types=1);

/**
 * Configurações de negócio editáveis pelo administrador na interface.
 */

use App\Core\Database;

return static function (): void {
    $definicoes = [
        [
            'chave'     => 'departamento_nome',
            'valor'     => 'Departamento de TI',
            'descricao' => 'Nome do departamento apresentado no cabeçalho do relatório.',
        ],
        [
            'chave'     => 'semana_dia_fecho',
            'valor'     => '5',
            'descricao' => 'Dia da semana em que o relatório fecha (1 = segunda-feira, 7 = domingo).',
        ],
        [
            'chave'     => 'prazo_entrega_dias',
            'valor'     => '2',
            'descricao' => 'Dias após o fim da semana para entrega do relatório.',
        ],
        [
            'chave'     => 'template_caminho',
            'valor'     => 'storage/templates/Relatorio_Semanal_TI_template.docx',
            'descricao' => 'Caminho do template .docx, relativo à raiz do projeto.',
        ],
        [
            'chave'     => 'tags_desenvolvimento',
            'valor'     => 'desenvolvimento,suporte,incidente,melhoria',
            'descricao' => 'Slugs das etiquetas que, numa tarefa, abrem um Relatório de Alteração de Software (separados por vírgula).',
        ],
        [
            'chave'     => 'alteracao_abertura_automatica',
            'valor'     => '1',
            'descricao' => 'Abrir automaticamente o Relatório de Alteração de Software ao criar projetos e tarefas de desenvolvimento (1 = sim, 0 = não).',
        ],
        [
            'chave'     => 'alteracao_template_caminho',
            'valor'     => 'storage/templates/Relatorio_Alteracao_Software_template.docx',
            'descricao' => 'Caminho do template .docx do relatório de alteração, relativo à raiz do projeto.',
        ],
        [
            'chave'     => 'tags_incidente',
            'valor'     => 'suporte,incidente',
            'descricao' => 'Slugs das etiquetas que classificam uma tarefa como incidente (separados por vírgula).',
        ],
    ];

    foreach ($definicoes as $definicao) {
        Database::executar(
            'INSERT INTO settings (chave, valor, descricao)
             VALUES (:chave, :valor, :descricao)
             ON DUPLICATE KEY UPDATE descricao = VALUES(descricao)',
            [
                ':chave'     => $definicao['chave'],
                ':valor'     => $definicao['valor'],
                ':descricao' => $definicao['descricao'],
            ]
        );

        echo '    · ' . $definicao['chave'] . PHP_EOL;
    }
};
