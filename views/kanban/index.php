<?php

/**
 * Quadro Kanban: barra de filtros, colunas e cartões arrastáveis.
 *
 * @var list<array<string, mixed>>              $colunas
 * @var array<int, list<array<string, mixed>>>  $porColuna
 * @var array<string, mixed>                    $filtros
 * @var bool                                    $temFiltros
 * @var list<array<string, mixed>>              $utilizadores
 * @var list<array<string, mixed>>              $projetos
 * @var list<array<string, mixed>>              $etiquetas
 * @var array<string, string>                   $prioridades
 * @var array<string, string>                   $cores
 * @var int                                     $totalCartoes
 */

use App\Core\View;

View::titulo('Quadro');

$classeCampo = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="space-y-4">

    <form method="get" action="/kanban" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">

        <input type="search" name="procura" value="<?= View::e($filtros['procura'] ?? '') ?>"
               placeholder="Procurar tarefa…"
               class="<?= $classeCampo ?> min-w-[12rem] flex-1">

        <select name="responsavel" class="<?= $classeCampo ?>">
            <option value="">Todos os responsáveis</option>
            <?php foreach ($utilizadores as $utilizador): ?>
                <option value="<?= (int) $utilizador['id'] ?>"
                    <?= (int) ($filtros['responsavel'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                    <?= View::e($utilizador['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="tag" class="<?= $classeCampo ?>">
            <option value="">Todas as etiquetas</option>
            <?php foreach ($etiquetas as $etiqueta): ?>
                <option value="<?= (int) $etiqueta['id'] ?>"
                    <?= (int) ($filtros['tag'] ?? 0) === (int) $etiqueta['id'] ? 'selected' : '' ?>>
                    <?= View::e($etiqueta['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="projeto" class="<?= $classeCampo ?>">
            <option value="">Todos os projetos</option>
            <?php foreach ($projetos as $projeto): ?>
                <option value="<?= (int) $projeto['id'] ?>"
                    <?= (int) ($filtros['projeto'] ?? 0) === (int) $projeto['id'] ? 'selected' : '' ?>>
                    <?= View::e($projeto['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="prioridade" class="<?= $classeCampo ?>">
            <option value="">Todas as prioridades</option>
            <?php foreach ($prioridades as $chave => $rotulo): ?>
                <option value="<?= View::e($chave) ?>" <?= ($filtros['prioridade'] ?? '') === $chave ? 'selected' : '' ?>>
                    <?= View::e($rotulo) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit"
                class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-marinho-700">
            Filtrar
        </button>

        <?php if ($temFiltros): ?>
            <a href="/kanban" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        <?php endif; ?>

        <span class="ml-auto text-xs text-slate-400">
            <?= (int) $totalCartoes ?> tarefa<?= $totalCartoes === 1 ? '' : 's' ?>
        </span>
    </form>

    <div class="flex gap-4 overflow-x-auto pb-4 scroll-fino">
        <?php foreach ($colunas as $coluna): ?>
            <?php $cartoes = $porColuna[(int) $coluna['id']] ?? []; ?>

            <section class="flex w-72 shrink-0 flex-col rounded-xl border border-slate-200 bg-slate-50">

                <header class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2.5">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: <?= View::e($coluna['cor']) ?>"></span>
                        <span class="truncate text-sm font-semibold text-slate-700"><?= View::e($coluna['nome']) ?></span>
                        <?php if ((int) $coluna['is_concluida'] === 1): ?>
                            <span class="shrink-0 text-[10px] text-slate-400" title="Ao receber uma tarefa, preenche a data de conclusão">
                                terminal
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="shrink-0 rounded-md bg-white px-1.5 py-0.5 text-xs font-semibold text-slate-500"
                          data-contador-coluna="<?= (int) $coluna['id'] ?>">
                        <?= count($cartoes) ?>
                    </span>
                </header>

                <div class="flex-1 space-y-2 overflow-y-auto p-2 scroll-fino"
                     style="min-height: 8rem; max-height: calc(100vh - 20rem)"
                     data-coluna="<?= (int) $coluna['id'] ?>">

                    <?php foreach ($cartoes as $tarefa): ?>
                        <?= View::parcial('partials/cartao', ['tarefa' => $tarefa, 'cores' => $cores]) ?>
                    <?php endforeach; ?>

                    <?php if ($cartoes === []): ?>
                        <p class="px-2 py-6 text-center text-xs text-slate-400" data-coluna-vazia>
                            <?= $temFiltros ? 'Nenhuma tarefa corresponde aos filtros.' : 'Sem tarefas nesta coluna.' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <button type="button"
                        class="border-t border-slate-200 px-3 py-2 text-left text-xs font-medium text-slate-500 hover:bg-white hover:text-marinho-800"
                        data-nova-tarefa="<?= (int) $coluna['id'] ?>">
                    + Adicionar tarefa
                </button>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<?= View::parcial('partials/modal-tarefa', [
    'utilizadores' => $utilizadores,
    'projetos'     => $projetos,
    'etiquetas'    => $etiquetas,
    'prioridades'  => $prioridades,
]) ?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
    window.KANBAN = {
        cores: <?= json_encode($cores, JSON_UNESCAPED_UNICODE) ?>,
        etiquetas: <?= json_encode($etiquetas, JSON_UNESCAPED_UNICODE) ?>,
        colunasConcluidas: <?= json_encode(
            array_values(array_map(
                static fn (array $c): int => (int) $c['id'],
                array_filter($colunas, static fn (array $c): bool => (int) $c['is_concluida'] === 1)
            ))
        ) ?>
    };
</script>
<script src="/assets/js/kanban.js"></script>
