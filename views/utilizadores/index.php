<?php

/**
 * Gestão de contas: criação, edição, ativação e reposição de palavra-passe.
 *
 * @var list<array<string, mixed>> $utilizadores
 * @var array<string, mixed>       $antigos
 * @var int                        $totalAdmins
 * @var array<string, mixed>|null  $utilizadorAtual
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

View::titulo('Utilizadores');

$classeCampo = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="grid gap-6 lg:grid-cols-3">

    <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-1">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Nova conta</h2>
        <p class="mb-5 text-xs text-slate-500">
            O nome e a função preenchem os campos «Colaborador» e «Função / Cargo» do relatório.
        </p>

        <form method="post" action="/utilizadores" class="space-y-4" novalidate>
            <?= Csrf::campo() ?>

            <div>
                <label for="nome" class="mb-1 block text-xs font-medium text-slate-600">Nome</label>
                <input type="text" id="nome" name="nome" required maxlength="150"
                       value="<?= View::e($antigos['nome'] ?? '') ?>" class="<?= $classeCampo ?>">
            </div>

            <div>
                <label for="email" class="mb-1 block text-xs font-medium text-slate-600">Endereço de correio</label>
                <input type="email" id="email" name="email" required maxlength="190"
                       value="<?= View::e($antigos['email'] ?? '') ?>" class="<?= $classeCampo ?>"
                       placeholder="nome@ti.local">
            </div>

            <div>
                <label for="funcao_cargo_novo" class="mb-1 block text-xs font-medium text-slate-600">Função / Cargo</label>
                <input type="text" id="funcao_cargo_novo" name="funcao_cargo" maxlength="150"
                       value="<?= View::e($antigos['funcao_cargo'] ?? '') ?>" class="<?= $classeCampo ?>"
                       placeholder="ex.: Técnico de Suporte">
            </div>

            <div>
                <label for="papel" class="mb-1 block text-xs font-medium text-slate-600">Papel</label>
                <select id="papel" name="papel" class="<?= $classeCampo ?>">
                    <option value="membro" <?= ($antigos['papel'] ?? 'membro') === 'membro' ? 'selected' : '' ?>>Membro</option>
                    <option value="admin" <?= ($antigos['papel'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
            </div>

            <div>
                <label for="senha" class="mb-1 block text-xs font-medium text-slate-600">
                    Palavra-passe inicial
                </label>
                <input type="password" id="senha" name="senha" required minlength="<?= Auth::MIN_SENHA ?>"
                       autocomplete="new-password" class="<?= $classeCampo ?>">
                <p class="mt-1 text-[11px] text-slate-400">
                    Mínimo de <?= Auth::MIN_SENHA ?> caracteres. Comunique-a e peça que a altere no perfil.
                </p>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                Criar conta
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white lg:col-span-2">
        <header class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">Contas</h2>
            <p class="mt-1 text-xs text-slate-500">
                As contas não são eliminadas: desativar mantém o histórico de tarefas e relatórios.
                <?= $totalAdmins ?> administrador<?= $totalAdmins === 1 ? '' : 'es' ?> ativo<?= $totalAdmins === 1 ? '' : 's' ?>.
            </p>
        </header>

        <ul class="divide-y divide-slate-100">
            <?php foreach ($utilizadores as $utilizador): ?>
                <?php
                $inativo = (int) $utilizador['ativo'] === 0;
                $euMesmo = (int) $utilizador['id'] === (int) ($utilizadorAtual['id'] ?? 0);
                ?>
                <li class="px-6 py-4<?= $inativo ? ' bg-slate-50' : '' ?>">

                    <form method="post" action="/utilizadores/<?= (int) $utilizador['id'] ?>" class="space-y-3" novalidate>
                        <?= Csrf::campo() ?>

                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marinho-100 text-xs font-semibold text-marinho-800">
                                <?= View::e(User::iniciais((string) $utilizador['nome'])) ?>
                            </span>

                            <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                                <input type="text" name="nome" value="<?= View::e($utilizador['nome']) ?>"
                                       required maxlength="150"
                                       class="rounded-lg border border-transparent px-2 py-1 text-sm font-medium hover:border-slate-300 focus:border-marinho-600 focus:outline-none focus:ring-2 focus:ring-marinho-100<?= $inativo ? ' text-slate-400' : '' ?>">

                                <input type="email" name="email" value="<?= View::e($utilizador['email']) ?>"
                                       required maxlength="190"
                                       class="rounded-lg border border-transparent px-2 py-1 text-sm text-slate-600 hover:border-slate-300 focus:border-marinho-600 focus:outline-none focus:ring-2 focus:ring-marinho-100">

                                <input type="text" name="funcao_cargo" value="<?= View::e($utilizador['funcao_cargo']) ?>"
                                       maxlength="150" placeholder="Função / Cargo"
                                       class="rounded-lg border border-transparent px-2 py-1 text-sm text-slate-600 hover:border-slate-300 focus:border-marinho-600 focus:outline-none focus:ring-2 focus:ring-marinho-100">

                                <select name="papel"
                                        class="rounded-lg border border-transparent px-2 py-1 text-sm text-slate-600 hover:border-slate-300 focus:border-marinho-600 focus:outline-none focus:ring-2 focus:ring-marinho-100">
                                    <option value="membro" <?= $utilizador['papel'] === 'membro' ? 'selected' : '' ?>>Membro</option>
                                    <option value="admin" <?= $utilizador['papel'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                </select>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <?php if ($inativo): ?>
                                    <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">inativa</span>
                                <?php endif; ?>
                                <?php if ($euMesmo): ?>
                                    <span class="rounded bg-marinho-50 px-1.5 py-0.5 text-[10px] font-medium text-marinho-800">a sua conta</span>
                                <?php endif; ?>
                                <button type="submit"
                                        class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                    Guardar
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-2 flex flex-wrap items-center gap-3 pl-11">
                        <?php if (!$euMesmo): ?>
                            <form method="post" action="/utilizadores/<?= (int) $utilizador['id'] ?>/ativo"
                                  onsubmit="return confirm('<?= $inativo ? 'Reativar' : 'Desativar' ?> a conta de <?= View::e($utilizador['nome']) ?>?');">
                                <?= Csrf::campo() ?>
                                <button type="submit" class="text-xs text-slate-500 underline-offset-2 hover:underline">
                                    <?= $inativo ? 'Reativar conta' : 'Desativar conta' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-xs text-slate-300">Não pode desativar a sua própria conta</span>
                        <?php endif; ?>

                        <button type="button" class="text-xs text-slate-500 underline-offset-2 hover:underline"
                                data-abrir-senha="<?= (int) $utilizador['id'] ?>">
                            Redefinir palavra-passe
                        </button>
                    </div>

                    <form method="post" action="/utilizadores/<?= (int) $utilizador['id'] ?>/senha"
                          class="mt-2 hidden items-end gap-2 pl-11" data-form-senha="<?= (int) $utilizador['id'] ?>" novalidate>
                        <?= Csrf::campo() ?>
                        <div class="w-56">
                            <label class="mb-1 block text-[11px] text-slate-500" for="senha_<?= (int) $utilizador['id'] ?>">
                                Nova palavra-passe
                            </label>
                            <input type="password" id="senha_<?= (int) $utilizador['id'] ?>" name="senha"
                                   required minlength="<?= Auth::MIN_SENHA ?>" autocomplete="new-password"
                                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                        </div>
                        <button type="submit"
                                class="rounded-lg bg-marinho-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-marinho-700">
                            Definir
                        </button>
                        <button type="button" data-fechar-senha="<?= (int) $utilizador['id'] ?>"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                            Cancelar
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<script>
    // Mostra e esconde o campo de reposição de palavra-passe de cada conta.
    document.querySelectorAll('[data-abrir-senha]').forEach(function (botao) {
        var id = botao.getAttribute('data-abrir-senha');
        var formulario = document.querySelector('[data-form-senha="' + id + '"]');

        botao.addEventListener('click', function () {
            if (!formulario) {
                return;
            }

            formulario.classList.toggle('hidden');
            formulario.classList.toggle('flex');

            if (formulario.classList.contains('flex')) {
                formulario.querySelector('input[type="password"]').focus();
            }
        });
    });

    document.querySelectorAll('[data-fechar-senha]').forEach(function (botao) {
        var id = botao.getAttribute('data-fechar-senha');
        var formulario = document.querySelector('[data-form-senha="' + id + '"]');

        botao.addEventListener('click', function () {
            if (formulario) {
                formulario.classList.add('hidden');
                formulario.classList.remove('flex');
            }
        });
    });
</script>
