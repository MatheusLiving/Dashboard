<?php

/**
 * Página de acesso negado.
 */

App\Core\View::titulo('Acesso negado');
?>
<div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
    <p class="text-4xl font-bold text-rose-600">403</p>
    <h2 class="mt-3 text-lg font-semibold text-slate-900">Não tem permissões para esta página</h2>
    <p class="mt-2 text-sm text-slate-500">
        Esta área está reservada a administradores. Se precisar de acesso, contacte o administrador do sistema.
    </p>
    <a href="/" class="mt-6 inline-block rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
        Voltar ao painel
    </a>
</div>
