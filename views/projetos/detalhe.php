<?php

/**
 * Detalhe de um projeto: edição, números e tarefas associadas.
 *
 * @var array<string, mixed>       $projeto
 * @var list<array<string, mixed>> $tarefas
 * @var array{total: int, concluidas: int, minutos: int, progresso_calculado: int} $estatisticas
 * @var list<array<string, mixed>> $utilizadores
 * @var array<string, string>      $estados
 * @var array<string, string>      $coresEstado
 * @var array<string, string>      $prioridades
 * @var bool                       $podeArquivar
 * @var array<string, mixed>       $antigos
 */

use App\Controllers\ProjectController;
use App\Core\Csrf;
use App\Core\Semana;
use App\Core\View;
use App\Models\Task;

View::titulo((string) $projeto['nome']);

$declarado = (int) $projeto['progresso_pct'];
$calculado = $estatisticas['progresso_calculado'];
$desvio    = $declarado - $calculado;
$atraso    = ProjectController::emAtraso($projeto['prazo'], (string) $projeto['status']);
$arquivado = (int) $projeto['arquivado'] === 1;
$cores     = Task::coresPrioridade();

// Agrupa as tarefas pela coluna do quadro, mantendo a ordem do fluxo.
$porColuna = [];
foreach ($tarefas as $tarefa) {
    $porColuna[(string) $tarefa['coluna_nome']][] = $tarefa;
}
?>
<div class="space-y-5">

    <nav class="text-xs text-slate-400">
        <a href="/projetos" class="hover:text-marinho-800">Projetos</a>
        <span class="mx-1">/</span>
        <span class="text-slate-600"><?= View::e($projeto['nome']) ?></span>
    </nav>

    <?php if ($arquivado): ?>
        <div class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-2.5 text-sm text-slate-600">
            Este projeto está arquivado: não aparece nos seletores de tarefa, mas as tarefas
            já associadas mantêm-se ligadas a ele.
        </div>
    <?php endif; ?>

    <!-- Números -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Progresso declarado</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900"><?= $declarado ?>%</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-marinho-800" style="width: <?= $declarado ?>%"></div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Progresso calculado</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900"><?= $calculado ?>%</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width: <?= $calculado ?>%"></div>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">
                <?= $estatisticas['concluidas'] ?> de <?= $estatisticas['total'] ?> tarefas concluídas
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Desvio</p>
            <p class="mt-2 text-2xl font-semibold <?= abs($desvio) >= 20 ? 'text-amber-600' : 'text-slate-900' ?>">
                <?= $desvio > 0 ? '+' : '' ?><?= $desvio ?> p.p.
            </p>
            <p class="mt-1 text-xs text-slate-400">
                <?php if ($estatisticas['total'] === 0): ?>
                    Sem tarefas para comparar
                <?php elseif ($desvio > 0): ?>
                    Declarado acima das tarefas fechadas
                <?php elseif ($desvio < 0): ?>
                    Tarefas fechadas acima do declarado
                <?php else: ?>
                    Declarado e calculado coincidem
                <?php endif; ?>
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Tempo dedicado</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">
                <?= View::e(Semana::minutosParaTexto($estatisticas['minutos'])) ?>
            </p>
            <p class="mt-1 text-xs text-slate-400">soma de todos os registos</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">

        <!-- Edição -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-marinho-800">Dados do projeto</h2>

            <form method="post" action="/projetos/<?= (int) $projeto['id'] ?>" class="space-y-4" novalidate>
                <?= Csrf::campo() ?>
                <?= View::parcial('partials/campos-projeto', [
                    'valores'      => $antigos === [] ? $projeto : array_merge($projeto, $antigos),
                    'utilizadores' => $utilizadores,
                    'estados'      => $estados,
                    'prioridades'  => $prioridades,
                    'sufixo'       => '_edit',
                ]) ?>

                <button type="submit"
                        class="w-full rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    Guardar alterações
                </button>
            </form>

            <?php if ($podeArquivar): ?>
                <form method="post" action="/projetos/<?= (int) $projeto['id'] ?>/arquivar" class="mt-3"
                      onsubmit="return confirm('<?= $arquivado ? 'Repor este projeto?' : 'Arquivar este projeto?' ?>');">
                    <?= Csrf::campo() ?>
                    <button type="submit"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        <?= $arquivado ? 'Repor projeto' : 'Arquivar projeto' ?>
                    </button>
                </form>
            <?php else: ?>
                <p class="mt-3 text-center text-[11px] text-slate-400">
                    Só o administrador ou o responsável pode arquivar este projeto.
                </p>
            <?php endif; ?>
        </section>

        <!-- Tarefas -->
        <section class="rounded-xl border border-slate-200 bg-white lg:col-span-3">
            <header class="flex items-center justify-between gap-2 border-b border-slate-200 px-6 py-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">
                    Tarefas associadas
                </h2>
                <span class="text-xs text-slate-400"><?= count($tarefas) ?></span>
            </header>

            <?php if ($tarefas === []): ?>
                <div class="px-6 py-16 text-center">
                    <p class="text-sm font-medium text-slate-600">Ainda não há tarefas neste projeto</p>
                    <p class="mt-1 text-xs text-slate-400">
                        No quadro, escolha este projeto ao criar ou editar uma tarefa.
                    </p>
                    <a href="/kanban" class="mt-4 inline-block rounded-lg border border-slate-300 px-3.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        Ir para o quadro
                    </a>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($porColuna as $nomeColuna => $lista): ?>
                        <div class="px-6 py-3">
                            <p class="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                                <span class="h-2 w-2 rounded-full" style="background-color: <?= View::e($lista[0]['coluna_cor']) ?>"></span>
                                <?= View::e($nomeColuna) ?>
                                <span class="text-slate-400">(<?= count($lista) ?>)</span>
                            </p>

                            <ul class="space-y-1">
                                <?php foreach ($lista as $tarefa): ?>
                                    <li class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full"
                                              style="background-color: <?= View::e($cores[$tarefa['prioridade']] ?? '#94A3B8') ?>"
                                              title="Prioridade: <?= View::e($tarefa['prioridade']) ?>"></span>

                                        <span class="min-w-0 flex-1 truncate text-sm <?= (int) $tarefa['is_concluida'] === 1 ? 'text-slate-400 line-through' : 'text-slate-700' ?>">
                                            <?= View::e($tarefa['titulo']) ?>
                                        </span>

                                        <?php if (!empty($tarefa['responsavel_nome'])): ?>
                                            <span class="shrink-0 text-[11px] text-slate-400">
                                                <?= View::e(explode(' ', (string) $tarefa['responsavel_nome'])[0]) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ((int) $tarefa['total_minutos'] > 0): ?>
                                            <span class="shrink-0 font-mono text-[11px] text-slate-400">
                                                <?= View::e(Semana::minutosParaTexto((int) $tarefa['total_minutos'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
    // Mostra o valor do progresso enquanto se arrasta o cursor.
    document.querySelectorAll('[data-slider-progresso]').forEach(function (slider) {
        var alvo = slider.closest('div').querySelector('[data-valor-progresso]');

        slider.addEventListener('input', function () {
            if (alvo) {
                alvo.textContent = slider.value + '%';
            }
        });
    });
</script>
