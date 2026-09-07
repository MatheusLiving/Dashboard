<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `reports` e às suas linhas.
 *
 * Existe no máximo um relatório por colaborador e por semana ISO — regra
 * garantida pela chave única (user_id, ano, numero_semana).
 *
 * As linhas guardadas em `report_*` são cópias do texto, não referências:
 * quando o relatório é entregue, o seu conteúdo deixa de poder mudar por
 * efeito colateral de uma tarefa apagada ou de um projeto renomeado.
 */
final class Report
{
    public const RASCUNHO = 'rascunho';
    public const ENTREGUE = 'entregue';

    /**
     * Tabelas das linhas e respetivos campos copiáveis.
     *
     * @var array<string, list<string>>
     */
    public const TABELAS_LINHAS = [
        'report_activities' => ['task_id', 'data', 'descricao', 'projeto_area', 'estado', 'tempo_dedicado_min'],
        'report_incidents'  => ['task_id', 'descricao', 'prioridade', 'estado', 'resolucao_notas'],
        'report_projects'   => ['project_id', 'nome_snapshot', 'progresso', 'proximos_passos', 'observacoes'],
        'report_next_week'  => ['task_id', 'tarefa', 'prioridade', 'prazo'],
    ];

    /**
     * Um relatório pelo identificador, com o nome do colaborador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT r.*, u.nome AS colaborador, u.funcao_cargo, u.email
             FROM reports r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.id = :id
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * O relatório de um colaborador numa semana, se existir.
     *
     * @return array<string, mixed>|null
     */
    public static function daSemana(int $userId, int $ano, int $semana): ?array
    {
        return Database::primeiro(
            'SELECT r.*, u.nome AS colaborador, u.funcao_cargo, u.email
             FROM reports r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.user_id = :uid AND r.ano = :ano AND r.numero_semana = :semana
             LIMIT 1',
            [':uid' => $userId, ':ano' => $ano, ':semana' => $semana]
        );
    }

    /**
     * Listagem com filtros. Um membro só vê os seus.
     *
     * @param array{user_id?: ?int, ano?: ?int, semana?: ?int, status?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public static function listar(array $filtros = []): array
    {
        $condicoes  = [];
        $parametros = [];

        if (!empty($filtros['user_id'])) {
            $condicoes[]        = 'r.user_id = :uid';
            $parametros[':uid'] = (int) $filtros['user_id'];
        }

        if (!empty($filtros['ano'])) {
            $condicoes[]        = 'r.ano = :ano';
            $parametros[':ano'] = (int) $filtros['ano'];
        }

        if (!empty($filtros['semana'])) {
            $condicoes[]           = 'r.numero_semana = :semana';
            $parametros[':semana'] = (int) $filtros['semana'];
        }

        if (!empty($filtros['status'])) {
            $condicoes[]           = 'r.status = :status';
            $parametros[':status'] = (string) $filtros['status'];
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return Database::todos(
            'SELECT r.*, u.nome AS colaborador, u.funcao_cargo,
                    (SELECT COUNT(*) FROM report_exports e WHERE e.report_id = r.id) AS total_exportacoes
             FROM reports r
             INNER JOIN users u ON u.id = r.user_id'
            . $onde .
            ' ORDER BY r.ano DESC, r.numero_semana DESC, u.nome ASC',
            $parametros
        );
    }

    /**
     * Anos com relatórios, para o filtro da listagem.
     *
     * @return list<int>
     */
    public static function anosComRelatorios(): array
    {
        $linhas = Database::todos('SELECT DISTINCT ano FROM reports ORDER BY ano DESC');

        return array_map(static fn (array $l): int => (int) $l['ano'], $linhas);
    }

    /**
     * Cria um relatório em rascunho e devolve o identificador.
     *
     * @param array<string, mixed> $dados
     */
    public static function criar(array $dados): int
    {
        return Database::inserir('reports', [
            'user_id'                => $dados['user_id'],
            'ano'                    => $dados['ano'],
            'numero_semana'          => $dados['numero_semana'],
            'semana_inicio'          => $dados['semana_inicio'],
            'semana_fim'             => $dados['semana_fim'],
            'data_entrega'           => $dados['data_entrega'] ?? null,
            'resumo_executivo'       => $dados['resumo_executivo'] ?? null,
            'bloqueios_riscos'       => $dados['bloqueios_riscos'] ?? null,
            'dificuldades'           => $dados['dificuldades'] ?? null,
            'tem_sugestao'           => $dados['tem_sugestao'] ?? 0,
            'sugestao_texto'         => $dados['sugestao_texto'] ?? null,
            'observacoes_adicionais' => $dados['observacoes_adicionais'] ?? null,
            'status'                 => $dados['status'] ?? self::RASCUNHO,
        ]);
    }

    /**
     * Atualiza os campos livres de um relatório.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        return Database::atualizar('reports', $id, $dados);
    }

    /**
     * Indica se um relatório já foi entregue e, por isso, está congelado.
     *
     * @param array<string, mixed> $relatorio
     */
    public static function congelado(array $relatorio): bool
    {
        return ($relatorio['status'] ?? self::RASCUNHO) === self::ENTREGUE;
    }

    // -----------------------------------------------------------------------
    // Linhas das secções 2, 3, 4 e 7
    // -----------------------------------------------------------------------

    /**
     * Linhas de uma secção, pela ordem gravada.
     *
     * @return list<array<string, mixed>>
     */
    public static function linhas(int $reportId, string $tabela): array
    {
        if (!isset(self::TABELAS_LINHAS[$tabela])) {
            return [];
        }

        // O nome da tabela vem da constante acima, nunca do pedido HTTP.
        return Database::todos(
            sprintf('SELECT * FROM `%s` WHERE report_id = :rid ORDER BY ordem ASC, id ASC', $tabela),
            [':rid' => $reportId]
        );
    }

    /**
     * Todas as linhas de um relatório, agrupadas por secção.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function todasAsLinhas(int $reportId): array
    {
        $tudo = [];

        foreach (array_keys(self::TABELAS_LINHAS) as $tabela) {
            $tudo[$tabela] = self::linhas($reportId, $tabela);
        }

        return $tudo;
    }

    /**
     * Substitui todas as linhas de uma secção pelas recebidas.
     *
     * As linhas são regravadas por inteiro a cada gravação: é mais simples e
     * mais seguro do que tentar casar identificadores vindos do formulário.
     *
     * @param list<array<string, mixed>> $linhas
     */
    public static function guardarLinhas(int $reportId, string $tabela, array $linhas): void
    {
        if (!isset(self::TABELAS_LINHAS[$tabela])) {
            return;
        }

        Database::executar(
            sprintf('DELETE FROM `%s` WHERE report_id = :rid', $tabela),
            [':rid' => $reportId]
        );

        $ordem = 1;

        foreach ($linhas as $linha) {
            $registo = ['report_id' => $reportId, 'ordem' => $ordem];

            foreach (self::TABELAS_LINHAS[$tabela] as $campo) {
                $registo[$campo] = $linha[$campo] ?? null;
            }

            Database::inserir($tabela, $registo);
            $ordem++;
        }
    }

    /**
     * Elimina um relatório e, em cascata, todas as suas linhas.
     */
    public static function eliminar(int $id): int
    {
        return Database::executar('DELETE FROM reports WHERE id = :id', [':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // Exportações
    // -----------------------------------------------------------------------

    /**
     * Ficheiros gerados a partir de um relatório, do mais recente ao mais antigo.
     *
     * @return list<array<string, mixed>>
     */
    public static function exportacoes(int $reportId): array
    {
        return Database::todos(
            'SELECT e.*, u.nome AS gerado_por_nome
             FROM report_exports e
             LEFT JOIN users u ON u.id = e.gerado_por
             WHERE e.report_id = :rid
             ORDER BY e.gerado_em DESC, e.id DESC',
            [':rid' => $reportId]
        );
    }

    /**
     * Uma exportação pelo identificador, com o dono do relatório.
     *
     * @return array<string, mixed>|null
     */
    public static function exportacao(int $id): ?array
    {
        return Database::primeiro(
            'SELECT e.*, r.user_id, r.ano, r.numero_semana
             FROM report_exports e
             INNER JOIN reports r ON r.id = e.report_id
             WHERE e.id = :id
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Regista um ficheiro gerado. Nunca há substituição: cada geração é uma
     * linha nova, para que o histórico de versões se mantenha.
     */
    public static function registarExportacao(
        int $reportId,
        string $caminho,
        string $hash,
        ?int $geradoPor,
        string $formato = 'docx'
    ): int {
        return Database::inserir('report_exports', [
            'report_id'       => $reportId,
            'formato'         => $formato,
            'caminho_arquivo' => $caminho,
            'hash'            => $hash,
            'gerado_por'      => $geradoPor,
        ]);
    }
}
