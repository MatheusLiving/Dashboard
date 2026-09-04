<?php

declare(strict_types=1);

/**
 * Executor de migrações.
 *
 * Percorre /database/migrations por ordem alfabética (os ficheiros são
 * numerados), executa os que ainda não foram aplicados e regista-os na tabela
 * `migrations`. Pode ser corrido as vezes que forem precisas: as migrações já
 * aplicadas são ignoradas.
 *
 * Utilização:
 *   php database/migrate.php            executa as migrações pendentes
 *   php database/migrate.php --estado   apenas mostra o que está pendente
 *   php database/migrate.php --forcar   reexecuta tudo (para desenvolvimento)
 */

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado a partir da linha de comandos.' . PHP_EOL);
}

require dirname(__DIR__) . '/vendor/autoload.php';

Config::carregar(require dirname(__DIR__) . '/config/config.php');

$opcoes  = array_slice($argv, 1);
$soEstado = in_array('--estado', $opcoes, true);
$forcar   = in_array('--forcar', $opcoes, true);

$pasta = Config::raiz('database/migrations');

echo '=== Migrações — ' . Config::get('db.nome') . ' @ ' . Config::get('db.host') . ' ===' . PHP_EOL;

try {
    criarBaseDeDados();
    criarTabelaDeControlo();

    $aplicadas = $forcar ? [] : migracoesAplicadas();
    $ficheiros = listarMigracoes($pasta);

    if ($ficheiros === []) {
        echo 'Não há ficheiros de migração em ' . $pasta . PHP_EOL;
        exit(0);
    }

    $pendentes = array_values(array_filter(
        $ficheiros,
        static fn (string $f): bool => !in_array(basename($f), $aplicadas, true)
    ));

    if ($pendentes === []) {
        echo 'Base de dados atualizada — ' . count($aplicadas) . ' migrações já aplicadas.' . PHP_EOL;
        exit(0);
    }

    if ($soEstado) {
        echo 'Migrações pendentes (' . count($pendentes) . '):' . PHP_EOL;
        foreach ($pendentes as $ficheiro) {
            echo '  · ' . basename($ficheiro) . PHP_EOL;
        }
        exit(0);
    }

    $lote = proximoLote();

    foreach ($pendentes as $ficheiro) {
        aplicar($ficheiro, $lote);
    }

    echo PHP_EOL . 'Concluído: ' . count($pendentes) . ' migrações aplicadas (lote ' . $lote . ').' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * Cria a base de dados caso ainda não exista.
 */
function criarBaseDeDados(): void
{
    $nome = (string) Config::get('db.nome');

    // O nome vem do .env, não do pedido HTTP; mesmo assim, restringimo-lo a
    // caracteres seguros, já que não pode ser passado como parâmetro ligado.
    if (preg_match('/^[A-Za-z0-9_]+$/', $nome) !== 1) {
        throw new RuntimeException('Nome de base de dados inválido: ' . $nome);
    }

    $pdo = Database::ligacaoServidor();
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $nome
    ));

    echo 'Base de dados pronta: ' . $nome . PHP_EOL;
}

/**
 * Cria a tabela que regista as migrações aplicadas.
 */
function criarTabelaDeControlo(): void
{
    Database::ligacao()->exec(
        'CREATE TABLE IF NOT EXISTS `migrations` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ficheiro`   VARCHAR(255) NOT NULL,
            `lote`       INT UNSIGNED NOT NULL DEFAULT 1,
            `aplicada_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_migrations_ficheiro` (`ficheiro`)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci'
    );
}

/**
 * Nomes das migrações já aplicadas.
 *
 * @return list<string>
 */
function migracoesAplicadas(): array
{
    $linhas = Database::todos('SELECT ficheiro FROM migrations');

    return array_map(static fn (array $l): string => (string) $l['ficheiro'], $linhas);
}

/**
 * Ficheiros .sql da pasta de migrações, por ordem.
 *
 * @return list<string>
 */
function listarMigracoes(string $pasta): array
{
    $ficheiros = glob($pasta . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($ficheiros, SORT_NATURAL);

    return array_values($ficheiros);
}

/**
 * Número do próximo lote de migrações.
 */
function proximoLote(): int
{
    $lote = Database::valor('SELECT MAX(lote) FROM migrations');

    return $lote === null ? 1 : ((int) $lote + 1);
}

/**
 * Aplica um ficheiro de migração dentro de uma transação.
 */
function aplicar(string $ficheiro, int $lote): void
{
    $nome = basename($ficheiro);
    $sql  = file_get_contents($ficheiro);

    if ($sql === false) {
        throw new RuntimeException('Não foi possível ler ' . $nome);
    }

    echo '  → ' . $nome . ' ... ';

    $pdo = Database::ligacao();

    // O MySQL confirma implicitamente as instruções DDL, por isso a transação
    // protege sobretudo o registo na tabela de controlo.
    foreach (dividirInstrucoes($sql) as $instrucao) {
        $pdo->exec($instrucao);
    }

    Database::executar(
        'INSERT INTO migrations (ficheiro, lote) VALUES (:ficheiro, :lote)
         ON DUPLICATE KEY UPDATE lote = VALUES(lote), aplicada_em = CURRENT_TIMESTAMP',
        [':ficheiro' => $nome, ':lote' => $lote]
    );

    echo 'ok' . PHP_EOL;
}

/**
 * Divide um ficheiro SQL em instruções, ignorando comentários e respeitando
 * o conteúdo de literais entre plicas ou aspas.
 *
 * @return list<string>
 */
function dividirInstrucoes(string $sql): array
{
    $instrucoes = [];
    $atual      = '';
    $aspas      = null;
    $tamanho    = strlen($sql);

    for ($i = 0; $i < $tamanho; $i++) {
        $caractere = $sql[$i];

        if ($aspas !== null) {
            $atual .= $caractere;

            // Escape dentro de literal: o caractere seguinte é sempre literal.
            if ($caractere === '\\' && $i + 1 < $tamanho) {
                $atual .= $sql[++$i];
                continue;
            }

            if ($caractere === $aspas) {
                $aspas = null;
            }

            continue;
        }

        if ($caractere === "'" || $caractere === '"' || $caractere === '`') {
            $aspas  = $caractere;
            $atual .= $caractere;
            continue;
        }

        // Comentário de linha: -- ou #
        if (($caractere === '-' && substr($sql, $i, 2) === '--') || $caractere === '#') {
            $fim = strpos($sql, "\n", $i);
            $i   = $fim === false ? $tamanho : $fim;
            continue;
        }

        // Comentário de bloco: /* ... */
        if ($caractere === '/' && substr($sql, $i, 2) === '/*') {
            $fim = strpos($sql, '*/', $i + 2);
            $i   = $fim === false ? $tamanho : $fim + 1;
            continue;
        }

        if ($caractere === ';') {
            $instrucao = trim($atual);
            if ($instrucao !== '') {
                $instrucoes[] = $instrucao;
            }
            $atual = '';
            continue;
        }

        $atual .= $caractere;
    }

    $instrucao = trim($atual);
    if ($instrucao !== '') {
        $instrucoes[] = $instrucao;
    }

    return $instrucoes;
}
