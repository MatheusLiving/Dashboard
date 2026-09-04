<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Tag;
use App\Services\AuditLogger;

/**
 * Gestão de etiquetas.
 *
 * A página de gestão é reservada a administradores; a criação rápida a partir
 * do modal da tarefa está disponível a qualquer utilizador autenticado, porque
 * é ali que as etiquetas nascem no dia a dia.
 */
final class TagController extends Controller
{
    /**
     * Página de gestão, com contagem de utilizações.
     */
    public function index(): void
    {
        $this->ver('tags/index', [
            'etiquetas' => Tag::comContagem(),
            'paleta'    => Tag::paleta(),
            'antigos'   => Flash::antigos(),
        ]);
    }

    /**
     * Cria uma etiqueta a partir do formulário da página de gestão.
     */
    public function criar(): void
    {
        $nome = (string) Request::post('nome', '');
        $cor  = (string) Request::post('cor_hex', '#1F3864');

        $validador = $this->validar($nome, $cor);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/tags');
        }

        $id = Tag::criar($nome, $cor);
        AuditLogger::criado('etiqueta', $id, ['nome' => $nome, 'cor_hex' => $cor]);

        Flash::sucesso('Etiqueta «' . $nome . '» criada.');
        $this->redirecionar('/tags');
    }

    /**
     * Atualiza o nome e a cor de uma etiqueta.
     */
    public function atualizar(string $id): void
    {
        $tagId = (int) $id;
        $antes = Tag::porId($tagId);

        if ($antes === null) {
            Flash::erro('Etiqueta não encontrada.');
            $this->redirecionar('/tags');
        }

        $nome = (string) Request::post('nome', '');
        $cor  = (string) Request::post('cor_hex', '#1F3864');

        $validador = $this->validar($nome, $cor, $tagId);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/tags');
        }

        Tag::atualizar($tagId, ['nome' => $nome, 'cor_hex' => $cor]);

        AuditLogger::atualizado('etiqueta', $tagId, $antes, Tag::porId($tagId) ?? []);

        Flash::sucesso('Etiqueta atualizada.');
        $this->redirecionar('/tags');
    }

    /**
     * Ativa ou desativa uma etiqueta.
     *
     * Uma etiqueta desativada desaparece dos seletores mas continua visível
     * nas tarefas antigas — por isso nunca se perde informação histórica.
     */
    public function alternar(string $id): void
    {
        $tagId    = (int) $id;
        $etiqueta = Tag::porId($tagId);

        if ($etiqueta === null) {
            Flash::erro('Etiqueta não encontrada.');
            $this->redirecionar('/tags');
        }

        $ativar = (int) $etiqueta['ativa'] === 0;
        Tag::definirAtiva($tagId, $ativar);

        AuditLogger::registar(
            'etiqueta',
            $tagId,
            $ativar ? AuditLogger::ATIVAR : AuditLogger::DESATIVAR,
            ['nome' => $etiqueta['nome']]
        );

        Flash::sucesso(sprintf(
            'Etiqueta «%s» %s.',
            $etiqueta['nome'],
            $ativar ? 'reativada' : 'desativada'
        ));

        $this->redirecionar('/tags');
    }

    /**
     * Elimina definitivamente uma etiqueta que nenhuma tarefa use.
     */
    public function eliminar(string $id): void
    {
        $tagId    = (int) $id;
        $etiqueta = Tag::porId($tagId);

        if ($etiqueta === null) {
            Flash::erro('Etiqueta não encontrada.');
            $this->redirecionar('/tags');
        }

        $utilizacoes = Tag::totalTarefas($tagId);

        if ($utilizacoes > 0) {
            Flash::erro(sprintf(
                'A etiqueta «%s» está em %d tarefa(s) e não pode ser eliminada. Desative-a.',
                $etiqueta['nome'],
                $utilizacoes
            ));

            $this->redirecionar('/tags');
        }

        Tag::eliminar($tagId);
        AuditLogger::eliminado('etiqueta', $tagId, $etiqueta);

        Flash::sucesso('Etiqueta eliminada.');
        $this->redirecionar('/tags');
    }

    // -----------------------------------------------------------------------
    // Pontos de entrada usados pelo modal da tarefa
    // -----------------------------------------------------------------------

    /**
     * Lista de etiquetas ativas, para o campo com sugestões automáticas.
     */
    public function apiListar(): void
    {
        $this->json(['etiquetas' => Tag::todas()]);
    }

    /**
     * Cria uma etiqueta sem sair do modal da tarefa.
     *
     * Quando o nome já existe, devolve a etiqueta existente em vez de falhar:
     * do ponto de vista de quem escreve o nome, o resultado é o mesmo.
     */
    public function apiCriar(): void
    {
        $dados = Request::json();
        $nome  = trim((string) ($dados['nome'] ?? ''));
        $cor   = (string) ($dados['cor_hex'] ?? '#1F3864');

        if ($nome === '') {
            Response::erroJson('Indique o nome da etiqueta.', 422);
        }

        if (mb_strlen($nome) > 60) {
            Response::erroJson('O nome da etiqueta não pode exceder 60 caracteres.', 422);
        }

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $cor) !== 1) {
            Response::erroJson('A cor indicada não é válida.', 422);
        }

        $existente = Tag::porSlug(Tag::slug($nome));

        if ($existente !== null) {
            // Se estava desativada, a criação reativa-a: foi isso que o
            // utilizador pediu ao escrever o nome outra vez.
            if ((int) $existente['ativa'] === 0) {
                Tag::definirAtiva((int) $existente['id'], true);
                $existente['ativa'] = 1;
            }

            $this->json([
                'mensagem' => 'Etiqueta já existente.',
                'etiqueta' => $existente,
                'nova'     => false,
            ]);
        }

        $id = Tag::criar($nome, $cor);
        AuditLogger::criado('etiqueta', $id, ['nome' => $nome, 'cor_hex' => $cor]);

        $this->json([
            'mensagem' => 'Etiqueta criada.',
            'etiqueta' => Tag::porId($id),
            'nova'     => true,
        ], 201);
    }

    /**
     * Regras comuns à criação e à edição.
     */
    private function validar(string $nome, string $cor, ?int $exceto = null): Validator
    {
        return Validator::para(['nome' => $nome, 'cor_hex' => $cor])
            ->rotulos(['nome' => 'nome', 'cor_hex' => 'cor'])
            ->obrigatorio('nome')
            ->maximo('nome', 60)
            ->obrigatorio('cor_hex')
            ->corHex('cor_hex')
            ->regra(
                'nome',
                $nome === '' || !Tag::slugExiste(Tag::slug($nome), $exceto),
                'Já existe uma etiqueta com esse nome.'
            );
    }
}
