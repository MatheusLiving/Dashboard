<?php

/**
 * Gestão de etiquetas: criação, edição da cor e do nome, ativação e eliminação.
 *
 * @var list<array<string, mixed>>              $etiquetas
 * @var list<array{nome: string, hex: string}>  $paleta
 * @var array<string, mixed>                    $antigos
 */

use App\Core\Csrf;
use App\Core\View;

View::titulo('Etiquetas');

$classeCampo = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="grid gap-6 lg:grid-cols-3">

    <section class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-1">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Nova etiqueta</h2>
        <p class="mb-5 text-xs text-slate-500">
            As etiquetas classificam as tarefas e determinam o que entra na secção
            «Incidentes e Pedidos de Suporte» do relatório.
        </p>

        <form method="post" action="/tags" class="space-y-4" data-form-etiqueta novalidate>
            <?= Csrf::campo() ?>

            <div>
                <label for="nome" class="mb-1.5 block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" id="nome" name="nome" required maxlength="60"
                       value="<?= View::e($antigos['nome'] ?? '') ?>"
                       class="<?= $classeCampo ?>" placeholder="ex.: Impressoras">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Cor</label>

                <div class="mb-2 flex flex-wrap gap-1.5">
                    <?php foreach ($paleta as $cor): ?>
                        <button type="button"
                                class="h-7 w-7 rounded-md ring-offset-1 transition hover:scale-110"
                                style="background-color: <?= View::e($cor['hex']) ?>"
                                data-cor="<?= View::e($cor['hex']) ?>"
                                title="<?= View::e($cor['nome']) ?>"
                                aria-label="Cor <?= View::e($cor['nome']) ?>"></button>
                    <?php endforeach; ?>
                </div>

                <div class="flex items-center gap-2">
                    <input type="color" value="<?= View::e($antigos['cor_hex'] ?? '#1F3864') ?>"
                           data-cor-seletor
                           class="h-9 w-12 cursor-pointer rounded border border-slate-300 bg-white p-1">
                    <input type="text" name="cor_hex" required maxlength="7"
                           value="<?= View::e($antigos['cor_hex'] ?? '#1F3864') ?>"
                           pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                           data-cor-texto
                           class="<?= $classeCampo ?> font-mono uppercase">
                </div>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                Criar etiqueta
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white lg:col-span-2">
        <header class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Etiquetas existentes
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Uma etiqueta desativada desaparece dos seletores, mas continua visível nas tarefas antigas.
            </p>
        </header>

        <?php if ($etiquetas === []): ?>
            <div class="px-6 py-12 text-center">
                <p class="text-sm font-medium text-slate-600">Ainda não há etiquetas</p>
                <p class="mt-1 text-xs text-slate-400">Crie a primeira no formulário ao lado.</p>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($etiquetas as $etiqueta): ?>
                    <?php
                    $inativa = (int) $etiqueta['ativa'] === 0;
                    $usos    = (int) $etiqueta['total_tarefas'];
                    ?>
                    <li class="px-6 py-3<?= $inativa ? ' bg-slate-50' : '' ?>">
                        <form method="post" action="/tags/<?= (int) $etiqueta['id'] ?>"
                              class="flex flex-wrap items-center gap-2">
                            <?= Csrf::campo() ?>

                            <span class="h-4 w-4 shrink-0 rounded"
                                  style="background-color: <?= View::e($etiqueta['cor_hex']) ?>"></span>

                            <input type="text" name="nome" value="<?= View::e($etiqueta['nome']) ?>"
                                   required maxlength="60"
                                   class="min-w-0 flex-1 rounded-lg border border-transparent px-2 py-1 text-sm hover:border-slate-300 focus:border-marinho-600 focus:outline-none focus:ring-2 focus:ring-marinho-100<?= $inativa ? ' text-slate-400' : '' ?>">

                            <input type="color" name="cor_hex" value="<?= View::e($etiqueta['cor_hex']) ?>"
                                   class="h-8 w-10 shrink-0 cursor-pointer rounded border border-slate-300 bg-white p-0.5">

                            <span class="shrink-0 text-xs text-slate-400" title="Tarefas com esta etiqueta">
                                <?= $usos ?> uso<?= $usos === 1 ? '' : 's' ?>
                            </span>

                            <?php if ($inativa): ?>
                                <span class="shrink-0 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                    inativa
                                </span>
                            <?php endif; ?>

                            <button type="submit"
                                    class="shrink-0 rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                Guardar
                            </button>
                        </form>

                        <div class="mt-1.5 flex gap-2">
                            <form method="post" action="/tags/<?= (int) $etiqueta['id'] ?>/alternar">
                                <?= Csrf::campo() ?>
                                <button type="submit" class="text-xs text-slate-500 underline-offset-2 hover:underline">
                                    <?= $inativa ? 'Reativar' : 'Desativar' ?>
                                </button>
                            </form>

                            <?php if ($usos === 0): ?>
                                <form method="post" action="/tags/<?= (int) $etiqueta['id'] ?>/eliminar"
                                      onsubmit="return confirm('Eliminar a etiqueta «<?= View::e($etiqueta['nome']) ?>»?');">
                                    <?= Csrf::campo() ?>
                                    <button type="submit" class="text-xs text-rose-500 underline-offset-2 hover:underline">
                                        Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<script>
    // Sincroniza a paleta, o seletor de cor e o campo hexadecimal do formulário de criação.
    (function () {
        var formulario = document.querySelector('[data-form-etiqueta]');

        if (!formulario) {
            return;
        }

        var seletor = formulario.querySelector('[data-cor-seletor]');
        var texto = formulario.querySelector('[data-cor-texto]');

        formulario.querySelectorAll('[data-cor]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var cor = botao.getAttribute('data-cor');
                seletor.value = cor;
                texto.value = cor;
            });
        });

        seletor.addEventListener('input', function () {
            texto.value = seletor.value.toUpperCase();
        });

        texto.addEventListener('input', function () {
            if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(texto.value)) {
                seletor.value = texto.value;
            }
        });
    }());
</script>
