<?php

declare(strict_types=1);

/**
 * Constrói os templates .docx com marcadores a partir dos documentos originais.
 *
 * Utilização:
 *   php bin/preparar-template.php             constrói os dois templates
 *   php bin/preparar-template.php --verificar apenas confere os marcadores
 *   php bin/preparar-template.php --semanal   só o relatório semanal
 *   php bin/preparar-template.php --alteracao só o relatório de alteração
 *
 * Os documentos originais nunca são alterados: cada template é uma cópia com
 * os marcadores colocados.
 */

use App\Core\Config;
use App\Services\ChangeTemplateBuilder;
use App\Services\TemplateBuilder;
use PhpOffice\PhpWord\TemplateProcessor;

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado a partir da linha de comandos.' . PHP_EOL);
}

require dirname(__DIR__) . '/vendor/autoload.php';

Config::carregar(require dirname(__DIR__) . '/config/config.php');

$opcoes          = array_slice($argv, 1);
$apenasVerificar = in_array('--verificar', $opcoes, true);
$soSemanal       = in_array('--semanal', $opcoes, true);
$soAlteracao     = in_array('--alteracao', $opcoes, true);

// Sem escolha explícita, tratam-se os dois.
$fazerSemanal   = !$soAlteracao;
$fazerAlteracao = !$soSemanal;

try {
    if ($fazerSemanal) {
        tratar(
            'Relatório Semanal',
            Config::raiz('Relatorio_Semanal_TI.docx'),
            (string) Config::get('relatorio.template'),
            static fn (string $origem, string $destino): array => (new TemplateBuilder($origem, $destino))->construir(),
            TemplateBuilder::marcadoresEsperados(),
            $apenasVerificar
        );
    }

    if ($fazerAlteracao) {
        tratar(
            'Relatório de Alteração de Software',
            (string) Config::get('alteracao.origem'),
            (string) Config::get('alteracao.template'),
            static fn (string $origem, string $destino): array => (new ChangeTemplateBuilder($origem, $destino))->construir(),
            ChangeTemplateBuilder::marcadoresEsperados(),
            $apenasVerificar
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * Constrói (ou apenas verifica) um template.
 *
 * @param callable(string, string): list<string> $construtor
 * @param list<string>                           $esperados
 */
function tratar(
    string $nome,
    string $origem,
    string $destino,
    callable $construtor,
    array $esperados,
    bool $apenasVerificar
): void {
    echo PHP_EOL . '=== ' . $nome . ' ===' . PHP_EOL;

    if (!$apenasVerificar) {
        echo 'Original: ' . $origem . PHP_EOL;

        foreach ($construtor($origem, $destino) as $linha) {
            echo '  · ' . $linha . PHP_EOL;
        }

        echo PHP_EOL;
    }

    verificar($destino, $esperados);
}

/**
 * Confirma que o template contém todos os marcadores esperados.
 *
 * Esta verificação existe porque um marcador partido em vários runs pelo Word
 * passa despercebido: o PhpWord não o encontra e ele acaba impresso no
 * documento final. Mais vale falhar aqui do que num relatório entregue.
 *
 * @param list<string> $esperados
 */
function verificar(string $template, array $esperados): void
{
    if (!is_file($template)) {
        throw new RuntimeException('Template não encontrado: ' . $template);
    }

    $processador = new TemplateProcessor($template);
    $encontrados = $processador->getVariables();

    echo 'Marcadores encontrados no template (' . count($encontrados) . '):' . PHP_EOL;
    echo '  ' . implode(', ', $encontrados) . PHP_EOL . PHP_EOL;

    $faltam = array_values(array_diff($esperados, $encontrados));
    $aMais  = array_values(array_diff($encontrados, $esperados));

    if ($aMais !== []) {
        echo 'Aviso — marcadores não previstos: ' . implode(', ', $aMais) . PHP_EOL;
    }

    if ($faltam !== []) {
        throw new RuntimeException('Faltam marcadores no template: ' . implode(', ', $faltam));
    }

    echo 'Todos os ' . count($esperados) . ' marcadores esperados estão presentes.' . PHP_EOL;
}
