<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Comportamento comum a todos os controladores.
 */
abstract class Controller
{
    /**
     * Renderiza uma vista dentro do layout principal.
     *
     * @param array<string, mixed> $dados
     */
    protected function ver(string $vista, array $dados = [], string $layout = 'layout/base'): void
    {
        View::render($vista, $dados, $layout);
    }

    /**
     * Redireciona para um caminho interno.
     */
    protected function redirecionar(string $caminho): never
    {
        Response::redirecionar($caminho);
    }

    /**
     * Regressa à página anterior guardando os valores submetidos, para que o
     * formulário possa ser repovoado depois de um erro de validação.
     *
     * @param array<string, string> $erros
     */
    protected function voltarComErros(array $erros, string $destino): never
    {
        Flash::guardarAntigos(Request::todosPost());

        foreach ($erros as $mensagem) {
            Flash::erro($mensagem);
            break; // Mostra apenas o primeiro erro, para não inundar o ecrã.
        }

        Response::redirecionar($destino);
    }

    /**
     * Resposta JSON de sucesso.
     *
     * @param array<string, mixed> $dados
     */
    protected function json(array $dados = [], int $codigo = 200): never
    {
        Response::json(array_merge(['ok' => true], $dados), $codigo);
    }
}
