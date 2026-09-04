<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Arranque e utilitários da sessão.
 *
 * Instala o gestor que guarda as sessões na tabela `sessions` e define os
 * parâmetros do cookie (HttpOnly, SameSite=Lax e Secure em produção).
 */
final class Session
{
    private static bool $iniciada = false;

    /**
     * Arranca a sessão. Chamar uma vez, no front-controller.
     */
    public static function iniciar(): void
    {
        if (self::$iniciada || session_status() === PHP_SESSION_ACTIVE) {
            self::$iniciada = true;

            return;
        }

        $duracao = (int) Config::get('sessao.duracao', 7200);

        session_set_save_handler(new SessionHandler($duracao), true);

        session_name((string) Config::get('sessao.nome', 'relatorio_ti_sessao'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) Config::get('sessao.segura', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) $duracao);

        session_start();
        self::$iniciada = true;
    }

    /**
     * Lê um valor da sessão.
     */
    public static function get(string $chave, mixed $omissao = null): mixed
    {
        return $_SESSION[$chave] ?? $omissao;
    }

    /**
     * Escreve um valor na sessão.
     */
    public static function set(string $chave, mixed $valor): void
    {
        $_SESSION[$chave] = $valor;
    }

    /**
     * Indica se uma chave existe na sessão.
     */
    public static function tem(string $chave): bool
    {
        return isset($_SESSION[$chave]);
    }

    /**
     * Remove uma chave da sessão.
     */
    public static function esquecer(string $chave): void
    {
        unset($_SESSION[$chave]);
    }

    /**
     * Lê um valor e remove-o de imediato.
     */
    public static function retirar(string $chave, mixed $omissao = null): mixed
    {
        $valor = self::get($chave, $omissao);
        self::esquecer($chave);

        return $valor;
    }

    /**
     * Gera um novo identificador de sessão, apagando o registo antigo.
     * Usado no início de sessão para impedir fixação de sessão.
     */
    public static function regenerar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Destrói a sessão por completo, incluindo o cookie do cliente.
     */
    public static function destruir(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $parametros['path'],
                    'domain'   => $parametros['domain'],
                    'secure'   => $parametros['secure'],
                    'httponly' => $parametros['httponly'],
                    'samesite' => $parametros['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
        self::$iniciada = false;
    }
}
