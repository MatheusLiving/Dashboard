<?php

declare(strict_types=1);

/**
 * Front-controller: único ponto de entrada da aplicação.
 *
 * Todos os pedidos são reescritos para aqui pelo .htaccess. Nenhum outro
 * ficheiro PHP fora de /public é acessível a partir do navegador.
 */

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

require dirname(__DIR__) . '/vendor/autoload.php';

Config::carregar(require dirname(__DIR__) . '/config/config.php');

// Em desenvolvimento os erros aparecem no ecrã; em produção só no registo.
$debug = Config::debug();
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', Config::raiz('storage/logs/php.log'));
error_reporting(E_ALL);

Response::cabecalhosSeguranca();
Session::iniciar();

// Disponibiliza o utilizador autenticado e o caminho atual a todas as vistas.
View::partilhar('utilizadorAtual', Auth::utilizador());
View::partilhar('caminhoAtual', Request::caminho());
View::partilhar('nomeApp', (string) Config::get('app.nome', 'Departamento de TI'));

$router = new Router();

// --- Autenticação -----------------------------------------------------------
$router->get('/login', [AuthController::class, 'formulario'], ['convidado']);
$router->post('/login', [AuthController::class, 'autenticar'], ['convidado']);
$router->post('/logout', [AuthController::class, 'terminar'], ['auth']);

// --- Painel -----------------------------------------------------------------
$router->get('/', [DashboardController::class, 'index'], ['auth']);

// --- Perfil do próprio utilizador -------------------------------------------
$router->get('/perfil', [AuthController::class, 'perfil'], ['auth']);
$router->post('/perfil', [AuthController::class, 'guardarPerfil'], ['auth']);
$router->post('/perfil/senha', [AuthController::class, 'alterarSenha'], ['auth']);

try {
    $router->despachar();
} catch (Throwable $e) {
    error_log(sprintf(
        '[%s] %s em %s:%d%s%s',
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));

    Response::estado(500);

    if (Request::esperaJson()) {
        Response::erroJson(
            $debug ? $e->getMessage() : 'Ocorreu um erro interno.',
            500
        );
    }

    View::render('errors/500', ['excecao' => $debug ? $e : null]);
}
