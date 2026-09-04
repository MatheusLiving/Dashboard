<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Semana;
use App\Core\Validator;
use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskTimeLog;
use App\Services\AuditLogger;
use Throwable;

/**
 * Operações sobre tarefas, todas em JSON.
 *
 * O quadro Kanban conversa com estes pontos de entrada através de `fetch`;
 * o token CSRF é verificado pelo encaminhador antes de qualquer método aqui.
 */
final class TaskController extends Controller
{
    /**
     * Detalhe completo de uma tarefa: campos, etiquetas, tempo e histórico.
     */
    public function mostrar(string $id): void
    {
        $tarefa = Task::porId((int) $id);

        if ($tarefa === null) {
            Response::erroJson('Tarefa não encontrada.', 404);
        }

        $registos = TaskTimeLog::daTarefa((int) $id);

        $this->json([
            'tarefa'    => $this->apresentar($tarefa),
            'tags'      => Task::tagsDe((int) $id),
            'tempo'     => array_map(
                static fn (array $r): array => $r + ['duracao' => Semana::minutosParaTexto((int) $r['minutos'])],
                $registos
            ),
            'total_minutos' => TaskTimeLog::totalDaTarefa((int) $id),
            'historico' => TaskActivity::daTarefa((int) $id),
            'podeEliminar' => Auth::adminOuProprio(
                $tarefa['criado_por'] === null ? null : (int) $tarefa['criado_por']
            ),
        ]);
    }

    /**
     * Cria uma tarefa e devolve-a já pronta a desenhar no quadro.
     */
    public function criar(): void
    {
        $dados     = Request::json() ?: Request::todosPost();
        $validador = $this->validar($dados);

        if ($validador->falhou()) {
            Response::erroJson(
                (string) $validador->primeiroErro(),
                422,
                $validador->erros()
            );
        }

        $colunaId = (int) ($dados['column_id'] ?? 0);
        $coluna   = BoardColumn::porId($colunaId);

        if ($coluna === null) {
            Response::erroJson('A coluna indicada não existe.', 422);
        }

        Database::iniciarTransacao();

        try {
            $concluida = (int) $coluna['is_concluida'] === 1;

            $taskId = Task::criar([
                'titulo'         => (string) $dados['titulo'],
                'descricao'      => $this->texto($dados, 'descricao'),
                'column_id'      => $colunaId,
                'project_id'     => $this->id($dados, 'project_id'),
                'assignee_id'    => $this->id($dados, 'assignee_id'),
                'criado_por'     => Auth::id(),
                'prioridade'     => (string) ($dados['prioridade'] ?? 'media'),
                'data_inicio'    => $this->texto($dados, 'data_inicio'),
                // Uma tarefa criada já na coluna terminal fica concluída hoje.
                'data_conclusao' => $concluida ? Semana::agora()->format('Y-m-d') : null,
                'estimativa_min' => $this->id($dados, 'estimativa_min'),
                'dificuldades'   => $this->texto($dados, 'dificuldades'),
            ]);

            Task::sincronizarTags($taskId, $this->tagIds($dados));

            TaskActivity::registar(
                $taskId,
                Auth::id(),
                TaskActivity::CRIACAO,
                'Tarefa criada em ' . $coluna['nome'] . '.',
                null,
                $colunaId
            );

            Database::confirmar();
        } catch (Throwable $e) {
            Database::anular();

            throw $e;
        }

        $criada = Task::porId($taskId);
        AuditLogger::criado('tarefa', $taskId, $criada ?? []);

        $this->json([
            'mensagem' => 'Tarefa criada.',
            'tarefa'   => $this->comTags($criada),
        ], 201);
    }

    /**
     * Atualiza os campos de uma tarefa.
     */
    public function atualizar(string $id): void
    {
        $taskId = (int) $id;
        $antes  = Task::porId($taskId);

        if ($antes === null) {
            Response::erroJson('Tarefa não encontrada.', 404);
        }

        $dados     = Request::json() ?: Request::todosPost();
        $validador = $this->validar($dados);

        if ($validador->falhou()) {
            Response::erroJson((string) $validador->primeiroErro(), 422, $validador->erros());
        }

        Database::iniciarTransacao();

        try {
            Task::atualizar($taskId, [
                'titulo'         => (string) $dados['titulo'],
                'descricao'      => $this->texto($dados, 'descricao'),
                'project_id'     => $this->id($dados, 'project_id'),
                'assignee_id'    => $this->id($dados, 'assignee_id'),
                'prioridade'     => (string) ($dados['prioridade'] ?? 'media'),
                'data_inicio'    => $this->texto($dados, 'data_inicio'),
                'estimativa_min' => $this->id($dados, 'estimativa_min'),
                'dificuldades'   => $this->texto($dados, 'dificuldades'),
            ]);

            Task::sincronizarTags($taskId, $this->tagIds($dados));

            TaskActivity::registar($taskId, Auth::id(), TaskActivity::EDICAO, 'Tarefa editada.');

            Database::confirmar();
        } catch (Throwable $e) {
            Database::anular();

            throw $e;
        }

        $depois = Task::porId($taskId);
        AuditLogger::atualizado('tarefa', $taskId, $antes, $depois ?? []);

        $this->json([
            'mensagem' => 'Tarefa atualizada.',
            'tarefa'   => $this->comTags($depois),
        ]);
    }

    /**
     * Move uma tarefa entre colunas e reordena a coluna de destino.
     *
     * O cliente envia a sequência completa de identificadores da coluna de
     * destino; o servidor só aplica a ordem às tarefas que lá estão de facto.
     */
    public function mover(string $id): void
    {
        $taskId = (int) $id;
        $tarefa = Task::porId($taskId);

        if ($tarefa === null) {
            Response::erroJson('Tarefa não encontrada.', 404);
        }

        $dados    = Request::json();
        $destinoId = (int) ($dados['column_id'] ?? 0);
        $destino   = BoardColumn::porId($destinoId);

        if ($destino === null) {
            Response::erroJson('A coluna de destino não existe.', 422);
        }

        $origemId = (int) $tarefa['column_id'];
        $ordem    = is_array($dados['ordem'] ?? null) ? array_map('intval', $dados['ordem']) : [];

        Database::iniciarTransacao();

        try {
            $eraConcluida = BoardColumn::eConcluida($origemId);
            $eConcluida   = (int) $destino['is_concluida'] === 1;

            $atualizacao = ['column_id' => $destinoId];

            if ($eConcluida && $tarefa['data_conclusao'] === null) {
                // Entrou na coluna terminal: fecha-se a tarefa com a data de hoje.
                $atualizacao['data_conclusao'] = Semana::agora()->format('Y-m-d');
            } elseif (!$eConcluida && $eraConcluida) {
                // Saiu da coluna terminal: a tarefa deixa de estar concluída.
                $atualizacao['data_conclusao'] = null;
            }

            Task::atualizar($taskId, $atualizacao);

            // A tarefa já está na coluna nova, por isso a reordenação apanha-a.
            Task::reordenarColuna($destinoId, $ordem !== [] ? $ordem : Task::idsDaColuna($destinoId));

            if ($origemId !== $destinoId) {
                Task::reordenarColuna($origemId, Task::idsDaColuna($origemId));

                TaskActivity::registar(
                    $taskId,
                    Auth::id(),
                    $eConcluida ? TaskActivity::CONCLUSAO : TaskActivity::MOVIMENTO,
                    sprintf('Movida de %s para %s.', $tarefa['coluna_nome'] ?? '—', $destino['nome']),
                    $origemId,
                    $destinoId
                );
            }

            Database::confirmar();
        } catch (Throwable $e) {
            Database::anular();

            throw $e;
        }

        if ($origemId !== $destinoId) {
            AuditLogger::registar('tarefa', $taskId, AuditLogger::MOVER, [
                'de'   => $tarefa['coluna_nome'] ?? null,
                'para' => $destino['nome'],
            ]);
        }

        $this->json([
            'mensagem' => 'Tarefa movida.',
            'tarefa'   => $this->comTags(Task::porId($taskId)),
        ]);
    }

    /**
     * Elimina uma tarefa. Reservado ao administrador e a quem a criou.
     */
    public function eliminar(string $id): void
    {
        $taskId = (int) $id;
        $tarefa = Task::porId($taskId);

        if ($tarefa === null) {
            Response::erroJson('Tarefa não encontrada.', 404);
        }

        if (!Auth::adminOuProprio($tarefa['criado_por'] === null ? null : (int) $tarefa['criado_por'])) {
            Response::erroJson('Só o administrador ou quem criou a tarefa a pode eliminar.', 403);
        }

        $colunaId = (int) $tarefa['column_id'];

        Task::eliminar($taskId);
        Task::reordenarColuna($colunaId, Task::idsDaColuna($colunaId));

        AuditLogger::eliminado('tarefa', $taskId, $tarefa);

        $this->json(['mensagem' => 'Tarefa eliminada.']);
    }

    /**
     * Regista tempo dedicado a uma tarefa.
     *
     * Cada utilizador regista o seu próprio tempo: o autor do registo é
     * sempre quem está autenticado, nunca um identificador vindo do cliente.
     */
    public function registarTempo(string $id): void
    {
        $taskId = (int) $id;

        if (Task::porId($taskId) === null) {
            Response::erroJson('Tarefa não encontrada.', 404);
        }

        $dados   = Request::json();
        $duracao = TaskTimeLog::interpretarDuracao((string) ($dados['duracao'] ?? ''));
        $data    = (string) ($dados['data'] ?? Semana::agora()->format('Y-m-d'));

        $validador = Validator::para(['data' => $data])
            ->rotulo('data', 'data')
            ->obrigatorio('data')
            ->data('data')
            ->regra(
                'duracao',
                $duracao !== null && $duracao > 0,
                'Indique uma duração válida (ex.: 90, 1h30, 45m).'
            )
            ->regra(
                'duracao',
                $duracao === null || $duracao <= 1440,
                'A duração não pode exceder 24 horas num só registo.'
            );

        if ($validador->falhou()) {
            Response::erroJson((string) $validador->primeiroErro(), 422, $validador->erros());
        }

        $logId = TaskTimeLog::criar(
            $taskId,
            (int) Auth::id(),
            $data,
            (int) $duracao,
            $this->texto($dados, 'nota')
        );

        TaskActivity::registar(
            $taskId,
            Auth::id(),
            TaskActivity::COMENTARIO,
            sprintf('Registou %s em %s.', Semana::minutosParaTexto((int) $duracao), date('d/m/Y', strtotime($data)))
        );

        AuditLogger::criado('registo_tempo', $logId, [
            'task_id' => $taskId,
            'data'    => $data,
            'minutos' => $duracao,
        ]);

        $this->json([
            'mensagem'      => 'Tempo registado.',
            'total_minutos' => TaskTimeLog::totalDaTarefa($taskId),
            'tempo'         => array_map(
                static fn (array $r): array => $r + ['duracao' => Semana::minutosParaTexto((int) $r['minutos'])],
                TaskTimeLog::daTarefa($taskId)
            ),
        ], 201);
    }

    /**
     * Elimina um registo de tempo. Só o autor do registo ou um administrador.
     */
    public function eliminarTempo(string $id): void
    {
        $registo = TaskTimeLog::porId((int) $id);

        if ($registo === null) {
            Response::erroJson('Registo de tempo não encontrado.', 404);
        }

        if (!Auth::adminOuProprio((int) $registo['user_id'])) {
            Response::erroJson('Só pode eliminar os seus próprios registos de tempo.', 403);
        }

        TaskTimeLog::eliminar((int) $id);
        AuditLogger::eliminado('registo_tempo', (int) $id, $registo);

        $taskId = (int) $registo['task_id'];

        $this->json([
            'mensagem'      => 'Registo eliminado.',
            'total_minutos' => TaskTimeLog::totalDaTarefa($taskId),
            'tempo'         => array_map(
                static fn (array $r): array => $r + ['duracao' => Semana::minutosParaTexto((int) $r['minutos'])],
                TaskTimeLog::daTarefa($taskId)
            ),
        ]);
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Regras de validação partilhadas pela criação e pela edição.
     *
     * @param array<string, mixed> $dados
     */
    private function validar(array $dados): Validator
    {
        $validador = Validator::para($dados)
            ->rotulos([
                'titulo'         => 'título',
                'descricao'      => 'descrição',
                'prioridade'     => 'prioridade',
                'data_inicio'    => 'data de início',
                'estimativa_min' => 'estimativa',
                'dificuldades'   => 'dificuldades',
            ])
            ->obrigatorio('titulo')
            ->maximo('titulo', 200)
            ->em('prioridade', Task::PRIORIDADES)
            ->data('data_inicio')
            ->inteiro('estimativa_min', 0, 100000);

        $projetoId = $this->id($dados, 'project_id');
        $validador->regra(
            'project_id',
            $projetoId === null || Project::existeAtivo($projetoId),
            'O projeto indicado não existe ou está arquivado.'
        );

        return $validador;
    }

    /**
     * Identificadores de etiquetas recebidos do cliente, já normalizados.
     *
     * @param array<string, mixed> $dados
     * @return list<int>
     */
    private function tagIds(array $dados): array
    {
        $tags = $dados['tags'] ?? [];

        if (!is_array($tags)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $tags),
            static fn (int $id): bool => $id > 0
        ));
    }

    /**
     * Lê um campo de texto, devolvendo null quando está vazio.
     *
     * @param array<string, mixed> $dados
     */
    private function texto(array $dados, string $campo): ?string
    {
        $valor = $dados[$campo] ?? null;

        if (!is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * Lê um campo numérico opcional, devolvendo null quando vazio ou inválido.
     *
     * @param array<string, mixed> $dados
     */
    private function id(array $dados, string $campo): ?int
    {
        $valor = $dados[$campo] ?? null;

        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        $numero = (int) $valor;

        return $numero > 0 ? $numero : null;
    }

    /**
     * Normaliza uma tarefa para resposta JSON.
     *
     * @param array<string, mixed> $tarefa
     * @return array<string, mixed>
     */
    private function apresentar(array $tarefa): array
    {
        $tarefa['tempo_formatado'] = Semana::minutosParaTexto(
            $tarefa['estimativa_min'] === null ? null : (int) $tarefa['estimativa_min']
        );

        return $tarefa;
    }

    /**
     * Tarefa com as etiquetas incluídas, pronta a redesenhar o cartão.
     *
     * @param array<string, mixed>|null $tarefa
     * @return array<string, mixed>|null
     */
    private function comTags(?array $tarefa): ?array
    {
        if ($tarefa === null) {
            return null;
        }

        $tarefa['tags']          = Task::tagsDe((int) $tarefa['id']);
        $tarefa['total_minutos'] = TaskTimeLog::totalDaTarefa((int) $tarefa['id']);

        return $tarefa;
    }
}
