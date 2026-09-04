<?php

/**
 * Formulário de início de sessão.
 *
 * @var array<string, mixed> $antigos
 * @var string               $nomeApp
 */

use App\Core\Csrf;
use App\Core\View;

View::titulo('Iniciar sessão');
?>
<div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">

    <div class="mb-8 text-center">
        <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-marinho-800 text-base font-bold text-white">TI</span>
        <h1 class="text-xl font-semibold text-slate-900">Relatório Semanal de Atividade</h1>
        <p class="mt-1 text-sm text-slate-500"><?= View::e($nomeApp) ?></p>
    </div>

    <form method="post" action="/login" class="space-y-4" novalidate>
        <?= Csrf::campo() ?>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">
                Endereço de correio
            </label>
            <input type="email"
                   id="email"
                   name="email"
                   value="<?= View::e($antigos['email'] ?? '') ?>"
                   autocomplete="username"
                   required
                   autofocus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition
                          focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100"
                   placeholder="nome@ti.local">
        </div>

        <div>
            <label for="senha" class="mb-1.5 block text-sm font-medium text-slate-700">
                Palavra-passe
            </label>
            <input type="password"
                   id="senha"
                   name="senha"
                   autocomplete="current-password"
                   required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition
                          focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-marinho-800 px-4 py-2.5 text-sm font-semibold text-white transition
                       hover:bg-marinho-700 focus:outline-none focus:ring-2 focus:ring-marinho-300">
            Entrar
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-slate-400">
        Em caso de dificuldade no acesso, contacte o administrador do sistema.
    </p>
</div>
