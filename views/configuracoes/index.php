<?php

/**
 * Configurações do departamento.
 *
 * @var array<string, array{rotulo: string, tipo: string, ajuda: string, omissao: string}> $definicoes
 * @var array<string, string>      $valores
 * @var array<string, mixed>       $antigos
 * @var list<array<string, mixed>> $etiquetas
 * @var array<int, string>         $diasSemana
 * @var string                     $templateCaminho
 * @var bool                       $templateExiste
 * @var string                     $pastaRelatorios
 * @var string                     $fuso
 * @var string                     $ambiente
 */

use App\Core\Csrf;
use App\Core\View;

View::titulo('Configurações');

$classeCampo = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';

/** Valor a apresentar: o submetido antes de um erro tem prioridade. */
$valor = static function (string $chave) use ($antigos, $valores): string {
    return (string) ($antigos[$chave] ?? $valores[$chave] ?? '');
};
?>
<div class="mx-auto max-w-4xl space-y-6">

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">
            Configurações do departamento
        </h2>
        <p class="mb-5 text-xs text-slate-500">
            Opções de negócio, editáveis aqui. As credenciais e a configuração de
            infraestrutura ficam no ficheiro <code class="rounded bg-slate-100 px-1">.env</code>.
        </p>

        <form method="post" action="/configuracoes" class="space-y-5" novalidate>
            <?= Csrf::campo() ?>

            <?php foreach ($definicoes as $chave => $definicao): ?>
                <div>
                    <label for="<?= View::e($chave) ?>" class="mb-1 block text-sm font-medium text-slate-700">
                        <?= View::e($definicao['rotulo']) ?>
                    </label>

                    <?php if ($definicao['tipo'] === 'dia_semana'): ?>
                        <select id="<?= View::e($chave) ?>" name="<?= View::e($chave) ?>" class="<?= $classeCampo ?>">
                            <?php foreach ($diasSemana as $numero => $nome): ?>
                                <option value="<?= $numero ?>" <?= (int) $valor($chave) === $numero ? 'selected' : '' ?>>
                                    <?= View::e($nome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ($definicao['tipo'] === 'inteiro'): ?>
                        <input type="number" id="<?= View::e($chave) ?>" name="<?= View::e($chave) ?>"
                               min="0" max="30" value="<?= View::e($valor($chave)) ?>" class="<?= $classeCampo ?>">

                    <?php else: ?>
                        <input type="text" id="<?= View::e($chave) ?>" name="<?= View::e($chave) ?>"
                               value="<?= View::e($valor($chave)) ?>" maxlength="255" class="<?= $classeCampo ?>">
                    <?php endif; ?>

                    <p class="mt-1 text-[11px] text-slate-400"><?= View::e($definicao['ajuda']) ?></p>

                    <?php if ($chave === 'tags_incidente'): ?>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Disponíveis:
                            <?php foreach ($etiquetas as $indice => $etiqueta): ?><?= $indice > 0 ? ', ' : '' ?><code class="rounded bg-slate-100 px-1"><?= View::e($etiqueta['slug']) ?></code><?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit"
                    class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                Guardar configurações
            </button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Estado do sistema</h2>
        <p class="mb-4 text-xs text-slate-500">
            Valores lidos do ambiente. Só mudam editando o <code class="rounded bg-slate-100 px-1">.env</code>.
        </p>

        <dl class="divide-y divide-slate-100 text-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
                <dt class="text-slate-500">Template do relatório</dt>
                <dd class="min-w-0 text-right">
                    <span class="block truncate font-mono text-xs text-slate-700"><?= View::e($templateCaminho) ?></span>
                    <?php if ($templateExiste): ?>
                        <span class="text-[11px] font-medium text-emerald-600">encontrado</span>
                    <?php else: ?>
                        <span class="text-[11px] font-medium text-rose-600">
                            em falta — execute <code>php bin/preparar-template.php</code>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="flex flex-wrap items-baseline justify-between gap-2 py-2.5">
                <dt class="text-slate-500">Pasta dos relatórios gerados</dt>
                <dd class="truncate font-mono text-xs text-slate-700"><?= View::e($pastaRelatorios) ?></dd>
            </div>

            <div class="flex items-baseline justify-between gap-2 py-2.5">
                <dt class="text-slate-500">Fuso horário</dt>
                <dd class="font-mono text-xs text-slate-700"><?= View::e($fuso) ?></dd>
            </div>

            <div class="flex items-baseline justify-between gap-2 py-2.5">
                <dt class="text-slate-500">Ambiente</dt>
                <dd>
                    <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold <?= $ambiente === 'production'
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-700' ?>">
                        <?= View::e($ambiente) ?>
                    </span>
                </dd>
            </div>

            <div class="flex items-baseline justify-between gap-2 py-2.5">
                <dt class="text-slate-500">Semana ISO corrente</dt>
                <dd class="text-xs text-slate-700">
                    <?= View::e(App\Core\Semana::rotulo(...array_values(App\Core\Semana::corrente()))) ?>
                </dd>
            </div>
        </dl>
    </section>
</div>
