<?php

/**
 * Cartão de uma tarefa no quadro Kanban.
 *
 * O mesmo desenho é reproduzido em JavaScript quando um cartão é criado ou
 * editado sem recarregar a página — ver `cartaoHtml()` em kanban.js.
 *
 * @var array<string, mixed>  $tarefa
 * @var array<string, string> $cores  Cores por prioridade
 */

use App\Core\Semana;
use App\Core\View;
use App\Models\User;

$prioridade = (string) $tarefa['prioridade'];
$minutos    = (int) ($tarefa['total_minutos'] ?? 0);
?>
<article class="grupo-cartao cursor-pointer rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:border-marinho-300 hover:shadow"
         data-cartao
         data-id="<?= (int) $tarefa['id'] ?>"
         tabindex="0"
         role="button"
         aria-label="Abrir tarefa <?= View::e($tarefa['titulo']) ?>">

    <div class="flex items-start gap-2">
        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
              style="background-color: <?= View::e($cores[$prioridade] ?? '#94A3B8') ?>"
              title="Prioridade: <?= View::e($prioridade) ?>"></span>
        <p class="min-w-0 flex-1 text-sm font-medium leading-snug text-slate-800">
            <?= View::e($tarefa['titulo']) ?>
        </p>
    </div>

    <?php if (!empty($tarefa['tags'])): ?>
        <div class="mt-2 flex flex-wrap gap-1">
            <?php foreach ($tarefa['tags'] as $etiqueta): ?>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold leading-tight text-white<?= (int) $etiqueta['ativa'] === 0 ? ' opacity-50' : '' ?>"
                      style="background-color: <?= View::e($etiqueta['cor_hex']) ?>"
                      <?= (int) $etiqueta['ativa'] === 0 ? 'title="Etiqueta desativada"' : '' ?>>
                    <?= View::e($etiqueta['nome']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($tarefa['projeto_nome'])): ?>
        <p class="mt-2 truncate text-xs text-slate-500" title="<?= View::e($tarefa['projeto_nome']) ?>">
            <?= View::e($tarefa['projeto_nome']) ?>
        </p>
    <?php endif; ?>

    <div class="mt-3 flex items-center justify-between gap-2">
        <?php if (!empty($tarefa['responsavel_nome'])): ?>
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-marinho-100 text-[10px] font-semibold text-marinho-800"
                  title="<?= View::e($tarefa['responsavel_nome']) ?>">
                <?= View::e(User::iniciais((string) $tarefa['responsavel_nome'])) ?>
            </span>
        <?php else: ?>
            <span class="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-slate-300 text-[10px] text-slate-400"
                  title="Sem responsável">—</span>
        <?php endif; ?>

        <?php if ($minutos > 0): ?>
            <span class="text-[11px] text-slate-400"><?= View::e(Semana::minutosParaTexto($minutos)) ?></span>
        <?php endif; ?>
    </div>
</article>
