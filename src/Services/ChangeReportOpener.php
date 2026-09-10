<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Semana;
use App\Models\ChangeReport;
use App\Models\Setting;
use App\Models\User;

/**
 * Abertura automática do Relatório de Alteração de Software.
 *
 * Sempre que nasce trabalho de desenvolvimento — um projeto novo, uma tarefa
 * de desenvolvimento ou de ajuda técnica — abre-se o relatório já com o que a
 * aplicação sabe, para que o programador não comece de uma folha em branco.
 *
 * O que a aplicação preenche é uma **proposta**: cada campo continua editável
 * no formulário, e é o programador que completa a análise antes de o enviar
 * para aprovação.
 */
final class ChangeReportOpener
{
    /**
     * Abre o relatório de um projeto acabado de criar.
     *
     * @param array<string, mixed> $projeto Linha de `projects`
     */
    public static function paraProjeto(array $projeto, ?int $utilizadorId): ?int
    {
        $projectId = (int) $projeto['id'];

        // Nunca dois relatórios para o mesmo projeto.
        if (ChangeReport::doProjeto($projectId) !== null) {
            return null;
        }

        $responsavel = self::nome($projeto['responsavel_id'] ?? null);

        $id = ChangeReport::criar([
            'user_id'        => $utilizadorId,
            'project_id'     => $projectId,
            'origem'         => 'projeto',
            'data_abertura'  => Semana::agora()->format('Y-m-d'),
            'solicitado_por' => self::nome($utilizadorId),
            // Cópia do nome, não uma referência: o relatório segue para
            // aprovação e não pode mudar por o projeto ser renomeado depois.
            'cliente_projeto' => (string) $projeto['nome'],
            'sistema_afetado' => null,
            'tipo'            => 'funcionalidade',
            'prioridade'      => self::prioridade((string) ($projeto['prioridade'] ?? 'media')),
            'data_prevista'   => $projeto['prazo'] ?? null,
            'desc_problema'   => self::texto($projeto['descricao'] ?? null),
            'objetivo'        => 'Executar o projeto «' . $projeto['nome'] . '».',
            'programadores'   => $responsavel,
        ]);

        self::registarAbertura($id, 'Aberto com a criação do projeto.', $utilizadorId);

        return $id;
    }

    /**
     * Abre o relatório de uma tarefa acabada de criar, se for caso disso.
     *
     * @param array<string, mixed> $tarefa Linha de `tasks`
     * @param list<string>         $slugs  Slugs das etiquetas da tarefa
     */
    public static function paraTarefa(array $tarefa, array $slugs, ?int $utilizadorId): ?int
    {
        $taskId = (int) $tarefa['id'];

        if (ChangeReport::daTarefa($taskId) !== null) {
            return null;
        }

        if (!self::tarefaAbreRelatorio($slugs)) {
            return null;
        }

        return self::abrirParaTarefa($tarefa, $slugs, $utilizadorId, 'tarefa');
    }

    /**
     * Abre o relatório de uma tarefa a pedido, sem olhar às etiquetas.
     *
     * Serve o botão «Abrir relatório de alteração» do quadro, para o trabalho
     * que as etiquetas não apanharam.
     *
     * @param array<string, mixed> $tarefa
     * @param list<string>         $slugs
     */
    public static function manualParaTarefa(array $tarefa, array $slugs, ?int $utilizadorId): ?int
    {
        if (ChangeReport::daTarefa((int) $tarefa['id']) !== null) {
            return null;
        }

        return self::abrirParaTarefa($tarefa, $slugs, $utilizadorId, 'manual');
    }

    /**
     * Indica se as etiquetas de uma tarefa a classificam como trabalho de
     * desenvolvimento ou ajuda técnica.
     *
     * @param list<string> $slugs
     */
    public static function tarefaAbreRelatorio(array $slugs): bool
    {
        $gatilhos = Setting::tagsDesenvolvimento();

        if ($gatilhos === []) {
            return false;
        }

        return array_intersect($slugs, $gatilhos) !== [];
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $tarefa
     * @param list<string>         $slugs
     */
    private static function abrirParaTarefa(array $tarefa, array $slugs, ?int $utilizadorId, string $origem): int
    {
        $titulo = (string) $tarefa['titulo'];

        $id = ChangeReport::criar([
            'user_id'         => $utilizadorId,
            'task_id'         => (int) $tarefa['id'],
            'project_id'      => $tarefa['project_id'] ?? null,
            'origem'          => $origem,
            'data_abertura'   => Semana::agora()->format('Y-m-d'),
            'solicitado_por'  => self::nome($utilizadorId),
            'cliente_projeto' => self::texto($tarefa['projeto_nome'] ?? null) ?? $titulo,
            'sistema_afetado' => null,
            'tipo'            => self::tipoPorEtiquetas($slugs),
            'prioridade'      => self::prioridade((string) ($tarefa['prioridade'] ?? 'media')),
            'data_prevista'   => $tarefa['data_conclusao'] ?? null,
            'desc_problema'   => self::texto($tarefa['descricao'] ?? null) ?? $titulo,
            'objetivo'        => $titulo,
            'programadores'   => self::nome($tarefa['assignee_id'] ?? null),
        ]);

        self::registarAbertura(
            $id,
            $origem === 'manual'
                ? 'Aberto a pedido, a partir da tarefa.'
                : 'Aberto com a criação da tarefa.',
            $utilizadorId
        );

        return $id;
    }

    /**
     * Grava a versão 1, com o estado em que o relatório nasceu.
     *
     * Fica aqui, e não em quem chama, para que nenhuma via de abertura possa
     * esquecer-se do primeiro ponto do histórico.
     */
    private static function registarAbertura(int $id, string $nota, ?int $utilizadorId): void
    {
        ChangeReport::guardarVersao($id, 'criacao', $nota, $utilizadorId, true);
    }

    /**
     * Tipo de alteração deduzido das etiquetas da tarefa.
     *
     * É apenas um ponto de partida: o programador corrige-o no formulário.
     *
     * @param list<string> $slugs
     */
    private static function tipoPorEtiquetas(array $slugs): string
    {
        if (in_array('incidente', $slugs, true)) {
            return 'bug';
        }

        if (in_array('suporte', $slugs, true)) {
            return 'correcao';
        }

        if (in_array('melhoria', $slugs, true)) {
            return 'melhoria';
        }

        if (in_array('infraestrutura', $slugs, true) || in_array('redes', $slugs, true)) {
            return 'configuracao';
        }

        return 'funcionalidade';
    }

    /**
     * As prioridades do quadro e do relatório quase coincidem: só «crítica»
     * precisa de tradução.
     */
    private static function prioridade(string $prioridade): string
    {
        return match ($prioridade) {
            'critica' => 'urgente',
            'baixa', 'media', 'alta' => $prioridade,
            default => 'media',
        };
    }

    /**
     * Nome de um utilizador, ou null se não houver.
     */
    private static function nome(mixed $utilizadorId): ?string
    {
        if ($utilizadorId === null || (int) $utilizadorId <= 0) {
            return null;
        }

        $utilizador = User::porId((int) $utilizadorId);

        return $utilizador === null ? null : (string) $utilizador['nome'];
    }

    /**
     * Texto aproveitável, ou null quando está vazio.
     */
    private static function texto(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
