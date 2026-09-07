<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Semana;
use App\Core\Validator;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocxGenerator;
use App\Services\Mailer;
use App\Services\ReportBuilder;
use InvalidArgumentException;
use Throwable;

/**
 * Relatório semanal de atividade.
 *
 * Um relatório por colaborador e por semana ISO. Enquanto está em rascunho
 * pode ser editado; ao ser entregue, o conteúdo congela.
 */
final class ReportController extends Controller
{
    /**
     * Listagem. Um membro vê apenas os seus; o administrador vê todos.
     */
    public function index(): void
    {
        $filtros = $this->filtrosListagem();

        $this->ver('relatorios/index', [
            'relatorios'   => Report::listar($filtros),
            'filtros'      => $filtros,
            'temFiltros'   => $this->temFiltrosListagem($filtros),
            'utilizadores' => Auth::admin() ? User::todos(false) : [],
            'anos'         => Report::anosComRelatorios(),
            'ehAdmin'      => Auth::admin(),
        ]);
    }

    /**
     * Formulário de criação ou edição do relatório de uma semana.
     *
     * A semana vem da query string; por omissão é a semana ISO corrente.
     * Se já existir relatório para essa semana, é aberto para edição — ou
     * apresentado em modo de leitura, se já tiver sido entregue.
     */
    public function nova(): void
    {
        $utilizadorId = (int) Auth::id();

        try {
            [$ano, $semana] = $this->semanaPedida();
        } catch (InvalidArgumentException $e) {
            Flash::erro($e->getMessage());
            $this->redirecionar('/relatorios/nova');
        }

        $relatorio = Report::daSemana($utilizadorId, $ano, $semana);

        // Já existe e está entregue: mostra-se congelado, sem edição possível.
        if ($relatorio !== null && Report::congelado($relatorio)) {
            $this->redirecionar('/relatorios/' . (int) $relatorio['id']);
        }

        $construtor = new ReportBuilder($utilizadorId, $ano, $semana);

        // Num rascunho já gravado mostram-se as linhas guardadas; num
        // relatório novo, a proposta construída a partir do quadro.
        $linhas = $relatorio !== null
            ? Report::todasAsLinhas((int) $relatorio['id'])
            : $construtor->construir();

        $this->ver('relatorios/formulario', [
            'relatorio'    => $relatorio,
            'ano'          => $ano,
            'semana'       => $semana,
            'intervalo'    => Semana::intervalo($ano, $semana),
            'rotulo'       => Semana::rotulo($ano, $semana),
            'semanas'      => Semana::recentes(12),
            'linhas'       => $linhas,
            'sugestaoAuto' => $relatorio === null ? ($linhas['dificuldades'] ?? '') : null,
            'resumo'       => $construtor->resumo(),
            'utilizador'   => Auth::utilizador(),
            'dataEntrega'  => $this->dataEntregaSugerida($ano, $semana),
            'antigos'      => Flash::antigos(),
        ]);
    }

    /**
     * Vista de um relatório já gravado.
     */
    public function mostrar(string $id): void
    {
        $relatorio = Report::porId((int) $id);

        if ($relatorio === null) {
            Flash::erro('Relatório não encontrado.');
            $this->redirecionar('/relatorios');
        }

        if (!$this->podeVer($relatorio)) {
            Flash::erro('Não tem permissões para ver este relatório.');
            $this->redirecionar('/relatorios');
        }

        $this->ver('relatorios/detalhe', [
            'relatorio'    => $relatorio,
            'linhas'       => Report::todasAsLinhas((int) $id),
            'exportacoes'  => Report::exportacoes((int) $id),
            'envios'       => Report::envios((int) $id),
            'rotulo'       => Semana::rotulo((int) $relatorio['ano'], (int) $relatorio['numero_semana']),
            'podeEditar'   => $this->podeEditar($relatorio),
            'emailAtivo'   => Mailer::ativo(),
            'assuntoEmail' => Mailer::assuntoPorOmissao($relatorio),
            'antigos'      => Flash::antigos(),
        ]);
    }

    /**
     * Grava o relatório como rascunho ou entrega-o.
     *
     * A ação vem do botão premido: «rascunho» mantém a edição aberta,
     * «entregar» congela o conteúdo.
     */
    public function guardar(): void
    {
        $utilizadorId = (int) Auth::id();
        $entregar     = Request::post('accao') === 'entregar';

        try {
            [$ano, $semana] = $this->semanaDoFormulario();
        } catch (InvalidArgumentException $e) {
            Flash::erro($e->getMessage());
            $this->redirecionar('/relatorios/nova');
        }

        $existente = Report::daSemana($utilizadorId, $ano, $semana);

        // Um relatório entregue não volta a ser gravado.
        if ($existente !== null && Report::congelado($existente)) {
            Flash::erro('O relatório desta semana já foi entregue e não pode ser alterado.');
            $this->redirecionar('/relatorios/' . (int) $existente['id']);
        }

        $validador = $this->validarFormulario($entregar);

        if ($validador->falhou()) {
            $this->voltarComErros(
                $validador->erros(),
                sprintf('/relatorios/nova?ano=%d&semana=%d', $ano, $semana)
            );
        }

        $temSugestao = Request::post('tem_sugestao') === '1';
        $intervalo   = Semana::intervalo($ano, $semana);

        $campos = [
            'resumo_executivo'       => Request::post('resumo_executivo') ?: null,
            'bloqueios_riscos'       => Request::post('bloqueios_riscos') ?: null,
            'dificuldades'           => Request::post('dificuldades') ?: null,
            'tem_sugestao'           => $temSugestao ? 1 : 0,
            // Sem sugestão, o texto é explicitamente apagado.
            'sugestao_texto'         => $temSugestao ? (Request::post('sugestao_texto') ?: null) : null,
            'observacoes_adicionais' => Request::post('observacoes_adicionais') ?: null,
            'data_entrega'           => Request::post('data_entrega') ?: null,
            'status'                 => $entregar ? Report::ENTREGUE : Report::RASCUNHO,
        ];

        Database::iniciarTransacao();

        try {
            if ($existente === null) {
                $reportId = Report::criar(array_merge($campos, [
                    'user_id'       => $utilizadorId,
                    'ano'           => $ano,
                    'numero_semana' => $semana,
                    'semana_inicio' => $intervalo['inicio'],
                    'semana_fim'    => $intervalo['fim'],
                ]));
            } else {
                $reportId = (int) $existente['id'];
                Report::atualizar($reportId, $campos);
            }

            foreach (array_keys(Report::TABELAS_LINHAS) as $tabela) {
                Report::guardarLinhas($reportId, $tabela, $this->linhasDoFormulario($tabela));
            }

            Database::confirmar();
        } catch (Throwable $e) {
            Database::anular();

            throw $e;
        }

        AuditLogger::registar(
            'relatorio',
            $reportId,
            $entregar ? AuditLogger::ENTREGAR : ($existente === null ? AuditLogger::CRIAR : AuditLogger::ATUALIZAR),
            ['ano' => $ano, 'semana' => $semana, 'status' => $campos['status']]
        );

        if ($entregar) {
            Flash::sucesso(sprintf(
                'Relatório da semana %s entregue. O conteúdo ficou congelado.',
                Semana::rotuloCurto($ano, $semana)
            ));

            // A entrega gera logo o ficheiro. Se a geração falhar, a entrega
            // mantém-se — o relatório está guardado e pode ser gerado de novo.
            $this->gerarFicheiro($reportId);

            $this->redirecionar('/relatorios/' . $reportId);
        }

        Flash::sucesso('Rascunho guardado.');
        $this->redirecionar(sprintf('/relatorios/nova?ano=%d&semana=%d', $ano, $semana));
    }

    /**
     * Elimina um rascunho. Relatórios entregues nunca são eliminados por aqui.
     */
    public function eliminar(string $id): void
    {
        $relatorio = Report::porId((int) $id);

        if ($relatorio === null) {
            Flash::erro('Relatório não encontrado.');
            $this->redirecionar('/relatorios');
        }

        if (!$this->podeEditar($relatorio)) {
            Flash::erro('Não tem permissões para eliminar este relatório.');
            $this->redirecionar('/relatorios');
        }

        if (Report::congelado($relatorio)) {
            Flash::erro('Um relatório entregue não pode ser eliminado.');
            $this->redirecionar('/relatorios/' . (int) $id);
        }

        Report::eliminar((int) $id);
        AuditLogger::eliminado('relatorio', (int) $id, $relatorio);

        Flash::sucesso('Rascunho eliminado.');
        $this->redirecionar('/relatorios');
    }

    /**
     * Gera o ficheiro .docx de um relatório.
     *
     * Cada geração acrescenta um ficheiro novo e uma linha em `report_exports`:
     * o histórico de versões geradas nunca é substituído.
     */
    public function gerar(string $id): void
    {
        $relatorio = Report::porId((int) $id);

        if ($relatorio === null) {
            Flash::erro('Relatório não encontrado.');
            $this->redirecionar('/relatorios');
        }

        if (!$this->podeVer($relatorio)) {
            Flash::erro('Não tem permissões para gerar este relatório.');
            $this->redirecionar('/relatorios');
        }

        $this->gerarFicheiro((int) $id);

        $this->redirecionar('/relatorios/' . (int) $id);
    }

    /**
     * Gera o ficheiro de um relatório e regista-o.
     *
     * Uma falha aqui nunca desfaz a entrega: o relatório fica guardado e o
     * ficheiro pode ser gerado de novo a partir da página do relatório.
     */
    private function gerarFicheiro(int $reportId): void
    {
        $relatorio = Report::porId($reportId);

        if ($relatorio === null) {
            return;
        }

        try {
            $ficheiro = (new DocxGenerator())->gerar($relatorio);
        } catch (Throwable $e) {
            error_log('Falha ao gerar o .docx do relatório ' . $reportId . ': ' . $e->getMessage());

            Flash::erro('Não foi possível gerar o ficheiro: ' . $e->getMessage());

            return;
        }

        $exportId = Report::registarExportacao(
            $reportId,
            $ficheiro['caminho'],
            $ficheiro['hash'],
            Auth::id()
        );

        AuditLogger::registar('relatorio', $reportId, AuditLogger::GERAR, [
            'ficheiro' => $ficheiro['nome'],
            'hash'     => $ficheiro['hash'],
            'export'   => $exportId,
        ]);

        Flash::sucesso('Ficheiro gerado: ' . $ficheiro['nome']);
    }

    /**
     * Envia o relatório por correio eletrónico, com o .docx em anexo.
     *
     * Se ainda não houver ficheiro gerado, gera um antes de enviar: ninguém
     * quer descobrir que o anexo faltava depois de a mensagem ter partido.
     */
    public function enviarEmail(string $id): void
    {
        $reportId  = (int) $id;
        $relatorio = Report::porId($reportId);

        if ($relatorio === null) {
            Flash::erro('Relatório não encontrado.');
            $this->redirecionar('/relatorios');
        }

        if (!$this->podeVer($relatorio)) {
            Flash::erro('Não tem permissões para enviar este relatório.');
            $this->redirecionar('/relatorios');
        }

        if (!Mailer::ativo()) {
            Flash::erro('O envio de correio não está configurado. Consulte MAIL_* no ficheiro .env.');
            $this->redirecionar('/relatorios/' . $reportId);
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
            $this->voltarComErros($validador->erros(), '/relatorios/' . $reportId);
        }

        // Sem ficheiro gerado ainda, gera-se um agora.
        if (Report::ultimaExportacao($reportId) === null) {
            $this->gerarFicheiro($reportId);
        }

        $exportacao = Report::ultimaExportacao($reportId);

        if ($exportacao === null) {
            Flash::erro('Não foi possível preparar o ficheiro para envio.');
            $this->redirecionar('/relatorios/' . $reportId);
        }

        $anexo = Config::raiz((string) $exportacao['caminho_arquivo']);
        $utilizador = Auth::utilizador() ?? [];

        try {
            (new Mailer())->enviarRelatorio(
                $relatorio,
                $enderecos['validos'],
                $anexo,
                $assunto,
                $mensagem,
                (string) ($utilizador['nome'] ?? ''),
                (string) ($utilizador['email'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('Falha no envio do relatório ' . $reportId . ': ' . $e->getMessage());

            Flash::erro($e->getMessage());
            $this->redirecionar('/relatorios/' . $reportId);
        }

        // A partir daqui a mensagem já partiu e não há como a retirar. Uma
        // falha a registar o envio não pode passar por falha no envio: o
        // utilizador voltaria a enviar e a chefia receberia tudo em duplicado.
        try {
            Report::registarEnvio(
                $reportId,
                (int) $exportacao['id'],
                $enderecos['validos'],
                $assunto,
                $mensagem !== '' ? $mensagem : null,
                Auth::id()
            );

            AuditLogger::registar('relatorio', $reportId, 'enviar_email', [
                'destinatarios' => $enderecos['validos'],
                'ficheiro'      => basename((string) $exportacao['caminho_arquivo']),
            ]);

            Flash::sucesso(sprintf(
                'Relatório enviado para %s.',
                implode(', ', $enderecos['validos'])
            ));
        } catch (Throwable $e) {
            error_log('Envio feito mas não registado (relatório ' . $reportId . '): ' . $e->getMessage());

            Flash::aviso(sprintf(
                'O relatório foi enviado para %s, mas não foi possível registar o envio. Não reenvie.',
                implode(', ', $enderecos['validos'])
            ));
        }

        $this->redirecionar('/relatorios/' . $reportId);
    }

    /**
     * Envia um ficheiro gerado para descarregamento.
     *
     * Os ficheiros vivem fora de /public e só chegam ao utilizador por aqui,
     * depois de verificadas as permissões.
     */
    public function download(): void
    {
        $exportId = Request::queryInt('id');

        if ($exportId === null) {
            Flash::erro('Descarregamento inválido.');
            $this->redirecionar('/relatorios');
        }

        $exportacao = Report::exportacao($exportId);

        if ($exportacao === null) {
            Flash::erro('Ficheiro não encontrado.');
            $this->redirecionar('/relatorios');
        }

        if (!Auth::adminOuProprio((int) $exportacao['user_id'])) {
            Flash::erro('Não tem permissões para descarregar este ficheiro.');
            $this->redirecionar('/relatorios');
        }

        $caminho = Config::raiz((string) $exportacao['caminho_arquivo']);

        // O caminho vem da base de dados, mas confirma-se que continua dentro
        // da pasta de relatórios: nunca se serve nada fora dela.
        $real = realpath($caminho);
        $base = realpath((string) Config::get('relatorio.saida'));

        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            Flash::erro('O ficheiro já não está disponível.');
            $this->redirecionar('/relatorios/' . (int) $exportacao['report_id']);
        }

        Response::ficheiro($real, basename($real));
    }

    /**
     * Reconstrói a proposta automática de uma secção, em JSON.
     *
     * Serve o botão «Repor sugestão» do formulário, para quem apagou linhas
     * e quer voltar ao que o quadro propõe.
     */
    public function prePreencher(): void
    {
        try {
            [$ano, $semana] = $this->semanaPedida();
        } catch (InvalidArgumentException $e) {
            \App\Core\Response::erroJson($e->getMessage(), 422);
        }

        $construtor = new ReportBuilder((int) Auth::id(), $ano, $semana);

        $this->json([
            'linhas' => $construtor->construir(),
            'resumo' => $construtor->resumo(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Ano e semana pedidos na query string, com validação.
     *
     * @return array{0: int, 1: int}
     */
    private function semanaPedida(): array
    {
        $corrente = Semana::corrente();
        $ano      = Request::queryInt('ano') ?? $corrente['ano'];
        $semana   = Request::queryInt('semana') ?? $corrente['semana'];

        return $this->validarSemana($ano, $semana);
    }

    /**
     * Ano e semana submetidos no formulário, com validação.
     *
     * @return array{0: int, 1: int}
     */
    private function semanaDoFormulario(): array
    {
        $corrente = Semana::corrente();
        $ano      = Request::postInt('ano') ?? $corrente['ano'];
        $semana   = Request::postInt('numero_semana') ?? $corrente['semana'];

        return $this->validarSemana($ano, $semana);
    }

    /**
     * Rejeita anos e semanas que não existam na norma ISO-8601.
     *
     * @return array{0: int, 1: int}
     */
    private function validarSemana(int $ano, int $semana): array
    {
        if ($ano < 2000 || $ano > 2100) {
            throw new InvalidArgumentException('O ano indicado não é válido.');
        }

        if ($semana < 1 || $semana > Semana::semanasNoAno($ano)) {
            throw new InvalidArgumentException(
                sprintf('A semana %d não existe no ano %d.', $semana, $ano)
            );
        }

        return [$ano, $semana];
    }

    /**
     * Regras dos campos livres.
     *
     * A sugestão só é obrigatória quando o utilizador escolhe «Sim».
     */
    private function validarFormulario(bool $entregar): Validator
    {
        $temSugestao = Request::post('tem_sugestao') === '1';

        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'resumo_executivo'       => 'resumo executivo',
                'bloqueios_riscos'       => 'bloqueios, riscos e dependências',
                'dificuldades'           => 'dificuldades',
                'sugestao_texto'         => 'sugestão de melhoria',
                'observacoes_adicionais' => 'observações adicionais',
                'data_entrega'           => 'data de entrega',
            ])
            ->maximo('resumo_executivo', 5000)
            ->maximo('bloqueios_riscos', 5000)
            ->maximo('dificuldades', 5000)
            ->maximo('sugestao_texto', 5000)
            ->maximo('observacoes_adicionais', 5000)
            ->data('data_entrega');

        if ($temSugestao) {
            $validador->obrigatorio('sugestao_texto');
        }

        // O resumo executivo só é exigido no momento da entrega: um rascunho
        // pode ser guardado a meio.
        if ($entregar) {
            $validador
                ->obrigatorio('resumo_executivo')
                ->regra(
                    'resumo_executivo',
                    mb_strlen(trim((string) Request::post('resumo_executivo', ''))) >= 20,
                    'O resumo executivo deve ter pelo menos 20 caracteres.'
                );
        }

        return $validador;
    }

    /**
     * Linhas de uma secção lidas do formulário.
     *
     * Os campos chegam como arrays paralelos (um por coluna da tabela); as
     * linhas sem conteúdo útil são descartadas.
     *
     * @return list<array<string, mixed>>
     */
    private function linhasDoFormulario(string $tabela): array
    {
        $campos = Report::TABELAS_LINHAS[$tabela] ?? [];

        if ($campos === []) {
            return [];
        }

        $colunas = [];
        foreach ($campos as $campo) {
            $colunas[$campo] = Request::postArray($tabela . '_' . $campo);
        }

        // O número de linhas é o do maior dos arrays recebidos.
        $total = 0;
        foreach ($colunas as $valores) {
            $total = max($total, count($valores));
        }

        // O campo que identifica a linha como preenchida, por secção.
        $obrigatorio = match ($tabela) {
            'report_activities' => 'descricao',
            'report_incidents'  => 'descricao',
            'report_projects'   => 'nome_snapshot',
            'report_next_week'  => 'tarefa',
            default             => $campos[0],
        };

        $linhas = [];

        for ($i = 0; $i < $total; $i++) {
            $linha = [];

            foreach ($campos as $campo) {
                $valor = $colunas[$campo][$i] ?? null;
                $valor = is_string($valor) ? trim($valor) : $valor;

                $linha[$campo] = ($valor === '' || $valor === null) ? null : $valor;
            }

            // Uma linha sem o campo essencial preenchido é ruído do formulário.
            if ($linha[$obrigatorio] === null) {
                continue;
            }

            $linhas[] = $this->normalizarLinha($tabela, $linha);
        }

        return $linhas;
    }

    /**
     * Converte os valores de uma linha para os tipos que a base de dados espera.
     *
     * @param array<string, mixed> $linha
     * @return array<string, mixed>
     */
    private function normalizarLinha(string $tabela, array $linha): array
    {
        foreach (['task_id', 'project_id', 'tempo_dedicado_min'] as $campo) {
            if (!array_key_exists($campo, $linha)) {
                continue;
            }

            $valor = $linha[$campo];

            $linha[$campo] = ($valor === null || !is_numeric($valor) || (int) $valor <= 0)
                ? null
                : (int) $valor;
        }

        foreach (['data', 'prazo'] as $campo) {
            if (!array_key_exists($campo, $linha) || $linha[$campo] === null) {
                continue;
            }

            // Datas mal formadas são descartadas em vez de rebentar o INSERT.
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $linha[$campo]);

            if ($data === false || $data->format('Y-m-d') !== $linha[$campo]) {
                $linha[$campo] = null;
            }
        }

        // Trunca os textos ao tamanho das colunas, para nunca perder a gravação.
        $limites = [
            'descricao'     => 500,
            'projeto_area'  => 200,
            'estado'        => 80,
            'prioridade'    => 40,
            'nome_snapshot' => 200,
            'progresso'     => 40,
            'tarefa'        => 500,
        ];

        foreach ($limites as $campo => $limite) {
            if (isset($linha[$campo]) && is_string($linha[$campo])) {
                $linha[$campo] = mb_substr($linha[$campo], 0, $limite);
            }
        }

        return $linha;
    }

    /**
     * Data de entrega proposta: fim da semana mais o prazo configurado.
     */
    private function dataEntregaSugerida(int $ano, int $semana): string
    {
        return Semana::fim($ano, $semana)
            ->modify('+' . Setting::prazoEntregaDias() . ' days')
            ->format('Y-m-d');
    }

    /**
     * Filtros da listagem. Um membro fica sempre limitado aos seus relatórios.
     *
     * @return array{user_id: ?int, ano: ?int, semana: ?int, status: ?string}
     */
    private function filtrosListagem(): array
    {
        $status = Request::query('status');

        return [
            // Só o administrador escolhe de quem quer ver os relatórios.
            'user_id' => Auth::admin() ? Request::queryInt('utilizador') : (int) Auth::id(),
            'ano'     => Request::queryInt('ano'),
            'semana'  => Request::queryInt('semana'),
            'status'  => in_array($status, [Report::RASCUNHO, Report::ENTREGUE], true) ? $status : null,
        ];
    }

    /**
     * Indica se algum filtro visível está ativo.
     *
     * @param array<string, mixed> $filtros
     */
    private function temFiltrosListagem(array $filtros): bool
    {
        return (Auth::admin() && $filtros['user_id'] !== null)
            || $filtros['ano'] !== null
            || $filtros['semana'] !== null
            || $filtros['status'] !== null;
    }

    /**
     * O administrador vê todos; um membro vê apenas os seus.
     *
     * @param array<string, mixed> $relatorio
     */
    private function podeVer(array $relatorio): bool
    {
        return Auth::adminOuProprio((int) $relatorio['user_id']);
    }

    /**
     * Editar e eliminar são só do próprio autor, mesmo para o administrador:
     * um relatório é o testemunho de quem o escreveu.
     *
     * @param array<string, mixed> $relatorio
     */
    private function podeEditar(array $relatorio): bool
    {
        return (int) $relatorio['user_id'] === (int) Auth::id();
    }
}
