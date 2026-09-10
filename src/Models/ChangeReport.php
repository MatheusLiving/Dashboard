<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Relatório de Pedido e Acompanhamento de Alteração de Software.
 *
 * Abre-se quando nasce trabalho de desenvolvimento — um projeto novo, uma
 * tarefa de desenvolvimento ou de ajuda técnica — e acompanha-o até à entrega
 * ao cliente.
 *
 * Ao contrário do relatório semanal, que é entregue e congela, este documento
 * vive num ciclo: segue para aprovação, pode voltar com alterações pedidas e
 * ser corrigido. Por isso **cada versão fica guardada por inteiro** em
 * `change_report_versions`: quando alguém aprovar, tem de ser possível saber
 * exatamente o que essa pessoa leu.
 */
final class ChangeReport
{
    public const RASCUNHO           = 'rascunho';
    public const EM_APROVACAO       = 'em_aprovacao';
    public const APROVADO           = 'aprovado';
    public const ALTERACOES_PEDIDAS = 'alteracoes_pedidas';

    public const ESTADOS = [
        self::RASCUNHO,
        self::EM_APROVACAO,
        self::APROVADO,
        self::ALTERACOES_PEDIDAS,
    ];

    public const TIPOS       = ['bug', 'correcao', 'funcionalidade', 'melhoria', 'configuracao'];
    public const PRIORIDADES = ['baixa', 'media', 'alta', 'urgente'];
    public const ORIGENS     = ['projeto', 'tarefa', 'manual'];

    /**
     * Campos de conteúdo do relatório, na ordem em que aparecem no documento.
     *
     * A lista serve de fonte única: gravação, versões e geração do .docx
     * percorrem-na, para que nunca fique um campo esquecido num dos sítios.
     *
     * @var list<string>
     */
    public const CAMPOS = [
        'solicitado_por', 'cliente_projeto', 'sistema_afetado', 'tipo', 'prioridade',
        'data_abertura', 'data_prevista',
        'desc_problema', 'objetivo', 'ambito', 'modulos', 'impacto_riscos', 'estimativa', 'programadores',
        'desvios',
        'testes_realizados', 'resultados', 'validado_por',
        'resumo_execucao', 'diferencas', 'notas_cliente', 'commits', 'conclusao_programadores',
        'entrega',
    ];

    /** Campos das linhas de acompanhamento (secção 2). */
    public const CAMPOS_ATIVIDADE = ['data', 'estado', 'descricao', 'responsavel'];

    /**
     * @return array<string, string>
     */
    public static function rotulosEstado(): array
    {
        return [
            self::RASCUNHO           => 'Rascunho',
            self::EM_APROVACAO       => 'Em aprovação',
            self::APROVADO           => 'Aprovado',
            self::ALTERACOES_PEDIDAS => 'Alterações pedidas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function coresEstado(): array
    {
        return [
            self::RASCUNHO           => 'bg-slate-100 text-slate-600',
            self::EM_APROVACAO       => 'bg-sky-50 text-sky-700',
            self::APROVADO           => 'bg-emerald-50 text-emerald-700',
            self::ALTERACOES_PEDIDAS => 'bg-amber-50 text-amber-700',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function rotulosTipo(): array
    {
        return [
            'bug'            => 'Bug',
            'correcao'       => 'Correção de erro',
            'funcionalidade' => 'Nova funcionalidade',
            'melhoria'       => 'Melhoria',
            'configuracao'   => 'Alteração de configuração',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function rotulosPrioridade(): array
    {
        return [
            'baixa'   => 'Baixa',
            'media'   => 'Média',
            'alta'    => 'Alta',
            'urgente' => 'Urgente',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function rotulosMotivo(): array
    {
        return [
            'criacao'            => 'Abertura',
            'gravacao'           => 'Alteração gravada',
            'envio_aprovacao'    => 'Enviado para aprovação',
            'aprovacao'          => 'Aprovado',
            'alteracoes_pedidas' => 'Alterações pedidas',
            'reabertura'         => 'Reaberto',
        ];
    }

    // -----------------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------------

    /**
     * Um relatório pelo identificador, com autor, projeto e tarefa.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT r.*, u.nome AS autor, p.nome AS projeto_nome, t.titulo AS tarefa_titulo
             FROM change_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN projects p ON p.id = r.project_id
             LEFT JOIN tasks t ON t.id = r.task_id
             WHERE r.id = :id
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Listagem com filtros.
     *
     * @param array{estado?: ?string, tipo?: ?string, user_id?: ?int, project_id?: ?int, procura?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public static function listar(array $filtros = []): array
    {
        $condicoes  = [];
        $parametros = [];

        if (!empty($filtros['estado'])) {
            $condicoes[]           = 'r.estado = :estado';
            $parametros[':estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['tipo'])) {
            $condicoes[]         = 'r.tipo = :tipo';
            $parametros[':tipo'] = (string) $filtros['tipo'];
        }

        if (!empty($filtros['user_id'])) {
            $condicoes[]        = 'r.user_id = :uid';
            $parametros[':uid'] = (int) $filtros['user_id'];
        }

        if (!empty($filtros['project_id'])) {
            $condicoes[]        = 'r.project_id = :pid';
            $parametros[':pid'] = (int) $filtros['project_id'];
        }

        if (!empty($filtros['procura'])) {
            // Com prepared statements reais cada marcador só pode aparecer uma
            // vez, por isso o mesmo termo segue em marcadores distintos.
            $condicoes[] = '(r.referencia LIKE :p_ref OR r.cliente_projeto LIKE :p_cli OR r.desc_problema LIKE :p_desc)';
            $termo       = '%' . $filtros['procura'] . '%';
            $parametros[':p_ref']  = $termo;
            $parametros[':p_cli']  = $termo;
            $parametros[':p_desc'] = $termo;
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return Database::todos(
            'SELECT r.*, u.nome AS autor, p.nome AS projeto_nome, t.titulo AS tarefa_titulo,
                    (SELECT COUNT(*) FROM change_report_versions v WHERE v.change_report_id = r.id) AS total_versoes,
                    (SELECT COUNT(*) FROM change_report_exports e WHERE e.change_report_id = r.id) AS total_ficheiros
             FROM change_reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN projects p ON p.id = r.project_id
             LEFT JOIN tasks t ON t.id = r.task_id'
            . $onde .
            ' ORDER BY r.updated_at DESC, r.id DESC',
            $parametros
        );
    }

    /**
     * O relatório aberto para um projeto, se existir.
     *
     * @return array<string, mixed>|null
     */
    public static function doProjeto(int $projectId): ?array
    {
        return Database::primeiro(
            'SELECT * FROM change_reports WHERE project_id = :pid ORDER BY id ASC LIMIT 1',
            [':pid' => $projectId]
        );
    }

    /**
     * O relatório aberto para uma tarefa, se existir.
     *
     * @return array<string, mixed>|null
     */
    public static function daTarefa(int $taskId): ?array
    {
        return Database::primeiro(
            'SELECT * FROM change_reports WHERE task_id = :tid ORDER BY id ASC LIMIT 1',
            [':tid' => $taskId]
        );
    }

    /**
     * Contagem por estado, para o resumo da listagem.
     *
     * @return array<string, int>
     */
    public static function totaisPorEstado(): array
    {
        $totais = array_fill_keys(self::ESTADOS, 0);

        foreach (Database::todos('SELECT estado, COUNT(*) AS total FROM change_reports GROUP BY estado') as $linha) {
            $totais[(string) $linha['estado']] = (int) $linha['total'];
        }

        return $totais;
    }

    // -----------------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------------

    /**
     * Cria um relatório e devolve o identificador.
     *
     * A referência só é atribuída depois da inserção, a partir do próprio
     * identificador: assim é única sem depender de contagens, que duas
     * aberturas ao mesmo tempo poderiam ler iguais.
     *
     * @param array<string, mixed> $dados
     */
    public static function criar(array $dados): int
    {
        $registo = [
            'user_id'         => $dados['user_id'] ?? null,
            'project_id'      => $dados['project_id'] ?? null,
            'task_id'         => $dados['task_id'] ?? null,
            'origem'          => in_array($dados['origem'] ?? '', self::ORIGENS, true) ? $dados['origem'] : 'manual',
            'data_abertura'   => $dados['data_abertura'],
            'estado'          => self::RASCUNHO,
            'versao'          => 1,
        ];

        foreach (self::CAMPOS as $campo) {
            if ($campo === 'data_abertura') {
                continue;
            }

            $registo[$campo] = $dados[$campo] ?? null;
        }

        $registo['tipo']       = in_array($registo['tipo'] ?? '', self::TIPOS, true)
            ? $registo['tipo'] : 'funcionalidade';
        $registo['prioridade'] = in_array($registo['prioridade'] ?? '', self::PRIORIDADES, true)
            ? $registo['prioridade'] : 'media';

        $id = Database::inserir('change_reports', $registo);

        Database::atualizar('change_reports', $id, [
            'referencia' => sprintf('ALT-%s-%04d', substr((string) $dados['data_abertura'], 0, 4), $id),
        ]);

        return $id;
    }

    /**
     * Atualiza os campos de conteúdo.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        return Database::atualizar('change_reports', $id, $dados);
    }

    /**
     * Muda o estado do relatório.
     */
    public static function definirEstado(int $id, string $estado): int
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            return 0;
        }

        return Database::atualizar('change_reports', $id, ['estado' => $estado]);
    }

    /**
     * Elimina um relatório e, em cascata, versões, linhas, ficheiros e envios.
     */
    public static function eliminar(int $id): int
    {
        return Database::executar('DELETE FROM change_reports WHERE id = :id', [':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // Linhas de acompanhamento (secção 2)
    // -----------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public static function atividades(int $id): array
    {
        return Database::todos(
            'SELECT * FROM change_report_activities WHERE change_report_id = :id ORDER BY ordem ASC, id ASC',
            [':id' => $id]
        );
    }

    /**
     * Substitui as linhas de acompanhamento pelas recebidas.
     *
     * Regravar tudo é mais simples e mais seguro do que casar identificadores
     * vindos do formulário — e o histórico não se perde, porque cada versão
     * guarda a sua própria cópia das linhas.
     *
     * @param list<array<string, mixed>> $linhas
     */
    public static function guardarAtividades(int $id, array $linhas): void
    {
        Database::executar(
            'DELETE FROM change_report_activities WHERE change_report_id = :id',
            [':id' => $id]
        );

        $ordem = 1;

        foreach ($linhas as $linha) {
            $registo = ['change_report_id' => $id, 'ordem' => $ordem];

            foreach (self::CAMPOS_ATIVIDADE as $campo) {
                $registo[$campo] = $linha[$campo] ?? null;
            }

            Database::inserir('change_report_activities', $registo);
            $ordem++;
        }
    }

    // -----------------------------------------------------------------------
    // Versões
    // -----------------------------------------------------------------------

    /**
     * Conteúdo completo do relatório, tal como está agora.
     *
     * É esta estrutura que fica guardada em cada versão. Inclui as linhas de
     * acompanhamento, para que uma versão seja legível sozinha.
     *
     * @return array<string, mixed>
     */
    public static function conteudo(int $id): array
    {
        $relatorio = self::porId($id);

        if ($relatorio === null) {
            return [];
        }

        $conteudo = [
            'referencia' => $relatorio['referencia'],
            'estado'     => $relatorio['estado'],
        ];

        foreach (self::CAMPOS as $campo) {
            $conteudo[$campo] = $relatorio[$campo] ?? null;
        }

        $conteudo['atividades'] = array_map(
            static function (array $linha): array {
                $limpa = [];

                foreach (self::CAMPOS_ATIVIDADE as $campo) {
                    $limpa[$campo] = $linha[$campo] ?? null;
                }

                return $limpa;
            },
            self::atividades($id)
        );

        return $conteudo;
    }

    /**
     * Grava uma versão, se houver alguma coisa nova para gravar.
     *
     * Uma gravação que não mude nada não merece uma versão: encheria o
     * histórico de ruído e tornaria difícil encontrar as mudanças reais. As
     * mudanças de estado gravam sempre, mesmo sem alteração de conteúdo — é
     * o próprio acontecimento que interessa registar.
     *
     * @return int|null Identificador da versão criada, ou null se nada mudou.
     */
    public static function guardarVersao(
        int $id,
        string $motivo,
        ?string $nota,
        ?int $criadoPor,
        bool $mesmoSemMudancas = false
    ): ?int {
        $relatorio = self::porId($id);

        if ($relatorio === null) {
            return null;
        }

        $conteudo = self::conteudo($id);
        $json     = json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ultima   = self::ultimaVersao($id);

        if (!$mesmoSemMudancas && $ultima !== null && (string) $ultima['conteudo_json'] === (string) $json) {
            return null;
        }

        $numero = $ultima === null ? 1 : ((int) $ultima['versao'] + 1);

        $versaoId = Database::inserir('change_report_versions', [
            'change_report_id' => $id,
            'versao'           => $numero,
            'motivo'           => $motivo,
            'estado'           => (string) $relatorio['estado'],
            'nota'             => $nota,
            'conteudo_json'    => $json,
            'criado_por'       => $criadoPor,
        ]);

        Database::atualizar('change_reports', $id, ['versao' => $numero]);

        return $versaoId;
    }

    /**
     * Versões de um relatório, da mais recente para a mais antiga.
     *
     * @return list<array<string, mixed>>
     */
    public static function versoes(int $id): array
    {
        return Database::todos(
            'SELECT v.id, v.versao, v.motivo, v.estado, v.nota, v.created_at, v.criado_por,
                    u.nome AS autor
             FROM change_report_versions v
             LEFT JOIN users u ON u.id = v.criado_por
             WHERE v.change_report_id = :id
             ORDER BY v.versao DESC',
            [':id' => $id]
        );
    }

    /**
     * Uma versão, com o conteúdo já descodificado.
     *
     * @return array<string, mixed>|null
     */
    public static function versao(int $versaoId): ?array
    {
        $versao = Database::primeiro(
            'SELECT v.*, u.nome AS autor, r.referencia
             FROM change_report_versions v
             LEFT JOIN users u ON u.id = v.criado_por
             INNER JOIN change_reports r ON r.id = v.change_report_id
             WHERE v.id = :id
             LIMIT 1',
            [':id' => $versaoId]
        );

        if ($versao === null) {
            return null;
        }

        $conteudo = json_decode((string) $versao['conteudo_json'], true);
        $versao['conteudo'] = is_array($conteudo) ? $conteudo : [];

        return $versao;
    }

    /**
     * A versão mais recente, em bruto.
     *
     * @return array<string, mixed>|null
     */
    public static function ultimaVersao(int $id): ?array
    {
        return Database::primeiro(
            'SELECT * FROM change_report_versions
             WHERE change_report_id = :id
             ORDER BY versao DESC
             LIMIT 1',
            [':id' => $id]
        );
    }

    // -----------------------------------------------------------------------
    // Ficheiros e envios
    // -----------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public static function exportacoes(int $id): array
    {
        return Database::todos(
            'SELECT e.*, u.nome AS gerado_por_nome
             FROM change_report_exports e
             LEFT JOIN users u ON u.id = e.gerado_por
             WHERE e.change_report_id = :id
             ORDER BY e.gerado_em DESC, e.id DESC',
            [':id' => $id]
        );
    }

    /**
     * Uma exportação pelo identificador, com o dono do relatório.
     *
     * @return array<string, mixed>|null
     */
    public static function exportacao(int $exportId): ?array
    {
        return Database::primeiro(
            'SELECT e.*, r.user_id, r.referencia
             FROM change_report_exports e
             INNER JOIN change_reports r ON r.id = e.change_report_id
             WHERE e.id = :id
             LIMIT 1',
            [':id' => $exportId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function ultimaExportacao(int $id): ?array
    {
        return Database::primeiro(
            'SELECT * FROM change_report_exports
             WHERE change_report_id = :id
             ORDER BY gerado_em DESC, id DESC
             LIMIT 1',
            [':id' => $id]
        );
    }

    public static function registarExportacao(
        int $id,
        int $versao,
        string $caminho,
        string $hash,
        ?int $geradoPor
    ): int {
        return Database::inserir('change_report_exports', [
            'change_report_id' => $id,
            'versao'           => $versao,
            'caminho_arquivo'  => $caminho,
            'hash'             => $hash,
            'gerado_por'       => $geradoPor,
        ]);
    }

    /**
     * Caminhos dos ficheiros gerados, para limpar o disco na eliminação.
     *
     * @return list<string>
     */
    public static function caminhosExportados(int $id): array
    {
        $linhas = Database::todos(
            'SELECT caminho_arquivo FROM change_report_exports WHERE change_report_id = :id',
            [':id' => $id]
        );

        return array_map(static fn (array $l): string => (string) $l['caminho_arquivo'], $linhas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function envios(int $id): array
    {
        return Database::todos(
            'SELECT e.*, u.nome AS enviado_por_nome
             FROM change_report_emails e
             LEFT JOIN users u ON u.id = e.enviado_por
             WHERE e.change_report_id = :id
             ORDER BY e.enviado_em DESC, e.id DESC',
            [':id' => $id]
        );
    }

    /**
     * @param list<string> $destinatarios
     */
    public static function registarEnvio(
        int $id,
        ?int $exportId,
        int $versao,
        array $destinatarios,
        string $assunto,
        ?string $mensagem,
        ?int $enviadoPor
    ): int {
        return Database::inserir('change_report_emails', [
            'change_report_id' => $id,
            'export_id'        => $exportId,
            'versao'           => $versao,
            'destinatarios'    => mb_substr(implode(', ', $destinatarios), 0, 1000),
            'assunto'          => mb_substr($assunto, 0, 255),
            'mensagem'         => $mensagem,
            'enviado_por'      => $enviadoPor,
        ]);
    }
}
