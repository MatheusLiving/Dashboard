<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Proteção contra pedidos forjados entre sítios (CSRF).
 *
 * O token vive na sessão e tem de acompanhar todos os pedidos POST, PUT,
 * PATCH e DELETE — quer em campo escondido de formulário, quer no cabeçalho
 * X-CSRF-Token dos pedidos feitos por fetch.
 */
final class Csrf
{
    private const CHAVE = '_token_csrf';

    /**
     * Devolve o token da sessão, gerando-o na primeira utilização.
     */
    public static function token(): string
    {
        $token = Session::get(self::CHAVE);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::CHAVE, $token);
        }

        return $token;
    }

    /**
     * Campo escondido pronto a colar dentro de um <form>.
     */
    public static function campo(): string
    {
        return sprintf(
            '<input type="hidden" name="_token" value="%s">',
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Compara um token recebido com o da sessão, em tempo constante.
     */
    public static function valido(?string $token): bool
    {
        $esperado = Session::get(self::CHAVE);

        if (!is_string($esperado) || $esperado === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($esperado, $token);
    }

    /**
     * Extrai o token do pedido: campo `_token` ou cabeçalho X-CSRF-Token.
     */
    public static function doPedido(): ?string
    {
        if (isset($_POST['_token']) && is_string($_POST['_token'])) {
            return $_POST['_token'];
        }

        $cabecalho = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return is_string($cabecalho) ? $cabecalho : null;
    }

    /**
     * Gera um token novo. Chamado depois do início de sessão, para que o
     * token emitido antes da autenticação deixe de ser válido.
     */
    public static function renovar(): void
    {
        Session::esquecer(self::CHAVE);
        self::token();
    }
}
