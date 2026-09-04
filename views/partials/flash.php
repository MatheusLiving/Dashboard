<?php

/**
 * Mensagens de retorno ao utilizador, de leitura única.
 */

use App\Core\Flash;
use App\Core\View;

$mensagens = Flash::consumir();

if ($mensagens === []) {
    return;
}

$estilos = [
    'sucesso' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'erro'    => 'border-rose-200 bg-rose-50 text-rose-800',
    'aviso'   => 'border-amber-200 bg-amber-50 text-amber-800',
    'info'    => 'border-sky-200 bg-sky-50 text-sky-800',
];
?>
<div class="mb-5 space-y-2">
    <?php foreach ($mensagens as $mensagem): ?>
        <div class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm <?= $estilos[$mensagem['tipo']] ?? $estilos['info'] ?>"
             role="status">
            <span><?= View::e($mensagem['texto']) ?></span>
            <button type="button" class="shrink-0 opacity-60 hover:opacity-100" data-fechar-flash aria-label="Fechar">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    <?php endforeach; ?>
</div>
