<?php

/**
 * Tabela editável de uma secção do relatório.
 *
 * As linhas são acrescentadas e removidas por JavaScript a partir do
 * <template> incluído no fim; os campos chegam ao servidor como arrays
 * paralelos, um por coluna.
 *
 * @var string                     $tabela    Nome da tabela em base de dados
 * @var list<array{campo: string, rotulo: string, tipo?: string, largura?: string, placeholder?: string}> $colunas
 * @var list<string>               $ocultos   Campos guardados sem serem editáveis
 * @var list<array<string, mixed>> $linhas
 * @var string                     $vazio     Texto do estado sem linhas
 * @var bool                       $soLeitura
 */

use App\Core\View;

$soLeitura = $soLeitura ?? false;
$ocultos   = $ocultos ?? [];

$classeCelula = 'w-full rounded border border-transparent bg-transparent px-2 py-1.5 text-sm '
    . 'hover:border-slate-200 focus:border-marinho-600 focus:bg-white focus:outline-none '
    . 'focus:ring-1 focus:ring-marinho-100 disabled:text-slate-500';

/** Desenha as células de uma linha, com os valores recebidos. */
$celulas = static function (array $valores) use ($colunas, $ocultos, $tabela, $classeCelula, $soLeitura): string {
    $html = '';

    foreach ($ocultos as $campo) {
        $html .= sprintf(
            '<input type="hidden" name="%s_%s[]" value="%s">',
            View::e($tabela),
            View::e($campo),
            View::e($valores[$campo] ?? '')
        );
    }

    foreach ($colunas as $coluna) {
        $tipo  = $coluna['tipo'] ?? 'text';
        $valor = $valores[$coluna['campo']] ?? '';

        $html .= '<td class="align-top">';

        if ($tipo === 'textarea') {
            $html .= sprintf(
                '<textarea name="%s_%s[]" rows="2" class="%s" placeholder="%s"%s>%s</textarea>',
                View::e($tabela),
                View::e($coluna['campo']),
                $classeCelula,
                View::e($coluna['placeholder'] ?? ''),
                $soLeitura ? ' disabled' : '',
                View::e($valor)
            );
        } else {
            $html .= sprintf(
                '<input type="%s" name="%s_%s[]" value="%s" class="%s" placeholder="%s"%s%s>',
                View::e($tipo),
                View::e($tabela),
                View::e($coluna['campo']),
                View::e($valor),
                $classeCelula,
                View::e($coluna['placeholder'] ?? ''),
                $tipo === 'number' ? ' min="0" step="15"' : '',
                $soLeitura ? ' disabled' : ''
            );
        }

        $html .= '</td>';
    }

    return $html;
};
?>
<div class="overflow-x-auto rounded-lg border border-slate-200">
    <table class="w-full min-w-[40rem] border-collapse">
        <thead>
            <tr class="bg-marinho-800 text-left text-white">
                <?php foreach ($colunas as $coluna): ?>
                    <th class="px-2 py-2 text-xs font-semibold" style="width: <?= View::e($coluna['largura'] ?? 'auto') ?>">
                        <?= View::e($coluna['rotulo']) ?>
                    </th>
                <?php endforeach; ?>
                <?php if (!$soLeitura): ?>
                    <th class="w-10 px-2 py-2"><span class="sr-only">Remover</span></th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody data-corpo-tabela="<?= View::e($tabela) ?>">
            <?php foreach ($linhas as $linha): ?>
                <tr class="border-t border-slate-100 odd:bg-white even:bg-slate-50" data-linha>
                    <?= $celulas($linha) ?>
                    <?php if (!$soLeitura): ?>
                        <td class="px-1 py-1 text-center align-top">
                            <button type="button" data-remover-linha
                                    class="rounded p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-600"
                                    aria-label="Remover linha">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                                </svg>
                            </button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="px-3 py-6 text-center text-xs text-slate-400<?= $linhas === [] ? '' : ' hidden' ?>"
       data-tabela-vazia="<?= View::e($tabela) ?>">
        <?= View::e($vazio ?? 'Sem linhas. Acrescente as que precisar.') ?>
    </p>
</div>

<?php if (!$soLeitura): ?>
    <div class="mt-2 flex items-center gap-2">
        <button type="button" data-adicionar-linha="<?= View::e($tabela) ?>"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
            + Acrescentar linha
        </button>
        <span class="text-xs text-slate-400" data-contador-linhas="<?= View::e($tabela) ?>">
            <?= count($linhas) ?> linha<?= count($linhas) === 1 ? '' : 's' ?>
        </span>
    </div>

    <template data-modelo-linha="<?= View::e($tabela) ?>">
        <tr class="border-t border-slate-100" data-linha>
            <?= $celulas([]) ?>
            <td class="px-1 py-1 text-center align-top">
                <button type="button" data-remover-linha
                        class="rounded p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-600"
                        aria-label="Remover linha">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </td>
        </tr>
    </template>
<?php endif; ?>
