<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Encaminhador de pedidos do front-controller.
 *
 * Suporta segmentos dinâmicos ({id}), verificação de token CSRF nos métodos
 * de escrita e middlewares de autenticação por rota.
 */
final class Router
{
    /** @var array<string, list<array{padrao: string, regex: string, parametros: list<string>, acao: array{0: class-string, 1: string}, middleware: list<string>}>> */
    private array $rotas = [];

    /** @var callable|null Ação a executar quando nenhuma rota corresponde. */
    private $naoEncontrado = null;

    /**
     * @param array{0: class-string, 1: string} $acao  [Controlador::class, 'metodo']
     * @param list<string>                      $middleware  'auth' e/ou 'admin'
     */
    public function get(string $padrao, array $acao, array $middleware = []): void
    {
        $this->registar('GET', $padrao, $acao, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $acao
     * @param list<string>                      $middleware
     */
    public function post(string $padrao, array $acao, array $middleware = []): void
    {
        $this->registar('POST', $padrao, $acao, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $acao
     * @param list<string>                      $middleware
     */
    public function put(string $padrao, array $acao, array $middleware = []): void
    {
        $this->registar('PUT', $padrao, $acao, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $acao
     * @param list<string>                      $middleware
     */
    public function delete(string $padrao, array $acao, array $middleware = []): void
    {
        $this->registar('DELETE', $padrao, $acao, $middleware);
    }

    /**
     * Define o que fazer quando nenhuma rota corresponde ao pedido.
     */
    public function naoEncontrado(callable $acao): void
    {
        $this->naoEncontrado = $acao;
    }

    /**
     * Regista uma rota, convertendo o padrão numa expressão regular.
     *
     * @param array{0: class-string, 1: string} $acao
     * @param list<string>                      $middleware
     */
    private function registar(string $metodo, string $padrao, array $acao, array $middleware): void
    {
        $padrao     = '/' . trim($padrao, '/');
        $parametros = [];

        // {id} passa a um grupo de captura; o nome fica guardado pela ordem.
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$parametros): string {
                $parametros[] = $m[1];

                return '([^/]+)';
            },
            $padrao
        );

        $this->rotas[$metodo][] = [
            'padrao'     => $padrao,
            'regex'      => '#^' . $regex . '$#u',
            'parametros' => $parametros,
            'acao'       => $acao,
            'middleware' => $middleware,
        ];
    }

    /**
     * Procura a rota correspondente ao pedido e executa-a.
     */
    public function despachar(): void
    {
        $metodo  = Request::metodo();
        $caminho = Request::caminho();

        foreach ($this->rotas[$metodo] ?? [] as $rota) {
            if (preg_match($rota['regex'], $caminho, $encontrados) !== 1) {
                continue;
            }

            array_shift($encontrados);
            $argumentos = array_combine($rota['parametros'], $encontrados) ?: [];

            $this->aplicarMiddleware($rota['middleware']);
            $this->verificarCsrf($metodo);
            $this->executar($rota['acao'], $argumentos);

            return;
        }

        $this->responderNaoEncontrado();
    }

    /**
     * Aplica os middlewares declarados na rota.
     *
     * @param list<string> $middleware
     */
    private function aplicarMiddleware(array $middleware): void
    {
        foreach ($middleware as $nome) {
            match ($nome) {
                'auth'     => Auth::requireAuth(),
                'admin'    => Auth::requireAdmin(),
                'convidado' => Auth::requireConvidado(),
                default    => throw new RuntimeException(sprintf('Middleware desconhecido: %s', $nome)),
            };
        }
    }

    /**
     * Exige token CSRF válido em todos os métodos de escrita.
     */
    private function verificarCsrf(string $metodo): void
    {
        if (in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if (Csrf::valido(Csrf::doPedido())) {
            return;
        }

        if (Request::esperaJson()) {
            Response::erroJson('Sessão expirada ou pedido inválido. Recarregue a página.', 419);
        }

        // Em navegação normal devolvemos o utilizador à página anterior com uma
        // mensagem: um 419 em bruto não lhe diria nada de útil.
        Flash::erro('O pedido expirou ou é inválido. Tente novamente.');
        Response::redirecionar(Request::referer() ?? '/');
    }

    /**
     * Instancia o controlador e invoca o método da rota.
     *
     * @param array{0: class-string, 1: string} $acao
     * @param array<string, string>             $argumentos
     */
    private function executar(array $acao, array $argumentos): void
    {
        [$classe, $metodo] = $acao;

        if (!class_exists($classe)) {
            throw new RuntimeException(sprintf('Controlador não encontrado: %s', $classe));
        }

        $controlador = new $classe();

        if (!method_exists($controlador, $metodo)) {
            throw new RuntimeException(sprintf('Ação não encontrada: %s::%s', $classe, $metodo));
        }

        $controlador->{$metodo}(...array_values($argumentos));
    }

    /**
     * Responde 404, em JSON ou em HTML consoante o pedido.
     */
    private function responderNaoEncontrado(): void
    {
        Response::estado(404);

        if ($this->naoEncontrado !== null) {
            ($this->naoEncontrado)();

            return;
        }

        if (Request::esperaJson()) {
            Response::erroJson('Recurso não encontrado.', 404);
        }

        View::render('errors/404');
    }
}
