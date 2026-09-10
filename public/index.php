<?php

declare(strict_types=1);

/**
 * Front-controller: único ponto de entrada da aplicação.
 *
 * Todos os pedidos são reescritos para aqui pelo .htaccess. Nenhum outro
 * ficheiro PHP fora de /public é acessível a partir do navegador.
 */

use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\BacklogController;
use App\Controllers\ChangeReportController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\KanbanController;
use App\Controllers\ProjectController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Controllers\TagController;
use App\Controllers\TaskController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

// Com o servidor embutido do PHP (php -S) todos os pedidos chegam aqui, incluindo
// os de ficheiros que existem em disco. Devolver false entrega-os ao servidor,
// com o tipo de conteúdo correto — sem isto, o CSS e o JavaScript chegariam ao
// navegador como HTML e seriam recusados. Com o Apache é o .htaccess que trata disto.
if (PHP_SAPI === 'cli-server') {
    $caminho = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if (is_string($caminho) && $caminho !== '/') {
        $ficheiro = __DIR__ . DIRECTORY_SEPARATOR . ltrim(rawurldecode($caminho), '/\\');

        // realpath resolve ".." antes da comparação: só se servem ficheiros
        // que estejam mesmo dentro de /public.
        $real = realpath($ficheiro);

        if ($real !== false && is_file($real) && str_starts_with($real, __DIR__ . DIRECTORY_SEPARATOR)) {
            return false;
        }
    }
}

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
// O nome vem das configurações; o .env serve de recurso enquanto não houver uma.
View::partilhar('nomeApp', Setting::departamento((string) Config::get('app.nome', 'Departamento de TI')));

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

// --- Backlog ----------------------------------------------------------------
$router->get('/backlog', [BacklogController::class, 'index'], ['auth']);

// --- Projetos ---------------------------------------------------------------
$router->get('/projetos', [ProjectController::class, 'index'], ['auth']);
$router->post('/projetos', [ProjectController::class, 'criar'], ['auth']);
$router->get('/projetos/{id}', [ProjectController::class, 'mostrar'], ['auth']);
$router->post('/projetos/{id}', [ProjectController::class, 'atualizar'], ['auth']);
$router->post('/projetos/{id}/arquivar', [ProjectController::class, 'alternarArquivo'], ['auth']);

// --- Assistências por departamento ------------------------------------------
// Quadro informativo da equipa; nada daqui entra no relatório semanal.
$router->get('/departamentos', [DepartmentController::class, 'index'], ['auth']);
// «voto» é literal: tem de ser registada antes de /departamentos/{id}, senão
// seria lida como identificador de departamento.
$router->post('/departamentos/voto', [DepartmentController::class, 'votar'], ['auth']);
$router->post('/departamentos/votos/{id}/anular', [DepartmentController::class, 'anularVoto'], ['auth']);
$router->post('/departamentos', [DepartmentController::class, 'criar'], ['admin']);
$router->post('/departamentos/{id}', [DepartmentController::class, 'atualizar'], ['admin']);
$router->post('/departamentos/{id}/alternar', [DepartmentController::class, 'alternar'], ['admin']);
$router->post('/departamentos/{id}/eliminar', [DepartmentController::class, 'eliminar'], ['admin']);

// --- Relatório semanal ------------------------------------------------------
// A rota de criação vem antes da de detalhe: «nova» não é um identificador.
$router->get('/relatorios', [ReportController::class, 'index'], ['auth']);
$router->get('/relatorios/nova', [ReportController::class, 'nova'], ['auth']);
$router->post('/relatorios', [ReportController::class, 'guardar'], ['auth']);
$router->get('/api/relatorios/pre-preencher', [ReportController::class, 'prePreencher'], ['auth']);
// «download» vem antes de «{id}»: a rota literal tem de ganhar à dinâmica.
$router->get('/relatorios/download', [ReportController::class, 'download'], ['auth']);
$router->get('/relatorios/{id}', [ReportController::class, 'mostrar'], ['auth']);
$router->get('/relatorios/{id}/editar', [ReportController::class, 'editar'], ['auth']);
$router->post('/relatorios/{id}/gerar', [ReportController::class, 'gerar'], ['auth']);
$router->post('/relatorios/{id}/reabrir', [ReportController::class, 'reabrir'], ['auth']);
$router->post('/relatorios/{id}/email', [ReportController::class, 'enviarEmail'], ['auth']);
$router->post('/relatorios/{id}/eliminar', [ReportController::class, 'eliminar'], ['auth']);

// --- Relatório de Alteração de Software -------------------------------------
// «abrir», «download» e «versoes» são literais: têm de ser registadas antes
// das rotas com {id}, senão seriam lidas como identificadores.
$router->get('/alteracoes', [ChangeReportController::class, 'index'], ['auth']);
$router->get('/alteracoes/download', [ChangeReportController::class, 'download'], ['auth']);
$router->get('/alteracoes/versoes/{id}', [ChangeReportController::class, 'versao'], ['auth']);
$router->get('/alteracoes/{id}', [ChangeReportController::class, 'mostrar'], ['auth']);
$router->get('/alteracoes/{id}/editar', [ChangeReportController::class, 'editar'], ['auth']);
$router->post('/alteracoes/abrir', [ChangeReportController::class, 'abrir'], ['auth']);
$router->post('/alteracoes/{id}', [ChangeReportController::class, 'guardar'], ['auth']);
$router->post('/alteracoes/{id}/aprovacao', [ChangeReportController::class, 'enviarAprovacao'], ['auth']);
// Decidir é do administrador: quem escreve o relatório não o aprova a si próprio.
$router->post('/alteracoes/{id}/aprovar', [ChangeReportController::class, 'aprovar'], ['admin']);
$router->post('/alteracoes/{id}/alteracoes', [ChangeReportController::class, 'pedirAlteracoes'], ['admin']);
$router->post('/alteracoes/{id}/reabrir', [ChangeReportController::class, 'reabrir'], ['auth']);
$router->post('/alteracoes/{id}/gerar', [ChangeReportController::class, 'gerar'], ['auth']);
$router->post('/alteracoes/{id}/email', [ChangeReportController::class, 'enviarEmail'], ['auth']);
$router->post('/alteracoes/{id}/eliminar', [ChangeReportController::class, 'eliminar'], ['auth']);

// --- Etiquetas --------------------------------------------------------------
$router->get('/api/tags', [TagController::class, 'apiListar'], ['auth']);
$router->post('/api/tags', [TagController::class, 'apiCriar'], ['auth']);

$router->get('/tags', [TagController::class, 'index'], ['admin']);
$router->post('/tags', [TagController::class, 'criar'], ['admin']);
$router->post('/tags/{id}', [TagController::class, 'atualizar'], ['admin']);
$router->post('/tags/{id}/alternar', [TagController::class, 'alternar'], ['admin']);
$router->post('/tags/{id}/eliminar', [TagController::class, 'eliminar'], ['admin']);

// --- Administração ----------------------------------------------------------
$router->get('/utilizadores', [UserController::class, 'index'], ['admin']);
$router->post('/utilizadores', [UserController::class, 'criar'], ['admin']);
$router->post('/utilizadores/{id}', [UserController::class, 'atualizar'], ['admin']);
$router->post('/utilizadores/{id}/ativo', [UserController::class, 'alternarAtivo'], ['admin']);
$router->post('/utilizadores/{id}/senha', [UserController::class, 'redefinirSenha'], ['admin']);

$router->get('/configuracoes', [SettingsController::class, 'index'], ['admin']);
$router->post('/configuracoes', [SettingsController::class, 'guardar'], ['admin']);

$router->get('/auditoria', [AuditController::class, 'index'], ['admin']);

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
