<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * A base de dados não respondeu.
 *
 * Distinta de um erro de SQL: aqui não houve sequer ligação — o servidor está
 * em baixo, o endereço está errado ou as credenciais não servem. Tem classe
 * própria para que o front-controller a possa apanhar sem andar a comparar
 * mensagens de erro, e mostrar uma página que diga o que fazer em vez de um
 * rasto de pilha com o DSN à vista.
 */
final class DatabaseUnavailableException extends RuntimeException
{
}
