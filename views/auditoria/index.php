<?php

/**
 * Registo de auditoria: quem alterou o quê e quando.
 *
 * @var list<array<string, mixed>>            $registos
 * @var array<string, mixed>                  $filtros
 * @var bool                                  $temFiltros
 * @var list<array<string, mixed>>            $utilizadores
 * @var list<string>                          $entidades
 * @var list<string>                          $acoes
 * @var list<array{acao: string, total: int}> $resumo
 * @var int                                   $total
 * @var int                                   $pagina
 * @var int                                   $paginas
 * @var array<string, string>                 $rotulosAcao
 * @var array<string, string>                 $rotulosEnt
 */

use App\Core\View;

View::titulo('Auditoria');

$classeCampo = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';

/** Mantém os filtros ao mudar de página. */
$ligacaoPagina = static function (int $numero) use ($filtros): string {
    $parametros = array_filter([
        'entidade'   => $filtros['entidade'],
        'acao'       => $filtros['acao'],
        'utilizador' => $filtros['user_id'],
        'de'         => $filtros['de'],
        'ate'        => $filtros['ate'],
        'pagina'     => $numero,
    ], static fn (mixed $v): bool => $v !== null && $v !== '');

    return '/auditoria?' . http_build_query($parametros);
};

$cores = [
    'criar'     => 'bg-emerald-50 text-emerald-700',
    'atualizar' => 'bg-sky-50 text-sky-700',
    'eliminar'  => 'bg-rose-50 text-rose-700',
    'mover'     => 'bg-violet-50 text-violet-700',
    'entregar'  => 'bg-marinho-50 text-marinho-800',
    'reabrir'   => 'bg-amber-50 text-amber-700',
    'gerar'     => 'bg-slate-100 text-slate-600',
    'enviar_email' => 'bg-indigo-50 text-indigo-700',
    'enviar_aprovacao' => 'bg-sky-50 text-sky-700',
    'aprovar'          => 'bg-emerald-50 text-emerald-700',
    'pedir_alteracoes' => 'bg-amber-50 text-amber-700',
];
?>
<div class="space-y-4">

    <form method="get" action="/auditoria" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <label class="block">
            <span class="mb-1 block text-[11px] text-slate-500">Entidade</span>
            <select name="entidade" class="<?= $classeCampo ?>">
                <option value="">Todas</option>
                <?php foreach ($entidades as $entidade): ?>
                    <option value="<?= View::e($entidade) ?>" <?= ($filtros['entidade'] ?? '') === $entidade ? 'selected' : '' ?>>
                        <?= View::e($rotulosEnt[$entidade] ?? $entidade) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-[11px] text-slate-500">Ação</span>
            <select name="acao" class="<?= $classeCampo ?>">
                <option value="">Todas</option>
                <?php foreach ($acoes as $acao): ?>
                    <option value="<?= View::e($acao) ?>" <?= ($filtros['acao'] ?? '') === $acao ? 'selected' : '' ?>>
                        <?= View::e($rotulosAcao[$acao] ?? $acao) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-[11px] text-slate-500">Utilizador</span>
            <select name="utilizador" class="<?= $classeCampo ?>">
                <option value="">Todos</option>
                <?php foreach ($utilizadores as $utilizador): ?>
                    <option value="<?= (int) $utilizador['id'] ?>"
                        <?= (int) ($filtros['user_id'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                        <?= View::e($utilizador['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-[11px] text-slate-500">De</span>
            <input type="date" name="de" value="<?= View::e($filtros['de'] ?? '') ?>" class="<?= $classeCampo ?>">
        </label>

        <label class="block">
            <span class="mb-1 block text-[11px] text-slate-500">Até</span>
            <input type="date" name="ate" value="<?= View::e($filtros['ate'] ?? '') ?>" class="<?= $classeCampo ?>">
        </label>

        <button type="submit" class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-marinho-700">
            Filtrar
        </button>

        <?php if ($temFiltros): ?>
            <a href="/auditoria" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpar
            </a>
        <?php endif; ?>

        <span class="ml-auto text-xs text-slate-400">
            <?= number_format($total, 0, ',', ' ') ?> registo<?= $total === 1 ? '' : 's' ?>
        </span>
    </form>

    <?php if ($resumo !== []): ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($resumo as $item): ?>
                <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs">
                    <span class="font-medium text-slate-700"><?= View::e($rotulosAcao[$item['acao']] ?? $item['acao']) ?></span>
                    <span class="ml-1.5 text-slate-400"><?= $item['total'] ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($registos === []): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="text-sm font-medium text-slate-600">
                <?= $temFiltros ? 'Nenhum registo corresponde aos filtros' : 'Ainda não há registos de auditoria' ?>
            </p>
            <p class="mt-1 text-xs text-slate-400">
                <?= $temFiltros
                    ? 'Ajuste os filtros ou limpe-os para ver todos.'
                    : 'As alterações a tarefas, projetos, etiquetas, contas e relatórios aparecem aqui.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Quando</th>
                            <th class="px-5 py-3">Quem</th>
                            <th class="px-5 py-3">Ação</th>
                            <th class="px-5 py-3">Entidade</th>
                            <th class="px-5 py-3">Alterações</th>
                            <th class="px-5 py-3">Origem</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($registos as $registo): ?>
                            <?php
                            $dados = $registo['dados_json'] === null
                                ? []
                                : (json_decode((string) $registo['dados_json'], true) ?: []);
                            ?>
                            <tr class="align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">
                                    <?= View::e(date('d/m/Y', strtotime((string) $registo['created_at']))) ?>
                                    <span class="block text-slate-400"><?= View::e(date('H:i:s', strtotime((string) $registo['created_at']))) ?></span>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-700">
                                    <?= View::e($registo['utilizador'] ?? '—') ?>
                                </td>

                                <td class="px-5 py-3">
                                    <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold <?= $cores[$registo['acao']] ?? 'bg-slate-100 text-slate-600' ?>">
                                        <?= View::e($rotulosAcao[$registo['acao']] ?? $registo['acao']) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-3 text-sm text-slate-600">
                                    <?= View::e($rotulosEnt[$registo['entidade']] ?? $registo['entidade']) ?>
                                    <?php if ($registo['entidade_id'] !== null && (int) $registo['entidade_id'] > 0): ?>
                                        <span class="text-slate-400">#<?= (int) $registo['entidade_id'] ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-3 text-xs">
                                    <?php if ($dados === []): ?>
                                        <span class="text-slate-300">—</span>
                                    <?php else: ?>
                                        <details>
                                            <summary class="cursor-pointer text-slate-500 hover:text-marinho-800">
                                                <?= count($dados) ?> campo<?= count($dados) === 1 ? '' : 's' ?>
                                            </summary>
                                            <dl class="mt-1.5 space-y-1">
                                                <?php foreach ($dados as $campo => $mudanca): ?>
                                                    <div class="rounded bg-slate-50 px-2 py-1">
                                                        <dt class="font-medium text-slate-600"><?= View::e((string) $campo) ?></dt>
                                                        <dd class="text-slate-500">
                                                            <?php if (is_array($mudanca) && array_key_exists('antes', $mudanca)): ?>
                                                                <span class="text-rose-600 line-through">
                                                                    <?= View::e(mb_strimwidth((string) ($mudanca['antes'] ?? '—'), 0, 60, '…')) ?>
                                                                </span>
                                                                <span class="mx-1 text-slate-300">·</span>
                                                                <span class="text-emerald-700">
                                                                    <?= View::e(mb_strimwidth((string) ($mudanca['depois'] ?? '—'), 0, 60, '…')) ?>
                                                                </span>
                                                            <?php elseif (is_array($mudanca)): ?>
                                                                <?= View::e(mb_strimwidth(json_encode($mudanca, JSON_UNESCAPED_UNICODE) ?: '', 0, 120, '…')) ?>
                                                            <?php else: ?>
                                                                <?= View::e(mb_strimwidth((string) $mudanca, 0, 120, '…')) ?>
                                                            <?php endif; ?>
                                                        </dd>
                                                    </div>
                                                <?php endforeach; ?>
                                            </dl>
                                        </details>
                                    <?php endif; ?>
                                </td>

                                <td class="whitespace-nowrap px-5 py-3 font-mono text-[11px] text-slate-400">
                                    <?= View::e($registo['ip'] ?: '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($paginas > 1): ?>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
                    <span class="text-xs text-slate-500">
                        Página <?= $pagina ?> de <?= $paginas ?>
                    </span>

                    <div class="flex gap-2">
                        <?php if ($pagina > 1): ?>
                            <a href="<?= View::e($ligacaoPagina($pagina - 1)) ?>"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php if ($pagina < $paginas): ?>
                            <a href="<?= View::e($ligacaoPagina($pagina + 1)) ?>"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                Seguinte
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
