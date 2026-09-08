<?php

/**
 * Listagem de relatórios semanais.
 *
 * @var list<array<string, mixed>> $relatorios
 * @var array<string, mixed>       $filtros
 * @var bool                       $temFiltros
 * @var list<array<string, mixed>> $utilizadores  Vazio para quem não é administrador
 * @var list<int>                  $anos
 * @var bool                       $ehAdmin
 */

use App\Core\Csrf;
use App\Core\Semana;
use App\Core\View;
use App\Models\Report;
use App\Models\User;

View::titulo('Relatórios');

$classeCampo = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="space-y-4">

    <form method="get" action="/relatorios" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">

        <?php if ($ehAdmin): ?>
            <select name="utilizador" class="<?= $classeCampo ?>">
                <option value="">Todos os colaboradores</option>
                <?php foreach ($utilizadores as $utilizador): ?>
                    <option value="<?= (int) $utilizador['id'] ?>"
                        <?= (int) ($filtros['user_id'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                        <?= View::e($utilizador['nome']) ?><?= (int) $utilizador['ativo'] === 0 ? ' (inativo)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <select name="ano" class="<?= $classeCampo ?>">
            <option value="">Todos os anos</option>
            <?php foreach ($anos as $ano): ?>
                <option value="<?= $ano ?>" <?= (int) ($filtros['ano'] ?? 0) === $ano ? 'selected' : '' ?>>
                    <?= $ano ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="semana" min="1" max="53" placeholder="Semana"
               value="<?= View::e($filtros['semana'] ?? '') ?>" class="<?= $classeCampo ?> w-24">

        <select name="status" class="<?= $classeCampo ?>">
            <option value="">Todos os estados</option>
            <option value="rascunho" <?= ($filtros['status'] ?? '') === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
            <option value="entregue" <?= ($filtros['status'] ?? '') === 'entregue' ? 'selected' : '' ?>>Entregue</option>
        </select>

        <button type="submit" class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-marinho-700">
            Filtrar
        </button>

        <?php if ($temFiltros): ?>
            <a href="/relatorios" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        <?php endif; ?>

        <a href="/relatorios/nova"
           class="ml-auto rounded-lg border border-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-marinho-800 hover:bg-marinho-50">
            + Novo relatório
        </a>
    </form>

    <?php if ($relatorios === []): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="text-sm font-medium text-slate-600">
                <?= $temFiltros ? 'Nenhum relatório corresponde aos filtros' : 'Ainda não há relatórios' ?>
            </p>
            <p class="mt-1 text-xs text-slate-400">
                <?= $temFiltros
                    ? 'Ajuste os filtros ou limpe-os para ver todos.'
                    : 'O relatório da semana corrente vem pré-preenchido com o trabalho registado no quadro.' ?>
            </p>
            <?php if (!$temFiltros): ?>
                <a href="/relatorios/nova"
                   class="mt-4 inline-block rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    Preparar o relatório desta semana
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Semana</th>
                            <?php if ($ehAdmin): ?>
                                <th class="px-5 py-3">Colaborador</th>
                            <?php endif; ?>
                            <th class="px-5 py-3">Período</th>
                            <th class="px-5 py-3">Entrega</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3">Ficheiros</th>
                            <th class="px-5 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($relatorios as $relatorio): ?>
                            <?php $entregue = $relatorio['status'] === Report::ENTREGUE; ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 text-sm font-medium text-slate-800">
                                    <?= View::e(Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana'])) ?>
                                </td>

                                <?php if ($ehAdmin): ?>
                                    <td class="px-5 py-3">
                                        <span class="flex items-center gap-2">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-marinho-100 text-[10px] font-semibold text-marinho-800">
                                                <?= View::e(User::iniciais((string) $relatorio['colaborador'])) ?>
                                            </span>
                                            <span class="text-sm text-slate-700"><?= View::e($relatorio['colaborador']) ?></span>
                                        </span>
                                    </td>
                                <?php endif; ?>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    <?= View::e(date('d/m', strtotime((string) $relatorio['semana_inicio']))) ?>
                                    –
                                    <?= View::e(date('d/m/Y', strtotime((string) $relatorio['semana_fim']))) ?>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    <?= $relatorio['data_entrega']
                                        ? View::e(date('d/m/Y', strtotime((string) $relatorio['data_entrega'])))
                                        : '—' ?>
                                </td>

                                <td class="px-5 py-3">
                                    <span class="rounded px-2 py-0.5 text-[11px] font-semibold <?= $entregue
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-amber-50 text-amber-700' ?>">
                                        <?= $entregue ? 'Entregue' : 'Rascunho' ?>
                                    </span>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-500">
                                    <?php $total = (int) $relatorio['total_exportacoes']; ?>
                                    <?= $total > 0 ? $total . ' ficheiro' . ($total === 1 ? '' : 's') : '—' ?>
                                </td>

                                <?php
                                // Alterar e eliminar são só do autor, mesmo para o administrador.
                                $ehAutor = (int) $relatorio['user_id'] === (int) ($utilizadorAtual['id'] ?? 0);

                                $aviso = sprintf(
                                    'Eliminar definitivamente o relatório de %s?',
                                    Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana'])
                                );

                                if ($total > 0) {
                                    $aviso .= sprintf(
                                        ' Apaga também %d ficheiro%s gerado%s.',
                                        $total,
                                        $total === 1 ? '' : 's',
                                        $total === 1 ? '' : 's'
                                    );
                                }

                                $aviso .= ' Esta ação não pode ser desfeita.';
                                ?>
                                <td class="px-5 py-3 text-right">
                                    <a href="/relatorios/<?= (int) $relatorio['id'] ?>"
                                       class="text-sm font-medium text-marinho-800 underline-offset-2 hover:underline">
                                        Ver
                                    </a>
                                    <?php if ($ehAutor): ?>
                                        <a href="/relatorios/<?= (int) $relatorio['id'] ?>/editar"
                                           class="ml-3 text-sm font-medium text-slate-500 underline-offset-2 hover:underline">
                                            <?= $entregue ? 'Alterar' : 'Editar' ?>
                                        </a>

                                        <form method="post" action="/relatorios/<?= (int) $relatorio['id'] ?>/eliminar"
                                              class="ml-3 inline"
                                              onsubmit="return confirm('<?= View::e($aviso) ?>');">
                                            <?= Csrf::campo() ?>
                                            <button type="submit"
                                                    class="text-sm font-medium text-rose-600 underline-offset-2 hover:underline">
                                                Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
