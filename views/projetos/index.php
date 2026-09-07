<?php

/**
 * Listagem de projetos, com filtros e formulário de criação.
 *
 * @var list<array<string, mixed>> $projetos
 * @var array<string, mixed>       $filtros
 * @var bool                       $temFiltros
 * @var list<array<string, mixed>> $utilizadores
 * @var array<string, string>      $estados
 * @var array<string, string>      $coresEstado
 * @var array<string, string>      $prioridades
 * @var array<string, mixed>       $antigos
 */

use App\Controllers\ProjectController;
use App\Core\Csrf;
use App\Core\Semana;
use App\Core\View;
use App\Models\Project;
use App\Models\User;

View::titulo('Projetos');

$classeCampo = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="space-y-4">

    <form method="get" action="/projetos" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <input type="search" name="procura" value="<?= View::e($filtros['procura'] ?? '') ?>"
               placeholder="Procurar projeto…" class="<?= $classeCampo ?> min-w-[12rem] flex-1">

        <select name="status" class="<?= $classeCampo ?>">
            <option value="">Todos os estados</option>
            <?php foreach ($estados as $chave => $rotulo): ?>
                <option value="<?= View::e($chave) ?>" <?= ($filtros['status'] ?? '') === $chave ? 'selected' : '' ?>>
                    <?= View::e($rotulo) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="responsavel" class="<?= $classeCampo ?>">
            <option value="">Todos os responsáveis</option>
            <?php foreach ($utilizadores as $utilizador): ?>
                <option value="<?= (int) $utilizador['id'] ?>"
                    <?= (int) ($filtros['responsavel'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                    <?= View::e($utilizador['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="flex items-center gap-1.5 text-sm text-slate-600">
            <input type="checkbox" name="arquivados" value="1" <?= !empty($filtros['arquivados']) ? 'checked' : '' ?>
                   class="rounded border-slate-300 text-marinho-800 focus:ring-marinho-100">
            Incluir arquivados
        </label>

        <button type="submit" class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-marinho-700">
            Filtrar
        </button>

        <?php if ($temFiltros): ?>
            <a href="/projetos" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        <?php endif; ?>

        <button type="button" data-abrir-novo-projeto
                class="ml-auto rounded-lg border border-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-marinho-800 hover:bg-marinho-50">
            + Novo projeto
        </button>
    </form>

    <!-- Formulário de criação, escondido até ser pedido -->
    <section data-painel-novo-projeto class="hidden rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-marinho-800">Novo projeto</h2>

        <form method="post" action="/projetos" class="space-y-4" novalidate>
            <?= Csrf::campo() ?>
            <?= View::parcial('partials/campos-projeto', [
                'valores'      => $antigos,
                'utilizadores' => $utilizadores,
                'estados'      => $estados,
                'prioridades'  => $prioridades,
                'sufixo'       => '_novo',
            ]) ?>

            <div class="flex justify-end gap-2">
                <button type="button" data-fechar-novo-projeto
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="submit" class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    Criar projeto
                </button>
            </div>
        </form>
    </section>

    <?php if ($projetos === []): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="text-sm font-medium text-slate-600">
                <?= $temFiltros ? 'Nenhum projeto corresponde aos filtros' : 'Ainda não há projetos' ?>
            </p>
            <p class="mt-1 text-xs text-slate-400">
                <?= $temFiltros
                    ? 'Ajuste os filtros ou limpe-os para ver todos.'
                    : 'Crie o primeiro projeto para começar a agrupar as tarefas do departamento.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($projetos as $projeto): ?>
                <?php
                $calculado = Project::progressoCalculado($projeto);
                $declarado = (int) $projeto['progresso_pct'];
                $atraso    = ProjectController::emAtraso($projeto['prazo'], (string) $projeto['status']);
                $arquivado = (int) $projeto['arquivado'] === 1;
                ?>
                <a href="/projetos/<?= (int) $projeto['id'] ?>"
                   class="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-marinho-300 hover:shadow<?= $arquivado ? ' opacity-60' : '' ?>">

                    <div class="mb-2 flex items-start justify-between gap-2">
                        <h3 class="min-w-0 flex-1 text-sm font-semibold leading-snug text-slate-900">
                            <?= View::e($projeto['nome']) ?>
                        </h3>
                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                              style="background-color: <?= View::e($coresEstado[$projeto['status']] ?? '#94A3B8') ?>">
                            <?= View::e($estados[$projeto['status']] ?? $projeto['status']) ?>
                        </span>
                    </div>

                    <?php if (!empty($projeto['descricao'])): ?>
                        <p class="mb-3 line-clamp-2 text-xs leading-relaxed text-slate-500">
                            <?= View::e(mb_strimwidth((string) $projeto['descricao'], 0, 120, '…')) ?>
                        </p>
                    <?php endif; ?>

                    <div class="mb-3 space-y-2">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-[11px]">
                                <span class="text-slate-500">Declarado</span>
                                <span class="font-mono font-semibold text-slate-700"><?= $declarado ?>%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-marinho-800" style="width: <?= $declarado ?>%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-[11px]">
                                <span class="text-slate-500">
                                    Tarefas concluídas
                                    <span class="text-slate-400">
                                        (<?= (int) $projeto['tarefas_concluidas'] ?>/<?= (int) $projeto['total_tarefas'] ?>)
                                    </span>
                                </span>
                                <span class="font-mono font-semibold text-slate-700"><?= $calculado ?>%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-500" style="width: <?= $calculado ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 text-xs">
                        <?php if (!empty($projeto['responsavel_nome'])): ?>
                            <span class="flex items-center gap-1.5 text-slate-500">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-marinho-100 text-[9px] font-semibold text-marinho-800">
                                    <?= View::e(User::iniciais((string) $projeto['responsavel_nome'])) ?>
                                </span>
                                <span class="truncate"><?= View::e($projeto['responsavel_nome']) ?></span>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-400">Sem responsável</span>
                        <?php endif; ?>

                        <?php if (!empty($projeto['prazo'])): ?>
                            <span class="shrink-0 <?= $atraso ? 'font-semibold text-rose-600' : 'text-slate-400' ?>"
                                  title="<?= $atraso ? 'Prazo ultrapassado' : 'Prazo' ?>">
                                <?= View::e(date('d/m/Y', strtotime((string) $projeto['prazo']))) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($arquivado): ?>
                        <p class="mt-3 rounded bg-slate-100 px-2 py-1 text-center text-[10px] font-medium text-slate-500">
                            arquivado
                        </p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        var painel = document.querySelector('[data-painel-novo-projeto]');
        var abrir = document.querySelector('[data-abrir-novo-projeto]');
        var fechar = document.querySelector('[data-fechar-novo-projeto]');

        if (!painel || !abrir) {
            return;
        }

        abrir.addEventListener('click', function () {
            painel.classList.toggle('hidden');

            if (!painel.classList.contains('hidden')) {
                painel.querySelector('input[name="nome"]').focus();
            }
        });

        if (fechar) {
            fechar.addEventListener('click', function () {
                painel.classList.add('hidden');
            });
        }

        // Mostra o valor do progresso enquanto se arrasta o cursor.
        document.querySelectorAll('[data-slider-progresso]').forEach(function (slider) {
            var alvo = slider.closest('div').querySelector('[data-valor-progresso]');

            slider.addEventListener('input', function () {
                if (alvo) {
                    alvo.textContent = slider.value + '%';
                }
            });
        });
    }());
</script>
