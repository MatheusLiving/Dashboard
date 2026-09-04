<?php

/**
 * Página de erro interno.
 *
 * O detalhe da exceção só é apresentado em modo de depuração; em produção
 * fica apenas no registo de erros.
 *
 * @var Throwable|null $excecao
 */

use App\Core\View;

View::titulo('Erro interno');
$excecao = $excecao ?? null;
?>
<div class="mx-auto max-w-3xl space-y-6">

    <div class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
        <p class="text-4xl font-bold text-amber-600">500</p>
        <h2 class="mt-3 text-lg font-semibold text-slate-900">Ocorreu um erro interno</h2>
        <p class="mt-2 text-sm text-slate-500">
            O problema foi registado. Tente novamente; se persistir, contacte o administrador do sistema.
        </p>
        <a href="/" class="mt-6 inline-block rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
            Voltar ao painel
        </a>
    </div>

    <?php if ($excecao !== null): ?>
        <div class="overflow-hidden rounded-xl border border-rose-200 bg-rose-50">
            <p class="border-b border-rose-200 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-rose-700">
                Detalhe (apenas em modo de depuração)
            </p>
            <div class="space-y-3 px-5 py-4 text-sm">
                <p class="font-medium text-rose-900"><?= View::e($excecao->getMessage()) ?></p>
                <p class="text-xs text-rose-700">
                    <?= View::e($excecao->getFile()) ?>:<?= (int) $excecao->getLine() ?>
                </p>
                <pre class="max-h-80 overflow-auto rounded-lg bg-white p-3 text-[11px] leading-relaxed text-slate-600"><?= View::e($excecao->getTraceAsString()) ?></pre>
            </div>
        </div>
    <?php endif; ?>
</div>
