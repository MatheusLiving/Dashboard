<?php

declare(strict_types=1);

/**
 * Utilizadores iniciais: um administrador e três membros da equipa.
 * As palavras-passe estão documentadas no README.
 */

use App\Core\Auth;
use App\Core\Database;

return static function (): void {
    $utilizadores = [
        [
            'nome'         => 'Administrador de Sistemas',
            'email'        => 'admin@ti.local',
            'senha'        => 'admin1234',
            'funcao_cargo' => 'Coordenador do Departamento de TI',
            'papel'        => 'admin',
        ],
        [
            'nome'         => 'Rui Marques',
            'email'        => 'rui.marques@ti.local',
            'senha'        => 'membro1234',
            'funcao_cargo' => 'Técnico de Infraestrutura',
            'papel'        => 'membro',
        ],
        [
            'nome'         => 'Sofia Almeida',
            'email'        => 'sofia.almeida@ti.local',
            'senha'        => 'membro1234',
            'funcao_cargo' => 'Técnica de Suporte',
            'papel'        => 'membro',
        ],
        [
            'nome'         => 'Nuno Correia',
            'email'        => 'nuno.correia@ti.local',
            'senha'        => 'membro1234',
            'funcao_cargo' => 'Administrador de Redes',
            'papel'        => 'membro',
        ],
    ];

    foreach ($utilizadores as $utilizador) {
        Database::executar(
            'INSERT INTO users (nome, email, senha_hash, funcao_cargo, papel, ativo)
             VALUES (:nome, :email, :senha_hash, :funcao_cargo, :papel, 1)
             ON DUPLICATE KEY UPDATE
                nome         = VALUES(nome),
                funcao_cargo = VALUES(funcao_cargo),
                papel        = VALUES(papel),
                ativo        = 1',
            [
                ':nome'         => $utilizador['nome'],
                ':email'        => $utilizador['email'],
                ':senha_hash'   => Auth::hash($utilizador['senha']),
                ':funcao_cargo' => $utilizador['funcao_cargo'],
                ':papel'        => $utilizador['papel'],
            ]
        );

        echo '    · ' . $utilizador['email'] . ' (' . $utilizador['papel'] . ')' . PHP_EOL;
    }
};
