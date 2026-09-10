<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ChangeReport;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ChangeDocxGenerator;
use App\Services\ChangeReportOpener;
use App\Services\Mailer;
use Throwable;

/**
 * Relatório de Pedido e Acompanhamento de Alteração de Software.
 *
 * O documento acompanha o trabalho de desenvolvimento e segue para aprovação.
 * Como pode voltar com alterações pedidas, **cada versão fica guardada por
 * inteiro**: nunca se perde o que estava escrito quando alguém decidiu.
 *
 * Ver está aberto a toda a equipa — é trabalho comum. Alterar é do autor (ou
 * de um administrador). Aprovar e pedir alterações é do administrador.
 */
final class ChangeReportController extends Controller
{
    /**
     * Listagem, com filtros e o resumo por estado.
     */
    public function index(): void
    {
        $filtros = $this->filtros();

        $this->ver('alteracoes/index', [
            'relatorios'      => ChangeReport::listar($filtros),
            'filtros'         => $filtros,
            'temFiltros'      => array_filter($filtros, static fn (mixed $v): bool => $v !== null && $v !== '') !== [],
            'totais'          => ChangeReport::totaisPorEstado(),
            'utilizadores'    => User::todos(false),
            'projetos'        => Project::ativos(),
            'rotulosEstado'   => ChangeReport::rotulosEstado(),
            'coresEstado'     => ChangeReport::coresEstado(),
            'rotulosTipo'     => ChangeReport::rotulosTipo(),
            'antigos'         => Flash::antigos(),
        ]);
    }

    /**
     * Vista de um relatório: conteúdo, histórico, ficheiros e envios.
     */
    public function mostrar(string $id): void
    {
        $relatorio = $this->relatorioOuVolta((int) $id);

        $this->ver('alteracoes/detalhe', [
            'relatorio'         => $relatorio,
            'atividades'        => ChangeReport::atividades((int) $id),
            'versoes'           => ChangeReport::versoes((int) $id),
            'exportacoes'       => ChangeReport::exportacoes((int) $id),
            'envios'            => ChangeReport::envios((int) $id),
            'rotulosEstado'     => ChangeReport::rotulosEstado(),
            'coresEstado'       => ChangeReport::coresEstado(),
            'rotulosTipo'       => ChangeReport::rotulosTipo(),
            'rotulosPrioridade' => ChangeReport::rotulosPrioridade(),
            'rotulosMotivo'     => ChangeReport::rotulosMotivo(),
            'podeEditar'        => $this->podeEditar($relatorio),
            'podeDecidir'       => Auth::admin(),
            'emailAtivo'        => Mailer::ativo(),
            'assuntoEmail'      => $this->assunto($relatorio),
            'antigos'           => Flash::antigos(),
        ]);
    }

    /**
     * Formulário de edição.
     */
    public function editar(string $id): void
    {
        $relatorio = $this->relatorioOuVolta((int) $id);

        if (!$this->podeEditar($relatorio)) {
            Flash::erro('Só o autor (ou um administrador) pode alterar este relatório.');
            $this->redirecionar('/alteracoes/' . (int) $id);
        }

        // Um relatório aprovado não se edita por cima: reabre-se primeiro, e
        // a reabertura fica no histórico. É o que garante que o que foi
        // aprovado continua a poder ser lido tal como foi aprovado.
        if ($relatorio['estado'] === ChangeReport::APROVADO) {
            Flash::aviso('Este relatório está aprovado. Reabra-o para poder alterá-lo.');
            $this->redirecionar('/alteracoes/' . (int) $id);
        }

        $this->ver('alteracoes/formulario', [
            'relatorio'         => $relatorio,
            'atividades'        => ChangeReport::atividades((int) $id),
            'rotulosTipo'       => ChangeReport::rotulosTipo(),
            'rotulosPrioridade' => ChangeReport::rotulosPrioridade(),
            'antigos'           => Flash::antigos(),
        ]);
    }

    /**
     * Grava as alterações e, se o conteúdo mudou, guarda uma versão.
     */
    public function guardar(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if (!$this->podeEditar($relatorio)) {
            Flash::erro('Só o autor (ou um administrador) pode alterar este relatório.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        if ($relatorio['estado'] === ChangeReport::APROVADO) {
            Flash::erro('Um relatório aprovado não pode ser alterado. Reabra-o primeiro.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        $validador = $this->validarFormulario();

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/alteracoes/' . $reportId . '/editar');
        }

        Database::iniciarTransacao();

        try {
            ChangeReport::atualizar($reportId, $this->camposDoPedido());
            ChangeReport::guardarAtividades($reportId, $this->atividadesDoPedido());

            Database::confirmar();
        } catch (Throwable $e) {
            Database::anular();

            throw $e;
        }

        // Uma gravação que não muda nada não merece uma versão: encheria o
        // histórico e escondia as mudanças que interessam.
        $versao = ChangeReport::guardarVersao($reportId, 'gravacao', null, Auth::id());

        AuditLogger::registar('relatorio_alteracao', $reportId, AuditLogger::ATUALIZAR, [
            'referencia' => $relatorio['referencia'],
            'versao'     => $versao === null ? (int) $relatorio['versao'] : null,
        ]);

        Flash::sucesso($versao === null
            ? 'Nada mudou — o relatório ficou como estava.'
            : 'Relatório gravado. Ficou registada uma versão nova no histórico.');

        $this->redirecionar('/alteracoes/' . $reportId);
    }

    /**
     * Abre um relatório a pedido, a partir de uma tarefa ou de um projeto.
     *
     * Serve o trabalho que a abertura automática não apanhou.
     */
    public function abrir(): void
    {
        $taskId    = Request::postInt('task_id');
        $projectId = Request::postInt('project_id');

        try {
            if ($taskId !== null) {
                $tarefa = Task::porId($taskId);

                if ($tarefa === null) {
                    Flash::erro('Tarefa não encontrada.');
                    $this->redirecionar('/alteracoes');
                }

                $existente = ChangeReport::daTarefa($taskId);

                if ($existente !== null) {
                    Flash::info('Esta tarefa já tem um relatório de alteração.');
                    $this->redirecionar('/alteracoes/' . (int) $existente['id']);
                }

                $slugs = array_map(
                    static fn (array $tag): string => (string) $tag['slug'],
                    Task::tagsDe($taskId)
                );

                $novoId = ChangeReportOpener::manualParaTarefa($tarefa, $slugs, Auth::id());
            } elseif ($projectId !== null) {
                $projeto = Project::porId($projectId);

                if ($projeto === null) {
                    Flash::erro('Projeto não encontrado.');
                    $this->redirecionar('/alteracoes');
                }

                $existente = ChangeReport::doProjeto($projectId);

                if ($existente !== null) {
                    Flash::info('Este projeto já tem um relatório de alteração.');
                    $this->redirecionar('/alteracoes/' . (int) $existente['id']);
                }

                $novoId = ChangeReportOpener::paraProjeto($projeto, Auth::id());
            } else {
                Flash::erro('Indique a tarefa ou o projeto a que o relatório diz respeito.');
                $this->redirecionar('/alteracoes');
            }
        } catch (Throwable $e) {
            error_log('Falha ao abrir relatório de alteração: ' . $e->getMessage());

            Flash::erro('Não foi possível abrir o relatório.');
            $this->redirecionar('/alteracoes');
        }

        if ($novoId === null) {
            Flash::erro('Não foi possível abrir o relatório.');
            $this->redirecionar('/alteracoes');
        }

        AuditLogger::criado('relatorio_alteracao', $novoId, ['origem' => 'manual']);

        Flash::sucesso('Relatório aberto. Complete a secção 1 antes de começar o trabalho.');
        $this->redirecionar('/alteracoes/' . $novoId . '/editar');
    }

    // -----------------------------------------------------------------------
    // Ciclo de aprovação
    // -----------------------------------------------------------------------

    /**
     * Envia o relatório para aprovação.
     */
    public function enviarAprovacao(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if (!$this->podeEditar($relatorio)) {
            Flash::erro('Só o autor (ou um administrador) pode enviar este relatório para aprovação.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        if ($relatorio['estado'] === ChangeReport::EM_APROVACAO) {
            Flash::info('Este relatório já está em aprovação.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        // A secção 1 é o pedido: sem ela, não há nada para aprovar.
        $falta = $this->camposEmFalta($relatorio, ['desc_problema', 'objetivo', 'ambito']);

        if ($falta !== []) {
            Flash::erro('Complete a secção 1 antes de enviar para aprovação: ' . implode(', ', $falta) . '.');
            $this->redirecionar('/alteracoes/' . $reportId . '/editar');
        }

        ChangeReport::definirEstado($reportId, ChangeReport::EM_APROVACAO);

        ChangeReport::guardarVersao(
            $reportId,
            'envio_aprovacao',
            $this->nota(),
            Auth::id(),
            true
        );

        AuditLogger::registar('relatorio_alteracao', $reportId, 'enviar_aprovacao', [
            'referencia' => $relatorio['referencia'],
        ]);

        // O ficheiro é gerado agora: quem aprova tem de poder ler exatamente
        // esta versão, e não uma reconstruída mais tarde.
        $this->gerarFicheiro($reportId);

        Flash::sucesso('Relatório enviado para aprovação. Foi gerada a versão correspondente do .docx.');
        $this->redirecionar('/alteracoes/' . $reportId);
    }

    /**
     * Regista a aprovação.
     */
    public function aprovar(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if ($relatorio['estado'] !== ChangeReport::EM_APROVACAO) {
            Flash::erro('Só um relatório em aprovação pode ser aprovado.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        ChangeReport::definirEstado($reportId, ChangeReport::APROVADO);
        ChangeReport::guardarVersao($reportId, 'aprovacao', $this->nota(), Auth::id(), true);

        AuditLogger::registar('relatorio_alteracao', $reportId, 'aprovar', [
            'referencia' => $relatorio['referencia'],
        ]);

        Flash::sucesso('Relatório aprovado. O conteúdo desta versão fica guardado tal como foi aprovado.');
        $this->redirecionar('/alteracoes/' . $reportId);
    }

    /**
     * Devolve o relatório ao autor, com alterações pedidas.
     */
    public function pedirAlteracoes(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if ($relatorio['estado'] !== ChangeReport::EM_APROVACAO) {
            Flash::erro('Só um relatório em aprovação pode voltar com alterações pedidas.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        $nota = $this->nota();

        // Pedir alterações sem dizer quais deixaria o autor a adivinhar.
        if ($nota === null) {
            Flash::erro('Escreva o que precisa de ser alterado.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        ChangeReport::definirEstado($reportId, ChangeReport::ALTERACOES_PEDIDAS);
        ChangeReport::guardarVersao($reportId, 'alteracoes_pedidas', $nota, Auth::id(), true);

        AuditLogger::registar('relatorio_alteracao', $reportId, 'pedir_alteracoes', [
            'referencia' => $relatorio['referencia'],
        ]);

        Flash::sucesso('Alterações pedidas. O relatório voltou para edição.');
        $this->redirecionar('/alteracoes/' . $reportId);
    }

    /**
     * Reabre um relatório aprovado, para uma correção posterior.
     */
    public function reabrir(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if (!$this->podeEditar($relatorio)) {
            Flash::erro('Só o autor (ou um administrador) pode reabrir este relatório.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        if ($relatorio['estado'] !== ChangeReport::APROVADO) {
            $this->redirecionar('/alteracoes/' . $reportId . '/editar');
        }

        ChangeReport::definirEstado($reportId, ChangeReport::RASCUNHO);
        ChangeReport::guardarVersao($reportId, 'reabertura', $this->nota(), Auth::id(), true);

        AuditLogger::registar('relatorio_alteracao', $reportId, AuditLogger::REABRIR, [
            'referencia' => $relatorio['referencia'],
        ]);

        Flash::aviso(
            'Relatório reaberto. A versão aprovada continua no histórico — '
            . 'quem a aprovou continua a poder ler exatamente o que aprovou.'
        );

        $this->redirecionar('/alteracoes/' . $reportId . '/editar');
    }

    /**
     * Vista de uma versão guardada.
     */
    public function versao(string $id): void
    {
        $versao = ChangeReport::versao((int) $id);

        if ($versao === null) {
            Flash::erro('Versão não encontrada.');
            $this->redirecionar('/alteracoes');
        }

        $relatorio = ChangeReport::porId((int) $versao['change_report_id']);

        if ($relatorio === null) {
            Flash::erro('Relatório não encontrado.');
            $this->redirecionar('/alteracoes');
        }

        $this->ver('alteracoes/versao', [
            'versao'            => $versao,
            'relatorio'         => $relatorio,
            'rotulosEstado'     => ChangeReport::rotulosEstado(),
            'coresEstado'       => ChangeReport::coresEstado(),
            'rotulosTipo'       => ChangeReport::rotulosTipo(),
            'rotulosPrioridade' => ChangeReport::rotulosPrioridade(),
            'rotulosMotivo'     => ChangeReport::rotulosMotivo(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Ficheiro e envio
    // -----------------------------------------------------------------------

    /**
     * Gera uma nova versão do .docx.
     */
    public function gerar(string $id): void
    {
        $this->relatorioOuVolta((int) $id);
        $this->gerarFicheiro((int) $id);

        $this->redirecionar('/alteracoes/' . (int) $id);
    }

    /**
     * Gera o ficheiro e regista-o.
     *
     * Uma falha aqui nunca desfaz a mudança de estado que a antecedeu: o
     * relatório está guardado e o ficheiro pode ser gerado outra vez.
     */
    private function gerarFicheiro(int $reportId): void
    {
        $relatorio = ChangeReport::porId($reportId);

        if ($relatorio === null) {
            return;
        }

        try {
            $ficheiro = (new ChangeDocxGenerator())->gerar($relatorio);
        } catch (Throwable $e) {
            error_log('Falha ao gerar o .docx do relatório de alteração ' . $reportId . ': ' . $e->getMessage());

            Flash::erro('Não foi possível gerar o ficheiro: ' . $e->getMessage());

            return;
        }

        $exportId = ChangeReport::registarExportacao(
            $reportId,
            $ficheiro['versao'],
            $ficheiro['caminho'],
            $ficheiro['hash'],
            Auth::id()
        );

        AuditLogger::registar('relatorio_alteracao', $reportId, AuditLogger::GERAR, [
            'ficheiro' => $ficheiro['nome'],
            'versao'   => $ficheiro['versao'],
            'export'   => $exportId,
        ]);

        Flash::sucesso('Ficheiro gerado: ' . $ficheiro['nome']);
    }

    /**
     * Envia o relatório por correio, com o .docx em anexo.
     */
    public function enviarEmail(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if (!Mailer::ativo()) {
            Flash::erro('O envio de correio não está configurado. Consulte MAIL_* no ficheiro .env.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        $enderecos = Mailer::separarEnderecos((string) Request::post('destinatarios', ''));
        $assunto   = trim((string) Request::post('assunto', ''));
        $mensagem  = (string) Request::post('mensagem', '');

        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'destinatarios' => 'destinatários',
                'assunto'       => 'assunto',
                'mensagem'      => 'mensagem',
            ])
            ->obrigatorio('destinatarios')
            ->obrigatorio('assunto')
            ->maximo('assunto', 255)
            ->maximo('mensagem', 5000)
            ->regra(
                'destinatarios',
                $enderecos['invalidos'] === [],
                'Endereços inválidos: ' . implode(', ', $enderecos['invalidos']) . '.'
            )
            ->regra(
                'destinatarios',
                $enderecos['validos'] !== [],
                'Indique pelo menos um endereço de correio válido.'
            )
            ->regra(
                'destinatarios',
                count($enderecos['validos']) <= Mailer::MAX_DESTINATARIOS,
                sprintf('Máximo de %d destinatários por envio.', Mailer::MAX_DESTINATARIOS)
            );

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/alteracoes/' . $reportId);
        }

        if (ChangeReport::ultimaExportacao($reportId) === null) {
            $this->gerarFicheiro($reportId);
        }

        $exportacao = ChangeReport::ultimaExportacao($reportId);

        if ($exportacao === null) {
            Flash::erro('Não foi possível preparar o ficheiro para envio.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        $utilizador = Auth::utilizador() ?? [];

        try {
            (new Mailer())->enviarDocumento(
                $enderecos['validos'],
                Config::raiz((string) $exportacao['caminho_arquivo']),
                $assunto,
                sprintf(
                    'Segue em anexo o Relatório de Pedido e Acompanhamento de Alteração de Software %s, na versão %d.',
                    (string) $relatorio['referencia'],
                    (int) $exportacao['versao']
                ),
                [
                    'Referência'      => (string) $relatorio['referencia'],
                    'Cliente / Projeto' => (string) ($relatorio['cliente_projeto'] ?? ''),
                    'Tipo'            => ChangeReport::rotulosTipo()[$relatorio['tipo']] ?? '',
                    'Prioridade'      => ChangeReport::rotulosPrioridade()[$relatorio['prioridade']] ?? '',
                    'Estado'          => ChangeReport::rotulosEstado()[$relatorio['estado']] ?? '',
                    'Versão'          => (string) (int) $exportacao['versao'],
                ],
                $mensagem,
                (string) ($utilizador['nome'] ?? ''),
                (string) ($utilizador['email'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('Falha no envio do relatório de alteração ' . $reportId . ': ' . $e->getMessage());

            Flash::erro($e->getMessage());
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        // A partir daqui a mensagem já partiu. Uma falha a registar o envio
        // não pode passar por falha no envio: o utilizador voltaria a enviar
        // e quem aprova receberia tudo em duplicado.
        try {
            ChangeReport::registarEnvio(
                $reportId,
                (int) $exportacao['id'],
                (int) $exportacao['versao'],
                $enderecos['validos'],
                $assunto,
                $mensagem !== '' ? $mensagem : null,
                Auth::id()
            );

            AuditLogger::registar('relatorio_alteracao', $reportId, AuditLogger::ENVIAR, [
                'destinatarios' => $enderecos['validos'],
                'versao'        => (int) $exportacao['versao'],
            ]);

            Flash::sucesso('Relatório enviado para ' . implode(', ', $enderecos['validos']) . '.');
        } catch (Throwable $e) {
            error_log('Envio feito mas não registado (alteração ' . $reportId . '): ' . $e->getMessage());

            Flash::aviso(sprintf(
                'O relatório foi enviado para %s, mas não foi possível registar o envio. Não reenvie.',
                implode(', ', $enderecos['validos'])
            ));
        }

        $this->redirecionar('/alteracoes/' . $reportId);
    }

    /**
     * Descarrega um ficheiro gerado.
     */
    public function download(): void
    {
        $exportId = Request::queryInt('id');

        if ($exportId === null) {
            Flash::erro('Descarregamento inválido.');
            $this->redirecionar('/alteracoes');
        }

        $exportacao = ChangeReport::exportacao($exportId);

        if ($exportacao === null) {
            Flash::erro('Ficheiro não encontrado.');
            $this->redirecionar('/alteracoes');
        }

        $caminho = Config::raiz((string) $exportacao['caminho_arquivo']);

        // O caminho vem da base de dados, mas confirma-se que continua dentro
        // da pasta de relatórios de alteração: nunca se serve nada fora dela.
        $real = realpath($caminho);
        $base = realpath((string) Config::get('alteracao.saida'));

        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            Flash::erro('O ficheiro já não está disponível.');
            $this->redirecionar('/alteracoes/' . (int) $exportacao['change_report_id']);
        }

        Response::ficheiro($real, basename($real));
    }

    /**
     * Elimina um relatório, com o histórico, os ficheiros e os envios.
     */
    public function eliminar(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = $this->relatorioOuVolta($reportId);

        if (!$this->podeEditar($relatorio)) {
            Flash::erro('Só o autor (ou um administrador) pode eliminar este relatório.');
            $this->redirecionar('/alteracoes/' . $reportId);
        }

        // Os caminhos têm de ser lidos antes: as linhas desaparecem em cascata.
        $caminhos = ChangeReport::caminhosExportados($reportId);

        // A base de dados primeiro; pela ordem inversa ficariam registos a
        // apontar para ficheiros já apagados.
        ChangeReport::eliminar($reportId);

        $apagados = $this->eliminarFicheirosGerados($caminhos);

        AuditLogger::eliminado('relatorio_alteracao', $reportId, array_merge($relatorio, [
            'ficheiros_removidos' => $apagados,
        ]));

        Flash::sucesso(sprintf(
            'Relatório %s eliminado%s.',
            (string) $relatorio['referencia'],
            $apagados > 0 ? sprintf(', com %d ficheiro%s', $apagados, $apagados === 1 ? '' : 's') : ''
        ));

        $this->redirecionar('/alteracoes');
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Apaga do disco os ficheiros gerados, nunca fora da pasta de destino.
     *
     * @param list<string> $caminhos
     */
    private function eliminarFicheirosGerados(array $caminhos): int
    {
        $base = realpath((string) Config::get('alteracao.saida'));

        if ($base === false) {
            return 0;
        }

        $apagados = 0;

        foreach ($caminhos as $caminho) {
            $real = realpath(Config::raiz($caminho));

            if ($real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (@unlink($real)) {
                $apagados++;
                continue;
            }

            error_log('Não foi possível apagar o ficheiro gerado: ' . $real);
        }

        return $apagados;
    }

    /**
     * Lê o relatório ou devolve o utilizador à listagem.
     *
     * @return array<string, mixed>
     */
    private function relatorioOuVolta(int $id): array
    {
        $relatorio = ChangeReport::porId($id);

        if ($relatorio === null) {
            Flash::erro('Relatório de alteração não encontrado.');
            $this->redirecionar('/alteracoes');
        }

        return $relatorio;
    }

    /**
     * Alterar é do autor; um administrador também pode, porque o documento
     * segue para fora da equipa e alguém tem de o poder corrigir.
     *
     * @param array<string, mixed> $relatorio
     */
    private function podeEditar(array $relatorio): bool
    {
        return Auth::admin() || (int) ($relatorio['user_id'] ?? 0) === (int) Auth::id();
    }

    /**
     * Nota escrita na decisão, ou null quando vem vazia.
     */
    private function nota(): ?string
    {
        $nota = trim((string) Request::post('nota', ''));

        return $nota === '' ? null : mb_substr($nota, 0, 2000);
    }

    /**
     * Campos obrigatórios ainda por preencher.
     *
     * @param array<string, mixed> $relatorio
     * @param list<string>         $campos
     * @return list<string>
     */
    private function camposEmFalta(array $relatorio, array $campos): array
    {
        $rotulos = [
            'desc_problema' => '1.1 Descrição do problema',
            'objetivo'      => '1.2 Objetivo',
            'ambito'        => '1.3 Âmbito',
        ];

        $falta = [];

        foreach ($campos as $campo) {
            if (trim((string) ($relatorio[$campo] ?? '')) === '') {
                $falta[] = $rotulos[$campo] ?? $campo;
            }
        }

        return $falta;
    }

    /**
     * Assunto proposto para o envio.
     *
     * @param array<string, mixed> $relatorio
     */
    private function assunto(array $relatorio): string
    {
        return sprintf(
            'Alteração de Software %s — %s',
            (string) $relatorio['referencia'],
            (string) ($relatorio['cliente_projeto'] ?? '')
        );
    }

    /**
     * Regras dos campos do formulário.
     */
    private function validarFormulario(): Validator
    {
        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'cliente_projeto' => 'cliente / projeto',
                'sistema_afetado' => 'sistema / aplicação afetada',
                'solicitado_por'  => 'solicitado por',
                'data_abertura'   => 'data de abertura',
                'data_prevista'   => 'data prevista de entrega',
            ])
            ->obrigatorio('data_abertura')
            ->data('data_abertura')
            ->data('data_prevista')
            ->maximo('solicitado_por', 200)
            ->maximo('cliente_projeto', 200)
            ->maximo('sistema_afetado', 200)
            ->em('tipo', ChangeReport::TIPOS)
            ->em('prioridade', ChangeReport::PRIORIDADES);

        foreach ([
            'desc_problema', 'objetivo', 'ambito', 'modulos', 'impacto_riscos', 'estimativa',
            'programadores', 'desvios', 'testes_realizados', 'resultados', 'validado_por',
            'resumo_execucao', 'diferencas', 'notas_cliente', 'commits',
            'conclusao_programadores', 'entrega',
        ] as $campo) {
            $validador->maximo($campo, 5000);
        }

        return $validador;
    }

    /**
     * Campos de conteúdo lidos do formulário.
     *
     * @return array<string, mixed>
     */
    private function camposDoPedido(): array
    {
        $campos = [];

        foreach (ChangeReport::CAMPOS as $campo) {
            $valor = Request::post($campo);

            $campos[$campo] = ($valor === null || $valor === '') ? null : $valor;
        }

        // Estes três nunca ficam vazios: têm de ter sempre um valor válido.
        $campos['data_abertura'] = $campos['data_abertura'] ?? date('Y-m-d');
        $campos['tipo']          = in_array($campos['tipo'] ?? '', ChangeReport::TIPOS, true)
            ? $campos['tipo'] : 'funcionalidade';
        $campos['prioridade']    = in_array($campos['prioridade'] ?? '', ChangeReport::PRIORIDADES, true)
            ? $campos['prioridade'] : 'media';

        return $campos;
    }

    /**
     * Linhas de acompanhamento lidas do formulário.
     *
     * Os campos chegam como arrays paralelos; as linhas sem descrição são
     * ruído do formulário e são descartadas.
     *
     * @return list<array<string, mixed>>
     */
    private function atividadesDoPedido(): array
    {
        $colunas = [];

        foreach (ChangeReport::CAMPOS_ATIVIDADE as $campo) {
            $colunas[$campo] = Request::postArray('ac_' . $campo);
        }

        $total = 0;

        foreach ($colunas as $valores) {
            $total = max($total, count($valores));
        }

        $limites = ['estado' => 80, 'descricao' => 500, 'responsavel' => 120];
        $linhas  = [];

        for ($i = 0; $i < $total; $i++) {
            $linha = [];

            foreach (ChangeReport::CAMPOS_ATIVIDADE as $campo) {
                $valor = $colunas[$campo][$i] ?? null;
                $valor = is_string($valor) ? trim($valor) : $valor;

                if ($valor === '' || $valor === null) {
                    $linha[$campo] = null;
                    continue;
                }

                $linha[$campo] = isset($limites[$campo])
                    ? mb_substr((string) $valor, 0, $limites[$campo])
                    : $valor;
            }

            if ($linha['descricao'] === null) {
                continue;
            }

            // Uma data mal formada é descartada em vez de rebentar o INSERT.
            if ($linha['data'] !== null) {
                $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $linha['data']);

                if ($data === false || $data->format('Y-m-d') !== $linha['data']) {
                    $linha['data'] = null;
                }
            }

            $linhas[] = $linha;
        }

        return $linhas;
    }

    /**
     * Filtros aceites na listagem.
     *
     * @return array{estado: ?string, tipo: ?string, user_id: ?int, project_id: ?int, procura: ?string}
     */
    private function filtros(): array
    {
        $estado = Request::query('estado');
        $tipo   = Request::query('tipo');

        return [
            'estado'     => in_array($estado, ChangeReport::ESTADOS, true) ? $estado : null,
            'tipo'       => in_array($tipo, ChangeReport::TIPOS, true) ? $tipo : null,
            'user_id'    => Request::queryInt('utilizador'),
            'project_id' => Request::queryInt('projeto'),
            'procura'    => Request::query('procura'),
        ];
    }
}
