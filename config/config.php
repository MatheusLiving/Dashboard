<?php
/**
 * Carregamento da configuração da aplicação.
 *
 * Lê o ficheiro .env (via vlucas/phpdotenv), define o fuso horário e devolve
 * um array simples com toda a configuração. É o único ponto do sistema que
 * conhece os nomes das variáveis de ambiente.
 */

declare(strict_types=1);

$raiz = dirname(__DIR__);

// O .env é obrigatório: sem ele não há credenciais de base de dados.
$dotenv = Dotenv\Dotenv::createImmutable($raiz);
$dotenv->safeLoad();
$dotenv->required(['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'])->notEmpty();

/**
 * Lê uma variável de ambiente com valor por omissão e conversão de booleanos.
 */
$env = static function (string $chave, mixed $omissao = null): mixed {
    $valor = $_ENV[$chave] ?? $_SERVER[$chave] ?? getenv($chave);

    if ($valor === false || $valor === null || $valor === '') {
        return $omissao;
    }

    return match (strtolower((string) $valor)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $valor,
    };
};

$fuso = (string) $env('APP_TIMEZONE', 'Europe/Lisbon');
date_default_timezone_set($fuso);

return [
    'app' => [
        'env'      => (string) $env('APP_ENV', 'production'),
        'debug'    => (bool) $env('APP_DEBUG', false),
        'nome'     => (string) $env('APP_NAME', 'Departamento de TI'),
        'url'      => rtrim((string) $env('APP_URL', ''), '/'),
        'fuso'     => $fuso,
        'raiz'     => $raiz,
    ],
    'db' => [
        'host'     => (string) $env('DB_HOST', '127.0.0.1'),
        'porta'    => (int) $env('DB_PORT', 3306),
        'nome'     => (string) $env('DB_DATABASE', 'relatorio_ti'),
        'user'     => (string) $env('DB_USERNAME', 'root'),
        'password' => (string) $env('DB_PASSWORD', ''),
        'charset'  => (string) $env('DB_CHARSET', 'utf8mb4'),
    ],
    'sessao' => [
        'nome'     => (string) $env('SESSION_NAME', 'relatorio_ti_sessao'),
        // Convertido para segundos, que é o que o PHP e a tabela sessions usam.
        'duracao'  => ((int) $env('SESSION_LIFETIME', 120)) * 60,
        'segura'   => (bool) $env('SESSION_SECURE', false),
    ],
    'relatorio' => [
        'template' => $raiz . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string) $env('REPORT_TEMPLATE', 'storage/templates/Relatorio_Semanal_TI_template.docx')),
        'saida'    => $raiz . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string) $env('REPORT_OUTPUT', 'storage/reports')),
    ],
];
