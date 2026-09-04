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
use App\Controllers\KanbanController;
use App\Controllers\TagController;
use App\Controllers\TaskController;
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

// --- Quadro Kanban ----------------------------------------------------------
$router->get('/kanban', [KanbanController::class, 'index'], ['auth']);

// --- Tarefas (JSON, consumidas pelo quadro) ---------------------------------
$router->get('/api/tarefas/{id}', [TaskController::class, 'mostrar'], ['auth']);
$router->post('/api/tarefas', [TaskController::class, 'criar'], ['auth']);
$router->post('/api/tarefas/{id}', [TaskController::class, 'atualizar'], ['auth']);
$router->post('/api/tarefas/{id}/mover', [TaskController::class, 'mover'], ['auth']);
$router->post('/api/tarefas/{id}/eliminar', [TaskController::class, 'eliminar'], ['auth']);
$router->post('/api/tarefas/{id}/tempo', [TaskController::class, 'registarTempo'], ['auth']);
$router->post('/api/tempo/{id}/eliminar', [TaskController::class, 'eliminarTempo'], ['auth']);

// --- Etiquetas --------------------------------------------------------------
$router->get('/api/tags', [TagController::class, 'apiListar'], ['auth']);
$router->post('/api/tags', [TagController::class, 'apiCriar'], ['auth']);

$router->get('/tags', [TagController::class, 'index'], ['admin']);
$router->post('/tags', [TagController::class, 'criar'], ['admin']);
$router->post('/tags/{id}', [TagController::class, 'atualizar'], ['admin']);
$router->post('/tags/{id}/alternar', [TagController::class, 'alternar'], ['admin']);
$router->post('/tags/{id}/eliminar', [TagController::class, 'eliminar'], ['admin']);

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
