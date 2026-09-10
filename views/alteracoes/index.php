<?php

/**
 * Listagem dos Relatórios de Alteração de Software.
 *
 * @var list<array<string, mixed>> $relatorios
 * @var array<string, mixed>       $filtros
 * @var bool                       $temFiltros
 * @var array<string, int>         $totais
 * @var list<array<string, mixed>> $utilizadores
 * @var list<array<string, mixed>> $projetos
 * @var array<string, string>      $rotulosEstado
 * @var array<string, string>      $coresEstado
 * @var array<string, string>      $rotulosTipo
 * @var array<string, mixed>       $antigos
 */

use App\Core\Csrf;
use App\Core\View;
use App\Models\ChangeReport;

View::titulo('Alterações de software');

$classeCampo = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="space-y-4">

    <!-- Resumo por estado -->
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($rotulosEstado as $chave => $rotulo): ?>
            <a href="/alteracoes?estado=<?= View::e($chave) ?>"
               class="rounded-xl border bg-white p-4 transition hover:border-marinho-600
                      <?= ($filtros['estado'] ?? null) === $chave ? 'border-marinho-600' : 'border-slate-200' ?>">
                <p class="text-2xl font-bold text-slate-800"><?= (int) ($totais[$chave] ?? 0) ?></p>
                <p class="mt-0.5 text-xs font-medium text-slate-500"><?= View::e($rotulo) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <form method="get" action="/alteracoes" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <input type="text" name="procura" placeholder="Referência, cliente ou descrição"
               value="<?= View::e($filtros['procura'] ?? '') ?>" class="<?= $classeCampo ?> min-w-[14rem] flex-1">

        <select name="estado" class="<?= $classeCampo ?>">
            <option value="">Todos os estados</option>
            <?php foreach ($rotulosEstado as $chave => $rotulo): ?>
                <option value="<?= View::e($chave) ?>" <?= ($filtros['estado'] ?? '') === $chave ? 'selected' : '' ?>>
                    <?= View::e($rotulo) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="tipo" class="<?= $classeCampo ?>">
            <option value="">Todos os tipos</option>
            <?php foreach ($rotulosTipo as $chave => $rotulo): ?>
                <option value="<?= View::e($chave) ?>" <?= ($filtros['tipo'] ?? '') === $chave ? 'selected' : '' ?>>
                    <?= View::e($rotulo) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="utilizador" class="<?= $classeCampo ?>">
            <option value="">Todos os autores</option>
            <?php foreach ($utilizadores as $utilizador): ?>
                <option value="<?= (int) $utilizador['id'] ?>"
                    <?= (int) ($filtros['user_id'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                    <?= View::e($utilizador['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-marinho-700">
            Filtrar
        </button>

        <?php if ($temFiltros): ?>
            <a href="/alteracoes" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        <?php endif; ?>
    </form>

    <!-- Abertura manual -->
    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">
            Abrir relatório a pedido
        </h2>
        <p class="mb-3 text-xs text-slate-500">
            Os relatórios abrem-se sozinhos com os projetos novos e com as tarefas de desenvolvimento
            ou ajuda técnica. Use isto para o trabalho que as etiquetas não apanharam.
        </p>

        <form method="post" action="/alteracoes/abrir" class="flex flex-wrap items-end gap-2">
            <?= Csrf::campo() ?>

            <label class="block min-w-[16rem] flex-1">
                <span class="mb-1 block text-[11px] text-slate-500">Projeto</span>
                <select name="project_id" class="<?= $classeCampo ?> w-full">
                    <option value="">— escolher —</option>
                    <?php foreach ($projetos as $projeto): ?>
                        <option value="<?= (int) $projeto['id'] ?>"><?= View::e($projeto['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] text-slate-500">ou identificador da tarefa</span>
                <input type="number" name="task_id" min="1" placeholder="Nº" class="<?= $classeCampo ?> w-28">
            </label>

            <button type="submit"
                    class="rounded-lg border border-marinho-800 px-3.5 py-2 text-sm font-semibold text-marinho-800 hover:bg-marinho-50">
                + Abrir relatório
            </button>
        </form>
    </section>

    <?php if ($relatorios === []): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="text-sm font-medium text-slate-600">
                <?= $temFiltros ? 'Nenhum relatório corresponde aos filtros' : 'Ainda não há relatórios de alteração' ?>
            </p>
            <p class="mt-1 text-xs text-slate-400">
                <?= $temFiltros
                    ? 'Ajuste os filtros ou limpe-os para ver todos.'
                    : 'O próximo projeto ou tarefa de desenvolvimento abre um automaticamente.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Referência</th>
                            <th class="px-5 py-3">Cliente / Projeto</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3">Versão</th>
                            <th class="px-5 py-3">Autor</th>
                            <th class="px-5 py-3">Atualizado</th>
                            <th class="px-5 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($relatorios as $relatorio): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <a href="/alteracoes/<?= (int) $relatorio['id'] ?>"
                                       class="font-mono text-sm font-medium text-marinho-800 underline-offset-2 hover:underline">
                                        <?= View::e($relatorio['referencia'] ?? ('#' . (int) $relatorio['id'])) ?>
                                    </a>
                                    <span class="block text-[11px] text-slate-400">
                                        <?= View::e(match ($relatorio['origem']) {
                                            'projeto' => 'do projeto',
                                            'tarefa'  => 'da tarefa',
                                            default   => 'aberto a pedido',
                                        }) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-700">
                                    <?= View::e($relatorio['cliente_projeto'] ?: '—') ?>
                                    <?php if (!empty($relatorio['tarefa_titulo'])): ?>
                                        <span class="block truncate text-[11px] text-slate-400">
                                            <?= View::e($relatorio['tarefa_titulo']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    <?= View::e($rotulosTipo[$relatorio['tipo']] ?? $relatorio['tipo']) ?>
                                </td>

                                <td class="px-5 py-3">
                                    <span class="rounded px-2 py-0.5 text-[11px] font-semibold
                                                 <?= View::e($coresEstado[$relatorio['estado']] ?? 'bg-slate-100 text-slate-600') ?>">
                                        <?= View::e($rotulosEstado[$relatorio['estado']] ?? $relatorio['estado']) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    v<?= (int) $relatorio['versao'] ?>
                                    <span class="text-[11px] text-slate-400">
                                        (<?= (int) $relatorio['total_versoes'] ?> no histórico)
                                    </span>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    <?= View::e($relatorio['autor'] ?? '—') ?>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    <?= View::e(date('d/m/Y H:i', strtotime((string) $relatorio['updated_at']))) ?>
                                </td>

                                <td class="px-5 py-3 text-right">
                                    <a href="/alteracoes/<?= (int) $relatorio['id'] ?>"
                                       class="text-sm font-medium text-marinho-800 underline-offset-2 hover:underline">
                                        Ver
                                    </a>
                                    <?php if ($relatorio['estado'] !== ChangeReport::APROVADO): ?>
                                        <a href="/alteracoes/<?= (int) $relatorio['id'] ?>/editar"
                                           class="ml-3 text-sm font-medium text-slate-500 underline-offset-2 hover:underline">
                                            Editar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
