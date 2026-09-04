<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Motor de templates mínimo sobre ficheiros PHP em /views.
 *
 * Uma vista é renderizada para uma variável e depois embrulhada num layout,
 * que a recebe em $conteudo. As vistas não contêm lógica de negócio.
 */
final class View
{
    /** @var array<string, mixed> Dados partilhados por todas as vistas. */
    private static array $partilhados = [];

    /**
     * Disponibiliza uma variável a todas as vistas (utilizador autenticado, etc.).
     */
    public static function partilhar(string $chave, mixed $valor): void
    {
        self::$partilhados[$chave] = $valor;
    }

    /** Título da página, definido pela vista através de View::titulo(). */
    private static ?string $titulo = null;

    /**
     * Define o título da página. Chamado pela vista, é lido depois pelo layout.
     */
    public static function titulo(string $titulo): void
    {
        self::$titulo = $titulo;
    }

    /**
     * Renderiza uma vista dentro de um layout e envia-a para o cliente.
     *
     * A vista é processada antes do layout, para que possa definir o título
     * através de View::titulo() e o layout já o encontre definido.
     *
     * @param array<string, mixed> $dados
     */
    public static function render(string $vista, array $dados = [], string $layout = 'layout/base'): void
    {
        self::$titulo = null;

        $conteudo = self::capturar($vista, $dados);

        echo self::capturar($layout, array_merge($dados, [
            'conteudo' => $conteudo,
            'titulo'   => $dados['titulo'] ?? self::$titulo,
        ]));
    }

    /**
     * Renderiza uma vista sem layout e devolve o resultado.
     *
     * @param array<string, mixed> $dados
     */
    public static function parcial(string $vista, array $dados = []): string
    {
        return self::capturar($vista, $dados);
    }

    /**
     * Executa o ficheiro da vista com buffer de saída e devolve o HTML.
     *
     * @param array<string, mixed> $dados
     */
    private static function capturar(string $vista, array $dados): string
    {
        $ficheiro = Config::raiz('views/' . str_replace('.', '/', $vista) . '.php');

        if (!is_file($ficheiro)) {
            throw new RuntimeException(sprintf('Vista não encontrada: %s', $vista));
        }

        // As variáveis das vistas são extraídas de um array controlado por nós.
        extract(array_merge(self::$partilhados, $dados), EXTR_SKIP);

        ob_start();

        try {
            require $ficheiro;
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Escapa texto para saída em HTML. Usar em toda a interpolação de vistas.
     */
    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escapa texto e converte quebras de linha em <br>.
     */
    public static function nl(mixed $valor): string
    {
        return nl2br(self::e($valor), false);
    }
}
