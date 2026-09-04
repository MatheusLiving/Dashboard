<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contentor estático da configuração da aplicação.
 *
 * Guarda o array devolvido por /config/config.php e permite consultá-lo
 * através de chaves com notação por pontos: Config::get('db.host').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $valores = [];

    /**
     * Carrega o array de configuração. Chamado uma única vez no arranque.
     *
     * @param array<string, mixed> $valores
     */
    public static function carregar(array $valores): void
    {
        self::$valores = $valores;
    }

    /**
     * Devolve um valor de configuração usando notação por pontos.
     */
    public static function get(string $chave, mixed $omissao = null): mixed
    {
        $atual = self::$valores;

        foreach (explode('.', $chave) as $segmento) {
            if (!is_array($atual) || !array_key_exists($segmento, $atual)) {
                return $omissao;
            }
            $atual = $atual[$segmento];
        }

        return $atual;
    }

    /**
     * Caminho absoluto para a raiz do projeto, opcionalmente com sufixo.
     */
    public static function raiz(string $sufixo = ''): string
    {
        $raiz = (string) self::get('app.raiz', dirname(__DIR__, 2));

        if ($sufixo === '') {
            return $raiz;
        }

        return $raiz . DIRECTORY_SEPARATOR
            . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $sufixo), DIRECTORY_SEPARATOR);
    }

    /**
     * Indica se a aplicação está em modo de depuração.
     */
    public static function debug(): bool
    {
        return (bool) self::get('app.debug', false);
    }
}
