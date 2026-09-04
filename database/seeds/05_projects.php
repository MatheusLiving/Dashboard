<?php

declare(strict_types=1);

/**
 * Projetos de exemplo, atribuídos aos membros criados no seed 01.
 */

use App\Core\Database;
use App\Core\Semana;

return static function (): void {
    $hoje = Semana::agora();

    // Mapa email => id, para ligar cada projeto ao seu responsável.
    $utilizadores = [];
    foreach (Database::todos('SELECT id, email FROM users') as $linha) {
        $utilizadores[(string) $linha['email']] = (int) $linha['id'];
    }

    $projetos = [
        [
            'nome'           => 'Migração do servidor de ficheiros',
            'descricao'      => 'Transferência do servidor de ficheiros para nova infraestrutura, '
                . 'com reorganização de permissões e política de cópias de segurança.',
            'responsavel'    => 'rui.marques@ti.local',
            'status'         => 'em_curso',
            'progresso_pct'  => 65,
            'prioridade'     => 'alta',
            'data_inicio'    => $hoje->modify('-45 days')->format('Y-m-d'),
            'prazo'          => $hoje->modify('+30 days')->format('Y-m-d'),
        ],
        [
            'nome'           => 'Renovação da rede sem fios',
            'descricao'      => 'Substituição dos pontos de acesso e segmentação da rede de visitantes.',
            'responsavel'    => 'nuno.correia@ti.local',
            'status'         => 'em_curso',
            'progresso_pct'  => 40,
            'prioridade'     => 'media',
            'data_inicio'    => $hoje->modify('-20 days')->format('Y-m-d'),
            'prazo'          => $hoje->modify('+45 days')->format('Y-m-d'),
        ],
        [
            'nome'           => 'Plano de segurança e cópias de segurança',
            'descricao'      => 'Revisão da política de palavras-passe, autenticação em dois passos '
                . 'e testes de restauro das cópias de segurança.',
            'responsavel'    => 'sofia.almeida@ti.local',
            'status'         => 'planeado',
            'progresso_pct'  => 15,
            'prioridade'     => 'critica',
            'data_inicio'    => $hoje->modify('-7 days')->format('Y-m-d'),
            'prazo'          => $hoje->modify('+60 days')->format('Y-m-d'),
        ],
    ];

    foreach ($projetos as $projeto) {
        $existe = Database::valor(
            'SELECT id FROM projects WHERE nome = :nome LIMIT 1',
            [':nome' => $projeto['nome']]
        );

        if ($existe !== null) {
            echo '    · ' . $projeto['nome'] . ' (já existia)' . PHP_EOL;
            continue;
        }

        Database::inserir('projects', [
            'nome'           => $projeto['nome'],
            'descricao'      => $projeto['descricao'],
            'responsavel_id' => $utilizadores[$projeto['responsavel']] ?? null,
            'status'         => $projeto['status'],
            'progresso_pct'  => $projeto['progresso_pct'],
            'prioridade'     => $projeto['prioridade'],
            'data_inicio'    => $projeto['data_inicio'],
            'prazo'          => $projeto['prazo'],
            'arquivado'      => 0,
        ]);

        echo '    · ' . $projeto['nome'] . PHP_EOL;
    }
};
