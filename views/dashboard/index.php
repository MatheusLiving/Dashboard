<?php

/**
 * Painel inicial: resumo da semana ISO corrente.
 *
 * @var array{ano: int, semana: int}                                  $semana
 * @var string                                                        $rotulo
 * @var array{atribuidas: int, concluidas: int, minutos: int, incidentes: int} $resumo
 * @var list<array<string, mixed>>                                    $porColuna
 * @var list<array<string, mixed>>                                    $atividade
 * @var array<string, mixed>|null                                     $utilizadorAtual
 */

use App\Core\Semana;
use App\Core\View;

View::titulo('Painel');

$cartoes = [
    ['rotulo' => 'Tarefas em aberto',   'valor' => (string) $resumo['atribuidas'], 'nota' => 'atribuídas a si'],
    ['rotulo' => 'Concluídas',          'valor' => (string) $resumo['concluidas'], 'nota' => 'nesta semana'],
    ['rotulo' => 'Tempo registado',     'valor' => Semana::minutosParaTexto($resumo['minutos']), 'nota' => 'nesta semana'],
    ['rotulo' => 'Incidentes/Suporte',  'valor' => (string) $resumo['incidentes'], 'nota' => 'no total'],
];

$rotulosAtividade = [
    'criacao'    => 'Criação',
    'movimento'  => 'Movimento',
    'comentario' => 'Comentário',
    'edicao'     => 'Edição',
    'conclusao'  => 'Conclusão',
];
?>
<div class="space-y-6">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">
                Olá, <?= View::e(explode(' ', (string) $utilizadorAtual['nome'])[0]) ?>
            </h2>
            <p class="text-sm text-slate-500">Semana <?= View::e($rotulo) ?></p>
        </div>
        <span class="rounded-full bg-marinho-50 px-3 py-1 text-xs font-medium text-marinho-800">
            Semana ISO <?= View::e(Semana::rotuloCurto($semana['ano'], $semana['semana'])) ?>
        </span>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($cartoes as $cartao): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= View::e($cartao['rotulo']) ?></p>
                <p class="mt-2 text-2xl font-semibold text-slate-900"><?= View::e($cartao['valor']) ?></p>
                <p class="mt-1 text-xs text-slate-400"><?= View::e($cartao['nota']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">

        <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                As suas tarefas por coluna
            </h3>

            <?php if ($porColuna === []): ?>
                <p class="text-sm text-slate-500">O quadro ainda não tem colunas configuradas.</p>
            <?php else: ?>
                <ul class="space-y-2.5">
                    <?php foreach ($porColuna as $coluna): ?>
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                      style="background-color: <?= View::e($coluna['cor']) ?>"></span>
                                <span class="truncate text-sm text-slate-700"><?= View::e($coluna['nome']) ?></span>
                            </span>
                            <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                <?= (int) $coluna['total'] ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-3">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Atividade recente
            </h3>

            <?php if ($atividade === []): ?>
                <div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center">
                    <p class="text-sm font-medium text-slate-600">Ainda não há atividade registada</p>
                    <p class="mt-1 text-xs text-slate-400">
                        Os movimentos das suas tarefas no quadro aparecerão aqui.
                    </p>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($atividade as $linha): ?>
                        <li class="flex items-start gap-3 py-2.5">
                            <span class="mt-0.5 shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                <?= View::e($rotulosAtividade[$linha['tipo']] ?? $linha['tipo']) ?>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-slate-800"><?= View::e($linha['titulo']) ?></span>
                                <?php if (!empty($linha['descricao'])): ?>
                                    <span class="block truncate text-xs text-slate-500"><?= View::e($linha['descricao']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="shrink-0 text-xs text-slate-400">
                                <?= View::e(date('d/m H:i', strtotime((string) $linha['created_at']))) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-5">
        <p class="text-sm font-medium text-slate-700">Fase 1 concluída</p>
        <p class="mt-1 text-xs text-slate-500">
            O quadro Kanban, os projetos e o gerador de relatórios entram nas fases seguintes.
        </p>
    </div>
</div>
