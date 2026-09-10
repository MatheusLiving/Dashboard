<?php

/**
 * Formulário do Relatório de Alteração de Software.
 *
 * Segue a ordem exata do documento: identificação, secções 1 a 5.
 *
 * @var array<string, mixed>       $relatorio
 * @var list<array<string, mixed>> $atividades
 * @var array<string, string>      $rotulosTipo
 * @var array<string, string>      $rotulosPrioridade
 * @var array<string, mixed>       $antigos
 */

use App\Core\Csrf;
use App\Core\View;

View::titulo('Editar ' . ($relatorio['referencia'] ?? 'relatório de alteração'));

/** Valor de um campo: o que ficou por gravar ganha ao que está em base de dados. */
$valor = static function (string $campo) use ($relatorio, $antigos): string {
    return (string) ($antigos[$campo] ?? $relatorio[$campo] ?? '');
};

$classeTexto = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';

/** Uma caixa de texto livre com título, como no documento. */
$caixa = static function (string $titulo, string $campo, int $linhas = 3, string $ajuda = '')
    use ($valor, $classeTexto): void {
    ?>
    <div>
        <label for="<?= View::e($campo) ?>" class="mb-1 block text-sm font-medium text-slate-700">
            <?= View::e($titulo) ?>
        </label>
        <?php if ($ajuda !== ''): ?>
            <p class="mb-1 text-[11px] text-slate-400"><?= View::e($ajuda) ?></p>
        <?php endif; ?>
        <textarea id="<?= View::e($campo) ?>" name="<?= View::e($campo) ?>" rows="<?= $linhas ?>"
                  maxlength="5000" class="<?= $classeTexto ?>"><?= View::e($valor($campo)) ?></textarea>
    </div>
    <?php
};
?>
<form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>" class="mx-auto max-w-4xl space-y-5">
    <?= Csrf::campo() ?>

    <nav class="text-xs text-slate-400">
        <a href="/alteracoes" class="hover:text-marinho-800">Alterações</a>
        <span class="mx-1">/</span>
        <a href="/alteracoes/<?= (int) $relatorio['id'] ?>" class="hover:text-marinho-800">
            <?= View::e($relatorio['referencia'] ?? '') ?>
        </a>
        <span class="mx-1">/</span>
        <span class="text-slate-600">Editar</span>
    </nav>

    <!-- Identificação -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-marinho-800">Identificação</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Data de Abertura</span>
                <input type="date" name="data_abertura" required value="<?= View::e($valor('data_abertura')) ?>"
                       class="<?= $classeTexto ?>">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Data Prevista de Entrega</span>
                <input type="date" name="data_prevista" value="<?= View::e($valor('data_prevista')) ?>"
                       class="<?= $classeTexto ?>">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Solicitado por (Programador)</span>
                <input type="text" name="solicitado_por" maxlength="200" value="<?= View::e($valor('solicitado_por')) ?>"
                       class="<?= $classeTexto ?>">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Cliente / Projeto</span>
                <input type="text" name="cliente_projeto" maxlength="200" value="<?= View::e($valor('cliente_projeto')) ?>"
                       class="<?= $classeTexto ?>">
            </label>

            <label class="block sm:col-span-2">
                <span class="mb-1 block text-sm font-medium text-slate-700">Sistema / Aplicação Afetada</span>
                <input type="text" name="sistema_afetado" maxlength="200" value="<?= View::e($valor('sistema_afetado')) ?>"
                       class="<?= $classeTexto ?>">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Tipo de Alteração</span>
                <select name="tipo" class="<?= $classeTexto ?>">
                    <?php foreach ($rotulosTipo as $chave => $rotulo): ?>
                        <option value="<?= View::e($chave) ?>" <?= $valor('tipo') === $chave ? 'selected' : '' ?>>
                            <?= View::e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">Prioridade</span>
                <select name="prioridade" class="<?= $classeTexto ?>">
                    <?php foreach ($rotulosPrioridade as $chave => $rotulo): ?>
                        <option value="<?= View::e($chave) ?>" <?= $valor('prioridade') === $chave ? 'selected' : '' ?>>
                            <?= View::e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <!-- Secção 1 -->
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">
            1. Descrição do pedido
        </h2>
        <p class="-mt-2 text-xs text-slate-500">
            A preencher antes de iniciar qualquer alteração.
        </p>

        <?php $caixa('1.1 Descrição do problema / necessidade', 'desc_problema', 4); ?>
        <?php $caixa('1.2 Objetivo da alteração', 'objetivo', 3); ?>
        <?php $caixa('1.3 Âmbito da alteração', 'ambito', 3, 'O que será e o que não será alterado.'); ?>
        <?php $caixa('1.4 Módulos / ficheiros / sistemas afetados', 'modulos', 3); ?>
        <?php $caixa('1.5 Análise de impacto e riscos', 'impacto_riscos', 3, 'Dados, integrações, outros utilizadores, desempenho.'); ?>
        <?php $caixa('1.6 Estimativa de esforço e plano de testes previsto', 'estimativa', 3); ?>
        <?php $caixa('1.7 Programador(es) responsável(eis)', 'programadores', 2); ?>
    </section>

    <!-- Secção 2 -->
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">
            2. Acompanhamento do desenvolvimento
        </h2>
        <p class="-mt-2 text-xs text-slate-500">A atualizar ao longo do trabalho.</p>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-sm" data-tabela-acompanhamento>
                <thead>
                    <tr class="bg-marinho-800 text-left text-xs font-semibold text-white">
                        <th class="w-36 px-3 py-2">Data</th>
                        <th class="w-40 px-3 py-2">Estado</th>
                        <th class="px-3 py-2">Descrição da atividade / progresso</th>
                        <th class="w-44 px-3 py-2">Responsável</th>
                        <th class="w-10 px-2 py-2"><span class="sr-only">Remover</span></th>
                    </tr>
                </thead>
                <tbody data-linhas>
                    <?php foreach ($atividades as $atividade): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-2 py-1.5">
                                <input type="date" name="ac_data[]" value="<?= View::e($atividade['data'] ?? '') ?>"
                                       class="<?= $classeTexto ?>">
                            </td>
                            <td class="px-2 py-1.5">
                                <input type="text" name="ac_estado[]" maxlength="80"
                                       value="<?= View::e($atividade['estado'] ?? '') ?>" class="<?= $classeTexto ?>">
                            </td>
                            <td class="px-2 py-1.5">
                                <input type="text" name="ac_descricao[]" maxlength="500"
                                       value="<?= View::e($atividade['descricao'] ?? '') ?>" class="<?= $classeTexto ?>">
                            </td>
                            <td class="px-2 py-1.5">
                                <input type="text" name="ac_responsavel[]" maxlength="120"
                                       value="<?= View::e($atividade['responsavel'] ?? '') ?>" class="<?= $classeTexto ?>">
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <button type="button" data-remover-linha
                                        class="text-slate-300 hover:text-rose-600" title="Remover linha">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="button" data-adicionar-linha
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
            + Acrescentar linha
        </button>

        <?php $caixa('2.1 Desvios em relação ao pedido inicial e respetiva justificação', 'desvios', 3); ?>
    </section>

    <!-- Secção 3 -->
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">3. Testes e validação</h2>

        <?php $caixa('3.1 Testes realizados', 'testes_realizados', 3); ?>
        <?php $caixa('3.2 Resultados obtidos / problemas encontrados e correções efetuadas', 'resultados', 3); ?>
        <?php $caixa('3.3 Validado por', 'validado_por', 2); ?>
    </section>

    <!-- Secção 4 -->
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">
            4. Relatório final de execução
        </h2>
        <p class="-mt-2 text-xs text-slate-500">A preencher antes da entrega ao cliente.</p>

        <?php $caixa('4.1 Resumo do que foi efetivamente executado', 'resumo_execucao', 4); ?>
        <?php $caixa('4.2 Diferenças em relação ao pedido inicial e respetiva justificação', 'diferencas', 3); ?>
        <?php $caixa('4.3 Notas / instruções para o cliente', 'notas_cliente', 3); ?>
        <?php $caixa('4.4 Identificação do(s) commit(s) no Bitbucket', 'commits', 3, 'Repositório, branch, hash/link do commit ou pull request.'); ?>
        <?php $caixa('4.5 Data de conclusão e programador(es) envolvido(s)', 'conclusao_programadores', 2); ?>
    </section>

    <!-- Secção 5 -->
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">5. Entrega</h2>
        <?php $caixa('Entregue ao cliente em (data) / por', 'entrega', 2); ?>
    </section>

    <!-- Ações -->
    <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-6 py-4 shadow-lg">
        <p class="text-xs text-slate-500">
            Cada gravação que mude alguma coisa fica no histórico. O que já foi aprovado não se perde.
        </p>

        <div class="flex gap-2">
            <a href="/alteracoes/<?= (int) $relatorio['id'] ?>"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                Cancelar
            </a>
            <button type="submit"
                    class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                Gravar
            </button>
        </div>
    </div>
</form>

<!-- Linha-modelo da tabela de acompanhamento, clonada pelo JavaScript. -->
<template data-modelo-linha>
    <tr class="border-b border-slate-100">
        <td class="px-2 py-1.5"><input type="date" name="ac_data[]" class="<?= $classeTexto ?>"></td>
        <td class="px-2 py-1.5"><input type="text" name="ac_estado[]" maxlength="80" class="<?= $classeTexto ?>"></td>
        <td class="px-2 py-1.5"><input type="text" name="ac_descricao[]" maxlength="500" class="<?= $classeTexto ?>"></td>
        <td class="px-2 py-1.5"><input type="text" name="ac_responsavel[]" maxlength="120" class="<?= $classeTexto ?>"></td>
        <td class="px-2 py-1.5 text-center">
            <button type="button" data-remover-linha class="text-slate-300 hover:text-rose-600" title="Remover linha">&times;</button>
        </td>
    </tr>
</template>

<script>
    // Acrescentar e remover linhas da tabela de acompanhamento.
    (function () {
        var corpo = document.querySelector('[data-tabela-acompanhamento] [data-linhas]');
        var modelo = document.querySelector('[data-modelo-linha]');
        var adicionar = document.querySelector('[data-adicionar-linha]');

        if (!corpo || !modelo || !adicionar) {
            return;
        }

        function novaLinha() {
            var linha = modelo.content.cloneNode(true);
            corpo.appendChild(linha);
            var campos = corpo.lastElementChild.querySelectorAll('input');
            if (campos.length > 2) {
                campos[2].focus();
            }
        }

        adicionar.addEventListener('click', novaLinha);

        // Uma tabela sem linhas nenhumas não tem onde escrever.
        if (corpo.children.length === 0) {
            novaLinha();
        }

        corpo.addEventListener('click', function (evento) {
            var botao = evento.target.closest('[data-remover-linha]');

            if (botao) {
                botao.closest('tr').remove();
            }
        });
    }());
</script>
