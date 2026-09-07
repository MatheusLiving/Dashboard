<?php

declare(strict_types=1);

/**
 * Constrói o template .docx com marcadores a partir do documento original.
 *
 * Utilização:
 *   php bin/preparar-template.php            constrói o template
 *   php bin/preparar-template.php --verificar apenas confere os marcadores
 *
 * O documento original (`Relatorio_Semanal_TI.docx`, na raiz do projeto) nunca
 * é alterado: o template é uma cópia com os marcadores colocados.
 */

use App\Core\Config;
use App\Services\TemplateBuilder;
use PhpOffice\PhpWord\TemplateProcessor;

if (PHP_SAPI !== 'cli') {
    exit('Este script só pode ser executado a partir da linha de comandos.' . PHP_EOL);
}

require dirname(__DIR__) . '/vendor/autoload.php';

Config::carregar(require dirname(__DIR__) . '/config/config.php');

$origem  = Config::raiz('Relatorio_Semanal_TI.docx');
$destino = (string) Config::get('relatorio.template');

$apenasVerificar = in_array('--verificar', array_slice($argv, 1), true);

echo '=== Template do Relatório Semanal ===' . PHP_EOL;

try {
    if (!$apenasVerificar) {
        echo 'Original: ' . $origem . PHP_EOL;

        $construtor = new TemplateBuilder($origem, $destino);

        foreach ($construtor->construir() as $linha) {
            echo '  · ' . $linha . PHP_EOL;
        }

        echo PHP_EOL;
    }

    verificar($destino);
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * Confirma que o template contém todos os marcadores esperados.
 *
 * Esta verificação existe porque um marcador partido em vários runs pelo Word
 * passa despercebido: o PhpWord não o encontra e ele acaba impresso no
 * documento final. Mais vale falhar aqui do que num relatório entregue.
 */
function verificar(string $template): void
{
    if (!is_file($template)) {
        throw new RuntimeException('Template não encontrado: ' . $template);
    }

    $processador = new TemplateProcessor($template);
    $encontrados = $processador->getVariables();

    echo 'Marcadores encontrados no template (' . count($encontrados) . '):' . PHP_EOL;
    echo '  ' . implode(', ', $encontrados) . PHP_EOL . PHP_EOL;

    $esperados = TemplateBuilder::marcadoresEsperados();
    $faltam    = array_values(array_diff($esperados, $encontrados));
    $aMais     = array_values(array_diff($encontrados, $esperados));

    if ($aMais !== []) {
        echo 'Aviso — marcadores não previstos: ' . implode(', ', $aMais) . PHP_EOL;
    }

    if ($faltam !== []) {
        throw new RuntimeException(
            'Faltam marcadores no template: ' . implode(', ', $faltam)
        );
    }

    echo 'Todos os ' . count($esperados) . ' marcadores esperados estão presentes.' . PHP_EOL;
}
