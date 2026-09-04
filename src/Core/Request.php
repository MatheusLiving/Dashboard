<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Leitura normalizada do pedido HTTP em curso.
 *
 * Todos os acessos a $_GET, $_POST e $_SERVER passam por aqui, para que os
 * controladores nunca lidem com superglobais em bruto.
 */
final class Request
{
    /** @var array<string, mixed>|null Corpo JSON já descodificado. */
    private static ?array $json = null;

    /**
     * Método HTTP do pedido, em maiúsculas.
     * Aceita o campo `_method` para simular PUT, PATCH e DELETE em formulários.
     */
    public static function metodo(): string
    {
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($metodo === 'POST' && isset($_POST['_method']) && is_string($_POST['_method'])) {
            $simulado = strtoupper($_POST['_method']);
            if (in_array($simulado, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $simulado;
            }
        }

        return $metodo;
    }

    /**
     * Caminho do pedido, sem query string e sem barra final.
     */
    public static function caminho(): string
    {
        $uri     = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $caminho = parse_url($uri, PHP_URL_PATH);
        $caminho = is_string($caminho) ? $caminho : '/';
        $caminho = rawurldecode($caminho);
        $caminho = '/' . trim($caminho, '/');

        return $caminho;
    }

    /**
     * Valor de $_GET, sempre devolvido como string ou o valor por omissão.
     */
    public static function query(string $chave, ?string $omissao = null): ?string
    {
        $valor = $_GET[$chave] ?? null;

        return is_string($valor) ? trim($valor) : $omissao;
    }

    /**
     * Valor de $_POST, sempre devolvido como string ou o valor por omissão.
     */
    public static function post(string $chave, ?string $omissao = null): ?string
    {
        $valor = $_POST[$chave] ?? null;

        return is_string($valor) ? trim($valor) : $omissao;
    }

    /**
     * Valor inteiro de $_POST, ou null se ausente ou não numérico.
     */
    public static function postInt(string $chave): ?int
    {
        $valor = self::post($chave);

        return ($valor === null || $valor === '' || !is_numeric($valor)) ? null : (int) $valor;
    }

    /**
     * Valor inteiro de $_GET, ou null se ausente ou não numérico.
     */
    public static function queryInt(string $chave): ?int
    {
        $valor = self::query($chave);

        return ($valor === null || $valor === '' || !is_numeric($valor)) ? null : (int) $valor;
    }

    /**
     * Array vindo de $_POST (por exemplo, linhas repetidas de um formulário).
     *
     * @return array<int|string, mixed>
     */
    public static function postArray(string $chave): array
    {
        $valor = $_POST[$chave] ?? [];

        return is_array($valor) ? $valor : [];
    }

    /**
     * Todos os campos de $_POST.
     *
     * @return array<string, mixed>
     */
    public static function todosPost(): array
    {
        return $_POST;
    }

    /**
     * Corpo do pedido descodificado como JSON.
     *
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }

        $corpo = file_get_contents('php://input');

        if ($corpo === false || $corpo === '') {
            return self::$json = [];
        }

        $dados = json_decode($corpo, true);

        return self::$json = is_array($dados) ? $dados : [];
    }

    /**
     * Indica se o pedido espera resposta em JSON (fetch, XHR ou Accept).
     */
    public static function esperaJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr    = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest';
    }

    /**
     * Endereço IP do cliente.
     */
    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /**
     * URL de onde veio o pedido, para regressos após submissão.
     */
    public static function referer(): ?string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        return is_string($referer) && $referer !== '' ? $referer : null;
    }
}
