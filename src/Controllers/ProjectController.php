<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Semana;
use App\Core\Validator;
use App\Models\ChangeReport;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ChangeReportOpener;
use Throwable;

/**
 * Gestão de projetos.
 *
 * Criar e editar está aberto a qualquer utilizador autenticado — a equipa é
 * pequena e os projetos são trabalho comum. Arquivar fica reservado ao
 * administrador e ao responsável do projeto.
 */
final class ProjectController extends Controller
{
    /**
     * Listagem com filtros por estado, responsável e pesquisa livre.
     */
    public function index(): void
    {
        $filtros = $this->filtros();

        $this->ver('projetos/index', [
            'projetos'     => Project::todos($filtros),
            'filtros'      => $filtros,
            'temFiltros'   => $this->temFiltros($filtros),
            'utilizadores' => User::todos(),
            'estados'      => Project::rotulosEstado(),
            'coresEstado'  => Project::coresEstado(),
            'prioridades'  => Project::rotulosPrioridade(),
            'antigos'      => Flash::antigos(),
        ]);
    }

    /**
     * Detalhe de um projeto: tarefas associadas e progresso calculado.
     */
    public function mostrar(string $id): void
    {
        $projeto = Project::porId((int) $id);

        if ($projeto === null) {
            Flash::erro('Projeto não encontrado.');
            $this->redirecionar('/projetos');
        }

        $this->ver('projetos/detalhe', [
            'projeto'      => $projeto,
            'tarefas'      => Project::tarefasDe((int) $id),
            'estatisticas' => Project::estatisticas((int) $id),
            'utilizadores' => User::todos(),
            'estados'      => Project::rotulosEstado(),
            'coresEstado'  => Project::coresEstado(),
            'prioridades'  => Project::rotulosPrioridade(),
            'podeArquivar' => $this->podeArquivar($projeto),
            'antigos'      => Flash::antigos(),
        ]);
    }

    /**
     * Cria um projeto.
     */
    public function criar(): void
    {
        $validador = $this->validar(Request::todosPost());

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/projetos');
        }

        $id = Project::criar($this->camposDoPedido());

        AuditLogger::criado('projeto', $id, Project::porId($id) ?? []);

        Flash::sucesso('Projeto «' . Request::post('nome') . '» criado.');

        $this->abrirRelatorioDeAlteracao($id);

        $this->redirecionar('/projetos/' . $id);
    }

    /**
     * Abre o Relatório de Alteração de Software do projeto acabado de criar.
     *
     * Nunca pode fazer falhar a criação do projeto: se a abertura correr mal,
     * fica o aviso e o relatório pode ser aberto à mão a partir da listagem.
     */
    private function abrirRelatorioDeAlteracao(int $projectId): void
    {
        if (!Setting::aberturaAutomatica()) {
            return;
        }

        try {
            $relatorioId = ChangeReportOpener::paraProjeto(Project::porId($projectId) ?? [], Auth::id());

            if ($relatorioId === null) {
                return;
            }

            $relatorio = ChangeReport::porId($relatorioId);

            AuditLogger::criado('relatorio_alteracao', $relatorioId, [
                'origem'  => 'projeto',
                'projeto' => $projectId,
            ]);

            Flash::info(sprintf(
                'Foi aberto o relatório de alteração %s para este projeto. Complete a secção 1 antes de começar o trabalho.',
                (string) ($relatorio['referencia'] ?? '')
            ));
        } catch (Throwable $e) {
            error_log('Falha ao abrir o relatório de alteração do projeto ' . $projectId . ': ' . $e->getMessage());

            Flash::aviso(
                'O projeto foi criado, mas não foi possível abrir o relatório de alteração. '
                . 'Pode abri-lo a partir da página Alterações.'
            );
        }
    }

    /**
     * Atualiza um projeto.
     */
    public function atualizar(string $id): void
    {
        $projetoId = (int) $id;
        $antes     = Project::porId($projetoId);

        if ($antes === null) {
            Flash::erro('Projeto não encontrado.');
            $this->redirecionar('/projetos');
        }

        $validador = $this->validar(Request::todosPost(), $projetoId);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/projetos/' . $projetoId);
        }

        Project::atualizar($projetoId, $this->camposDoPedido());

        AuditLogger::atualizado('projeto', $projetoId, $antes, Project::porId($projetoId) ?? []);

        Flash::sucesso('Projeto atualizado.');
        $this->redirecionar('/projetos/' . $projetoId);
    }

    /**
     * Arquiva ou desarquiva um projeto.
     */
    public function alternarArquivo(string $id): void
    {
        $projetoId = (int) $id;
        $projeto   = Project::porId($projetoId);

        if ($projeto === null) {
            Flash::erro('Projeto não encontrado.');
            $this->redirecionar('/projetos');
        }

        if (!$this->podeArquivar($projeto)) {
            Flash::erro('Só o administrador ou o responsável do projeto o pode arquivar.');
            $this->redirecionar('/projetos/' . $projetoId);
        }

        $arquivar = (int) $projeto['arquivado'] === 0;
        Project::definirArquivado($projetoId, $arquivar);

        AuditLogger::registar(
            'projeto',
            $projetoId,
            $arquivar ? 'arquivar' : 'desarquivar',
            ['nome' => $projeto['nome']]
        );

        Flash::sucesso(sprintf(
            'Projeto «%s» %s.',
            $projeto['nome'],
            $arquivar ? 'arquivado' : 'reposto'
        ));

        $this->redirecionar('/projetos/' . $projetoId);
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Filtros aceites na listagem.
     *
     * @return array{status: ?string, responsavel: ?int, arquivados: bool, procura: ?string}
     */
    private function filtros(): array
    {
        $status = Request::query('status');

        return [
            'status'      => in_array($status, Project::ESTADOS, true) ? $status : null,
            'responsavel' => Request::queryInt('responsavel'),
            'arquivados'  => Request::query('arquivados') === '1',
            'procura'     => Request::query('procura') ?: null,
        ];
    }

    /**
     * Indica se algum filtro está ativo.
     *
     * @param array<string, mixed> $filtros
     */
    private function temFiltros(array $filtros): bool
    {
        return $filtros['status'] !== null
            || $filtros['responsavel'] !== null
            || $filtros['arquivados'] === true
            || $filtros['procura'] !== null;
    }

    /**
     * Regras de validação partilhadas pela criação e pela edição.
     *
     * @param array<string, mixed> $dados
     */
    private function validar(array $dados, ?int $exceto = null): Validator
    {
        $nome  = trim((string) ($dados['nome'] ?? ''));
        $inicio = (string) ($dados['data_inicio'] ?? '');
        $prazo  = (string) ($dados['prazo'] ?? '');

        $validador = Validator::para($dados)
            ->rotulos([
                'nome'           => 'nome',
                'descricao'      => 'descrição',
                'status'         => 'estado',
                'prioridade'     => 'prioridade',
                'progresso_pct'  => 'progresso',
                'data_inicio'    => 'data de início',
                'prazo'          => 'prazo',
            ])
            ->obrigatorio('nome')
            ->maximo('nome', 180)
            ->em('status', Project::ESTADOS)
            ->em('prioridade', Project::PRIORIDADES)
            ->inteiro('progresso_pct', 0, 100)
            ->data('data_inicio')
            ->data('prazo')
            ->regra(
                'nome',
                $nome === '' || !Project::nomeExiste($nome, $exceto),
                'Já existe um projeto com esse nome.'
            );

        // O prazo antes da data de início seria um erro de digitação silencioso.
        if ($inicio !== '' && $prazo !== '') {
            $validador->regra(
                'prazo',
                strtotime($prazo) >= strtotime($inicio),
                'O prazo não pode ser anterior à data de início.'
            );
        }

        return $validador;
    }

    /**
     * Campos do projeto lidos do pedido, já normalizados.
     *
     * @return array<string, mixed>
     */
    private function camposDoPedido(): array
    {
        $responsavel = Request::postInt('responsavel_id');

        return [
            'nome'           => (string) Request::post('nome'),
            'descricao'      => Request::post('descricao') ?: null,
            'responsavel_id' => $responsavel !== null && $responsavel > 0 ? $responsavel : null,
            'status'         => (string) Request::post('status', 'planeado'),
            'progresso_pct'  => max(0, min(100, (int) Request::post('progresso_pct', '0'))),
            'prioridade'     => (string) Request::post('prioridade', 'media'),
            'data_inicio'    => Request::post('data_inicio') ?: null,
            'prazo'          => Request::post('prazo') ?: null,
        ];
    }

    /**
     * Só o administrador e o responsável podem arquivar um projeto.
     *
     * @param array<string, mixed> $projeto
     */
    private function podeArquivar(array $projeto): bool
    {
        return Auth::adminOuProprio(
            $projeto['responsavel_id'] === null ? null : (int) $projeto['responsavel_id']
        );
    }

    /**
     * Indica se um prazo já passou, para assinalar atrasos na interface.
     */
    public static function emAtraso(?string $prazo, string $status): bool
    {
        if ($prazo === null || $status === 'concluido') {
            return false;
        }

        return strtotime($prazo) < strtotime(Semana::agora()->format('Y-m-d'));
    }
}
