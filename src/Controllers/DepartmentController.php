<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Department;
use App\Services\AuditLogger;

/**
 * Quadro de assistências por departamento.
 *
 * Cada voto é um pedido de assistência técnica atribuído a um departamento.
 * Serve para a equipa de TI perceber de onde vem o trabalho — é informação
 * interna e **não entra no relatório semanal**.
 *
 * Registar votos está aberto a toda a equipa, porque é toda a equipa que
 * atende os pedidos. Gerir a lista de departamentos é do administrador.
 */
final class DepartmentController extends Controller
{
    /**
     * O quadro: classificação do período, evolução e últimos registos.
     */
    public function index(): void
    {
        $periodo   = Department::periodoValido(Request::query('periodo'));
        $intervalo = Department::intervalo($periodo);

        $classificacao = Department::classificacao(
            $intervalo['de'],
            $intervalo['ate'],
            // Um departamento desativado só aparece ao administrador, na
            // secção de gestão; para os restantes, saiu do quadro.
            Auth::admin()
        );

        $this->ver('departamentos/index', [
            'classificacao' => $classificacao,
            'periodo'       => $periodo,
            'intervalo'     => $intervalo,
            'totalPeriodo'  => Department::totalVotos($intervalo['de'], $intervalo['ate']),
            'totalSempre'   => Department::totalVotos(null, null),
            'porAutor'      => Department::porAutor($intervalo['de'], $intervalo['ate']),
            'evolucao'      => Department::evolucaoSemanal(8),
            'recentes'      => Department::votosRecentes(12),
            'paraVotar'     => Department::todos(true),
            'ehAdmin'       => Auth::admin(),
            'antigos'       => Flash::antigos(),
        ]);
    }

    /**
     * Regista um pedido de assistência atribuído a um departamento.
     *
     * O departamento vem no corpo do pedido, e não no caminho, porque o mesmo
     * ponto de entrada serve o formulário com nota (onde é escolhido num
     * seletor) e os botões «+1» da classificação. Sem isto, o formulário
     * precisaria de JavaScript para saber para onde submeter.
     */
    public function votar(): void
    {
        $departmentId = Request::postInt('department_id') ?? 0;
        $departamento = Department::porId($departmentId);

        if ($departamento === null) {
            Flash::erro('Departamento não encontrado.');
            $this->redirecionar($this->destino());
        }

        // Um departamento desativado deixou de receber registos; os votos
        // antigos continuam a contar, mas não se acrescentam mais.
        if ((int) $departamento['ativo'] === 0) {
            Flash::erro('O departamento «' . $departamento['nome'] . '» está desativado.');
            $this->redirecionar($this->destino());
        }

        $nota = (string) Request::post('nota', '');

        $validador = Validator::para(Request::todosPost())
            ->rotulo('nota', 'nota')
            ->maximo('nota', 200);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), $this->destino());
        }

        Department::votar($departmentId, Auth::id(), $nota);

        Flash::sucesso(sprintf(
            'Mais uma assistência registada em «%s».',
            $departamento['nome']
        ));

        $this->redirecionar($this->destino());
    }

    /**
     * Anula um voto registado por engano.
     *
     * O autor do voto e o administrador podem fazê-lo: um engano corrige-se,
     * e a contagem só é útil enquanto for verdadeira.
     */
    public function anularVoto(string $id): void
    {
        $voto = Department::voto((int) $id);

        if ($voto === null) {
            Flash::erro('Registo não encontrado.');
            $this->redirecionar($this->destino());
        }

        if (!Auth::adminOuProprio($voto['user_id'] === null ? null : (int) $voto['user_id'])) {
            Flash::erro('Só quem registou a assistência (ou um administrador) a pode anular.');
            $this->redirecionar($this->destino());
        }

        Department::anularVoto((int) $id);

        Flash::sucesso('Registo anulado em «' . $voto['departamento'] . '».');
        $this->redirecionar($this->destino());
    }

    // -----------------------------------------------------------------------
    // Gestão da lista (administrador)
    // -----------------------------------------------------------------------

    /**
     * Cria um departamento.
     */
    public function criar(): void
    {
        $nome = (string) Request::post('nome', '');
        $cor  = (string) Request::post('cor_hex', '#1F3864');

        $validador = $this->validar($nome, $cor);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), $this->destino());
        }

        $id = Department::criar($nome, $cor);
        AuditLogger::criado('departamento', $id, ['nome' => $nome, 'cor_hex' => $cor]);

        Flash::sucesso('Departamento «' . $nome . '» criado.');
        $this->redirecionar($this->destino());
    }

    /**
     * Atualiza o nome e a cor de um departamento.
     */
    public function atualizar(string $id): void
    {
        $departmentId = (int) $id;
        $antes        = Department::porId($departmentId);

        if ($antes === null) {
            Flash::erro('Departamento não encontrado.');
            $this->redirecionar($this->destino());
        }

        $nome = (string) Request::post('nome', '');
        $cor  = (string) Request::post('cor_hex', '#1F3864');

        $validador = $this->validar($nome, $cor, $departmentId);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), $this->destino());
        }

        Department::atualizar($departmentId, ['nome' => $nome, 'cor_hex' => $cor]);
        AuditLogger::atualizado('departamento', $departmentId, $antes, Department::porId($departmentId) ?? []);

        Flash::sucesso('Departamento atualizado.');
        $this->redirecionar($this->destino());
    }

    /**
     * Ativa ou desativa um departamento.
     *
     * Desativar tira-o do quadro de registo sem apagar a contagem: o que já
     * foi pedido continua a ter acontecido.
     */
    public function alternar(string $id): void
    {
        $departmentId = (int) $id;
        $departamento = Department::porId($departmentId);

        if ($departamento === null) {
            Flash::erro('Departamento não encontrado.');
            $this->redirecionar($this->destino());
        }

        $ativar = (int) $departamento['ativo'] === 0;
        Department::definirAtivo($departmentId, $ativar);

        AuditLogger::registar(
            'departamento',
            $departmentId,
            $ativar ? AuditLogger::ATIVAR : AuditLogger::DESATIVAR,
            ['nome' => $departamento['nome']]
        );

        Flash::sucesso(sprintf(
            'Departamento «%s» %s.',
            $departamento['nome'],
            $ativar ? 'reativado' : 'desativado'
        ));

        $this->redirecionar($this->destino());
    }

    /**
     * Elimina um departamento sem votos.
     *
     * Com votos registados, eliminar apagaria a contagem em cascata — nesse
     * caso o caminho é desativar.
     */
    public function eliminar(string $id): void
    {
        $departmentId = (int) $id;
        $departamento = Department::porId($departmentId);

        if ($departamento === null) {
            Flash::erro('Departamento não encontrado.');
            $this->redirecionar($this->destino());
        }

        $votos = Department::votosDoDepartamento($departmentId);

        if ($votos > 0) {
            Flash::erro(sprintf(
                '«%s» tem %d assistência%s registada%s. Desative-o em vez de o eliminar, para não perder a contagem.',
                $departamento['nome'],
                $votos,
                $votos === 1 ? '' : 's',
                $votos === 1 ? '' : 's'
            ));

            $this->redirecionar($this->destino());
        }

        Department::eliminar($departmentId);
        AuditLogger::eliminado('departamento', $departmentId, $departamento);

        Flash::sucesso('Departamento «' . $departamento['nome'] . '» eliminado.');
        $this->redirecionar($this->destino());
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Regras do nome e da cor, com o slug a garantir que não há repetidos.
     */
    private function validar(string $nome, string $cor, ?int $exceto = null): Validator
    {
        return Validator::para(['nome' => $nome, 'cor_hex' => $cor])
            ->rotulos(['nome' => 'nome', 'cor_hex' => 'cor'])
            ->obrigatorio('nome')
            ->maximo('nome', 80)
            ->corHex('cor_hex')
            ->regra(
                'nome',
                !Department::slugExiste(Department::slugDe($nome), $exceto),
                'Já existe um departamento com esse nome.'
            );
    }

    /**
     * Regresso ao quadro mantendo o período que estava a ser visto.
     */
    private function destino(): string
    {
        $periodo = Department::periodoValido(Request::post('periodo') ?? Request::query('periodo'));

        return '/departamentos?periodo=' . $periodo;
    }
}
