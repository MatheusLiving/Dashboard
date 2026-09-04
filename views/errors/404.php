<?php

/**
 * Página de recurso inexistente.
 */

App\Core\View::titulo('Página não encontrada');
?>
<div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
    <p class="text-4xl font-bold text-marinho-800">404</p>
    <h2 class="mt-3 text-lg font-semibold text-slate-900">Página não encontrada</h2>
    <p class="mt-2 text-sm text-slate-500">
        O endereço pedido não existe ou foi movido.
    </p>
    <a href="/" class="mt-6 inline-block rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
        Voltar ao painel
    </a>
</div>
