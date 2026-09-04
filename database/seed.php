<?php

declare(strict_types=1);

/**
 * Executor de seeds.
 *
 * Corre por ordem os ficheiros de /database/seeds, cada um devolvendo uma
 * função anónima. Os seeds são idempotentes: podem ser executados várias
 * vezes sem duplicar registos.
 *
 * Utilização:
 *   php database/seed.php               executa todos os seeds
 *   php database/seed.php 03_tags       executa apenas um
 */

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado a partir da linha de comandos.' . PHP_EOL);
}

require dirname(__DIR__) . '/vendor/autoload.php';

Config::carregar(require dirname(__DIR__) . '/config/config.php');

$filtro = $argv[1] ?? null;
$pasta  = Config::raiz('database/seeds');

echo '=== Seeds — ' . Config::get('db.nome') . ' ===' . PHP_EOL;

try {
    // Sem migrações aplicadas não há onde inserir dados.
    $tabelas = Database::valor(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = :esquema AND table_name = :tabela',
        [':esquema' => Config::get('db.nome'), ':tabela' => 'users']
    );

    if ((int) $tabelas === 0) {
        throw new RuntimeException('Execute primeiro as migrações: php database/migrate.php');
    }

    $ficheiros = glob($pasta . DIRECTORY_SEPARATOR . '*.php') ?: [];
    sort($ficheiros, SORT_NATURAL);

    $executados = 0;

    foreach ($ficheiros as $ficheiro) {
        $nome = basename($ficheiro, '.php');

        if ($filtro !== null && !str_contains($nome, $filtro)) {
            continue;
        }

        echo '  → ' . $nome . PHP_EOL;

        $semear = require $ficheiro;

        if (!is_callable($semear)) {
            throw new RuntimeException($nome . ' não devolveu uma função executável.');
        }

        Database::iniciarTransacao();

        try {
            $semear();
            Database::confirmar();
        } catch (Throwable $e) {
            Database::anular();

            throw $e;
        }

        $executados++;
    }

    if ($executados === 0) {
        echo 'Nenhum seed correspondeu ao filtro indicado.' . PHP_EOL;
        exit(1);
    }

    echo PHP_EOL . 'Concluído: ' . $executados . ' seeds executados.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
