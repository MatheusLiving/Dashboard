<?php

/**
 * Perfil do utilizador autenticado: dados pessoais e alteração de palavra-passe.
 *
 * @var array<string, mixed> $utilizador
 * @var array<string, mixed> $antigos
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

View::titulo('O meu perfil');
?>
<div class="mx-auto grid max-w-4xl gap-6 lg:grid-cols-2">

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Dados pessoais</h2>
        <p class="mb-5 text-xs text-slate-500">
            Estes dados preenchem os campos «Colaborador» e «Função / Cargo» do relatório.
        </p>

        <form method="post" action="/perfil" class="space-y-4" novalidate>
            <?= Csrf::campo() ?>

            <div>
                <label for="nome" class="mb-1.5 block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" id="nome" name="nome" required maxlength="150"
                       value="<?= View::e($antigos['nome'] ?? $utilizador['nome']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>

            <div>
                <label for="email_perfil" class="mb-1.5 block text-sm font-medium text-slate-700">Endereço de correio</label>
                <input type="email" id="email_perfil" name="email" required maxlength="190"
                       value="<?= View::e($antigos['email'] ?? $utilizador['email']) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>

            <div>
                <label for="funcao_cargo" class="mb-1.5 block text-sm font-medium text-slate-700">Função / Cargo</label>
                <input type="text" id="funcao_cargo" name="funcao_cargo" maxlength="150"
                       value="<?= View::e($antigos['funcao_cargo'] ?? $utilizador['funcao_cargo']) ?>"
                       placeholder="ex.: Técnico de Infraestrutura"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>

            <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                Papel: <strong class="text-slate-700"><?= $utilizador['papel'] === 'admin' ? 'Administrador' : 'Membro' ?></strong>
                — apenas um administrador o pode alterar.
            </div>

            <button type="submit"
                    class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                Guardar alterações
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Palavra-passe</h2>
        <p class="mb-5 text-xs text-slate-500">
            Mínimo de <?= Auth::MIN_SENHA ?> caracteres.
        </p>

        <form method="post" action="/perfil/senha" class="space-y-4" novalidate>
            <?= Csrf::campo() ?>

            <div>
                <label for="senha_atual" class="mb-1.5 block text-sm font-medium text-slate-700">Palavra-passe atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>

            <div>
                <label for="senha_nova" class="mb-1.5 block text-sm font-medium text-slate-700">Nova palavra-passe</label>
                <input type="password" id="senha_nova" name="senha" required minlength="<?= Auth::MIN_SENHA ?>" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>

            <div>
                <label for="senha_confirmacao" class="mb-1.5 block text-sm font-medium text-slate-700">Confirmar nova palavra-passe</label>
                <input type="password" id="senha_confirmacao" name="senha_confirmacao" required minlength="<?= Auth::MIN_SENHA ?>" autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>

            <button type="submit"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Alterar palavra-passe
            </button>
        </form>
    </section>
</div>
