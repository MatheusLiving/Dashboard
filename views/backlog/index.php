<?php

/**
 * Visão de backlog: tarefas por entrar no fluxo, agrupadas por projeto,
 * arrastáveis diretamente para as colunas ativas do quadro.
 *
 * @var list<array{id: ?int, nome: string, status: ?string, tarefas: list<array<string, mixed>>}> $grupos
 * @var list<array<string, mixed>>  $colunas       Colunas de destino
 * @var array<string, mixed>|null   $colunaOrigem
 * @var array<string, mixed>        $filtros
 * @var bool                        $temFiltros
 * @var list<array<string, mixed>>  $utilizadores
 * @var array<string, string>       $prioridades
 * @var array<string, string>       $cores
 * @var int                         $total
 */

use App\Core\Semana;
use App\Core\View;
use App\Models\User;

View::titulo('Backlog');

$classeCampo = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="space-y-4">

    <form method="get" action="/backlog" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <input type="search" name="procura" value="<?= View::e($filtros['procura'] ?? '') ?>"
               placeholder="Procurar no backlog…" class="<?= $classeCampo ?> min-w-[12rem] flex-1">

        <select name="responsavel" class="<?= $classeCampo ?>">
            <option value="">Todos os responsáveis</option>
            <?php foreach ($utilizadores as $utilizador): ?>
                <option value="<?= (int) $utilizador['id'] ?>"
                    <?= (int) ($filtros['responsavel'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                    <?= View::e($utilizador['nome']) ?>
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

        <button type="submit" class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-marinho-700">
            Filtrar
        </button>

        <?php if ($temFiltros): ?>
            <a href="/backlog" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        <?php endif; ?>

        <span class="ml-auto text-xs text-slate-400">
            <?= (int) $total ?> tarefa<?= $total === 1 ? '' : 's' ?> em
            <strong class="font-medium text-slate-500"><?= View::e($colunaOrigem['nome'] ?? '—') ?></strong>
        </span>
    </form>

    <?php if ($colunaOrigem === null): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="text-sm font-medium text-slate-600">O quadro não tem colunas configuradas</p>
            <p class="mt-1 text-xs text-slate-400">Execute os seeds ou crie as colunas do quadro.</p>
        </div>
    <?php else: ?>

        <div class="grid gap-4 lg:grid-cols-3">

            <!-- Tarefas do backlog, agrupadas por projeto -->
            <div class="space-y-4 lg:col-span-2">
                <?php if ($grupos === []): ?>
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <p class="text-sm font-medium text-slate-600">
                            <?= $temFiltros ? 'Nenhuma tarefa corresponde aos filtros' : 'O backlog está vazio' ?>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            <?= $temFiltros
                                ? 'Ajuste os filtros para ver mais tarefas.'
                                : 'Tudo o que estava por planear já entrou no fluxo de trabalho.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grupos as $grupo): ?>
                        <section class="rounded-xl border border-slate-200 bg-white">
                            <header class="flex items-center justify-between gap-2 border-b border-slate-200 px-4 py-2.5">
                                <?php if ($grupo['id'] === null): ?>
                                    <span class="text-sm font-semibold text-slate-500"><?= View::e($grupo['nome']) ?></span>
                                <?php else: ?>
                                    <a href="/projetos/<?= (int) $grupo['id'] ?>"
                                       class="truncate text-sm font-semibold text-slate-800 hover:text-marinho-800">
                                        <?= View::e($grupo['nome']) ?>
                                    </a>
                                <?php endif; ?>
                                <span class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-500">
                                    <?= count($grupo['tarefas']) ?>
                                </span>
                            </header>

                            <ul class="space-y-1.5 p-2" data-lista-backlog>
                                <?php foreach ($grupo['tarefas'] as $tarefa): ?>
                                    <li class="flex cursor-grab items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 hover:border-marinho-300 active:cursor-grabbing"
                                        data-tarefa-backlog
                                        data-id="<?= (int) $tarefa['id'] ?>">

                                        <span class="h-2 w-2 shrink-0 rounded-full"
                                              style="background-color: <?= View::e($cores[$tarefa['prioridade']] ?? '#94A3B8') ?>"
                                              title="Prioridade: <?= View::e($tarefa['prioridade']) ?>"></span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm text-slate-800"><?= View::e($tarefa['titulo']) ?></span>

                                            <?php if (!empty($tarefa['tags'])): ?>
                                                <span class="mt-1 flex flex-wrap gap-1">
                                                    <?php foreach ($tarefa['tags'] as $etiqueta): ?>
                                                        <span class="rounded px-1 py-0.5 text-[9px] font-semibold leading-tight text-white"
                                                              style="background-color: <?= View::e($etiqueta['cor_hex']) ?>">
                                                            <?= View::e($etiqueta['nome']) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>

                                        <?php if (!empty($tarefa['responsavel_nome'])): ?>
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-marinho-100 text-[10px] font-semibold text-marinho-800"
                                                  title="<?= View::e($tarefa['responsavel_nome']) ?>">
                                                <?= View::e(User::iniciais((string) $tarefa['responsavel_nome'])) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ((int) $tarefa['estimativa_min'] > 0): ?>
                                            <span class="shrink-0 font-mono text-[11px] text-slate-400"
                                                  title="Estimativa">
                                                <?= View::e(Semana::minutosParaTexto((int) $tarefa['estimativa_min'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Colunas de destino -->
            <div class="lg:col-span-1">
                <div class="sticky top-20 space-y-2">
                    <p class="px-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                        Arraste para o quadro
                    </p>

                    <?php foreach ($colunas as $coluna): ?>
                        <div class="rounded-xl border-2 border-dashed border-slate-200 bg-white p-3 transition"
                             data-destino-coluna="<?= (int) $coluna['id'] ?>">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: <?= View::e($coluna['cor']) ?>"></span>
                                <span class="text-sm font-medium text-slate-700"><?= View::e($coluna['nome']) ?></span>
                                <?php if ((int) $coluna['is_concluida'] === 1): ?>
                                    <span class="ml-auto text-[10px] text-slate-400" title="Preenche a data de conclusão">
                                        terminal
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">Largue aqui para mover</p>
                        </div>
                    <?php endforeach; ?>

                    <a href="/kanban" class="block rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-medium text-slate-600 hover:bg-white">
                        Abrir o quadro
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script src="/assets/js/backlog.js"></script>
