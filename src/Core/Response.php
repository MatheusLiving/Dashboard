<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Emissão de respostas HTTP: redirecionamentos, JSON e cabeçalhos de segurança.
 */
final class Response
{
    /**
     * Envia os cabeçalhos de segurança aplicados a todas as respostas.
     */
    public static function cabecalhosSeguranca(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Cross-Origin-Opener-Policy: same-origin');

        // A política de conteúdos permite os CDN do Tailwind e do SortableJS,
        // usados em desenvolvimento. Num build local de CSS, apertar isto.
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; "
            . "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; "
            . "font-src 'self' https://fonts.gstatic.com data:; "
            . "img-src 'self' data:; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'"
        );
    }

    /**
     * Redireciona para um caminho interno e termina o pedido.
     */
    public static function redirecionar(string $caminho, int $codigo = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $caminho, true, $codigo);
        }

        exit;
    }

    /**
     * Devolve uma resposta JSON e termina o pedido.
     *
     * @param array<string, mixed>|list<mixed> $dados
     */
    public static function json(array $dados, int $codigo = 200): never
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Resposta JSON de erro, com mensagem e código HTTP.
     *
     * @param array<string, string> $erros Erros por campo, quando existam.
     */
    public static function erroJson(string $mensagem, int $codigo = 400, array $erros = []): never
    {
        $carga = ['ok' => false, 'mensagem' => $mensagem];

        if ($erros !== []) {
            $carga['erros'] = $erros;
        }

        self::json($carga, $codigo);
    }

    /**
     * Envia um ficheiro para descarregamento e termina o pedido.
     *
     * Os relatórios gerados vivem fora de /public: chegam ao utilizador
     * apenas por aqui, depois de verificadas as permissões pelo controlador.
     */
    public static function ficheiro(string $caminho, string $nome): never
    {
        if (!is_file($caminho) || !is_readable($caminho)) {
            self::estado(404);

            exit;
        }

        // Qualquer saída anterior corromperia o ficheiro binário.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $tipo = match (strtolower(pathinfo($nome, PATHINFO_EXTENSION))) {
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf'   => 'application/pdf',
            default => 'application/octet-stream',
        };

        // O nome é higienizado: nunca se confia num valor para montar cabeçalhos.
        $nomeSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nome) ?? 'relatorio.docx';

        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . $nomeSeguro . '"; '
            . "filename*=UTF-8''" . rawurlencode($nome));
        header('Content-Length: ' . (string) filesize($caminho));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($caminho);
        exit;
    }

    /**
     * Define o código de estado da resposta.
     */
    public static function estado(int $codigo): void
    {
        if (!headers_sent()) {
            http_response_code($codigo);
        }
    }
}
