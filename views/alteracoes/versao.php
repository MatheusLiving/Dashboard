<?php

/**
 * Uma versão guardada do Relatório de Alteração de Software.
 *
 * O conteúdo vem da cópia gravada em `change_report_versions`, não da tabela
 * do relatório: é assim que se lê o documento tal como estava naquele
 * momento, mesmo depois de ter mudado.
 *
 * @var array<string, mixed>  $versao
 * @var array<string, mixed>  $relatorio
 * @var array<string, string> $rotulosEstado
 * @var array<string, string> $coresEstado
 * @var array<string, string> $rotulosTipo
 * @var array<string, string> $rotulosPrioridade
 * @var array<string, string> $rotulosMotivo
 */

use App\Core\View;

View::titulo(sprintf('%s — v%d', (string) ($versao['referencia'] ?? ''), (int) $versao['versao']));

$conteudo   = is_array($versao['conteudo'] ?? null) ? $versao['conteudo'] : [];
$atividades = is_array($conteudo['atividades'] ?? null) ? $conteudo['atividades'] : [];
$estado     = (string) ($versao['estado'] ?? '');
$atual      = (int) $versao['versao'] === (int) $relatorio['versao'];
?>
<div class="mx-auto max-w-4xl space-y-5">

    <nav class="text-xs text-slate-400">
        <a href="/alteracoes" class="hover:text-marinho-800">Alterações</a>
        <span class="mx-1">/</span>
        <a href="/alteracoes/<?= (int) $relatorio['id'] ?>" class="font-mono hover:text-marinho-800">
            <?= View::e($relatorio['referencia'] ?? '') ?>
        </a>
        <span class="mx-1">/</span>
        <span class="text-slate-600">v<?= (int) $versao['versao'] ?></span>
    </nav>

    <div class="rounded-xl border border-slate-300 bg-slate-50 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded bg-slate-800 px-2.5 py-1 text-xs font-semibold text-white">
                Versão <?= (int) $versao['versao'] ?><?= $atual ? ' (atual)' : '' ?>
            </span>
            <span class="rounded px-2.5 py-1 text-xs font-semibold <?= View::e($coresEstado[$estado] ?? '') ?>">
                <?= View::e($rotulosEstado[$estado] ?? $estado) ?>
            </span>
            <span class="text-xs text-slate-500">
                <?= View::e($rotulosMotivo[$versao['motivo']] ?? $versao['motivo']) ?>
                · <?= View::e(date('d/m/Y H:i', strtotime((string) $versao['created_at']))) ?>
                · <?= View::e($versao['autor'] ?? '—') ?>
            </span>

            <a href="/alteracoes/<?= (int) $relatorio['id'] ?>"
               class="ml-auto rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                Voltar ao relatório atual
            </a>
        </div>

        <?php if (!empty($versao['nota'])): ?>
            <p class="mt-3 whitespace-pre-line rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <?= View::nl($versao['nota']) ?>
            </p>
        <?php endif; ?>

        <?php if (!$atual): ?>
            <p class="mt-3 text-xs text-slate-500">
                Esta é uma versão do histórico, em modo de leitura. O relatório atual pode já
                dizer outra coisa — foi de propósito que esta ficou guardada.
            </p>
        <?php endif; ?>
    </div>

    <?= View::parcial('partials/documento-alteracao', [
        'dados'             => $conteudo,
        'atividades'        => $atividades,
        'rotulosTipo'       => $rotulosTipo,
        'rotulosPrioridade' => $rotulosPrioridade,
    ]) ?>
</div>
