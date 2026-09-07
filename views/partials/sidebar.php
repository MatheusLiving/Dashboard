<?php

/**
 * Barra lateral de navegação.
 *
 * As entradas marcadas com 'disponivel' => false ainda não têm rota: aparecem
 * assinaladas para dar visibilidade ao mapa da aplicação durante o desenvolvimento.
 *
 * @var array<string, mixed>|null $utilizadorAtual
 * @var string                    $caminhoAtual
 * @var string                    $nomeApp
 */

use App\Core\Auth;
use App\Core\View;

$navegacao = [
    [
        'grupo' => 'Trabalho',
        'itens' => [
            ['rotulo' => 'Painel',     'href' => '/',           'icone' => 'grelha',    'disponivel' => true],
            ['rotulo' => 'Quadro',     'href' => '/kanban',     'icone' => 'colunas',   'disponivel' => true],
            ['rotulo' => 'Backlog',    'href' => '/backlog',    'icone' => 'lista',     'disponivel' => true],
            ['rotulo' => 'Projetos',   'href' => '/projetos',   'icone' => 'pasta',     'disponivel' => true],
        ],
    ],
    [
        'grupo' => 'Relatórios',
        'itens' => [
            ['rotulo' => 'Relatórios',      'href' => '/relatorios',      'icone' => 'documento', 'disponivel' => true],
            ['rotulo' => 'Novo relatório',  'href' => '/relatorios/nova', 'icone' => 'mais',      'disponivel' => true],
        ],
    ],
    [
        'grupo' => 'Administração',
        'apenasAdmin' => true,
        'itens' => [
            ['rotulo' => 'Etiquetas',     'href' => '/tags',        'icone' => 'etiqueta',  'disponivel' => true],
            ['rotulo' => 'Utilizadores',  'href' => '/utilizadores', 'icone' => 'pessoas',  'disponivel' => false],
            ['rotulo' => 'Configurações', 'href' => '/configuracoes', 'icone' => 'roda',    'disponivel' => false],
            ['rotulo' => 'Auditoria',     'href' => '/auditoria',   'icone' => 'escudo',    'disponivel' => false],
        ],
    ],
];

/** Devolve o caminho SVG de cada ícone da navegação. */
$icone = static function (string $nome): string {
    return match ($nome) {
        'grelha'    => 'M4 5h6v6H4V5zm10 0h6v6h-6V5zM4 15h6v4H4v-4zm10 0h6v4h-6v-4z',
        'colunas'   => 'M4 5h4v14H4V5zm6 0h4v14h-4V5zm6 0h4v14h-4V5z',
        'lista'     => 'M4 6h16M4 12h16M4 18h10',
        'pasta'     => 'M3 7a2 2 0 012-2h4l2 2h7a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
        'documento' => 'M7 4h7l4 4v12H7V4zm7 0v4h4',
        'mais'      => 'M12 5v14M5 12h14',
        'etiqueta'  => 'M3 12l8-8h8v8l-8 8-8-8zm13-4h.01',
        'pessoas'   => 'M8 11a3 3 0 100-6 3 3 0 000 6zm8 0a3 3 0 100-6 3 3 0 000 6zM3 20a5 5 0 0110 0M14 20a5 5 0 017-4.6',
        'roda'      => 'M12 15a3 3 0 100-6 3 3 0 000 6zM4.5 12l-1.6-1 1.2-2.1 1.8.5.9-1.6-.9-1.7 1.7-1.7 1.7.9 1.6-.9-.5-1.8L12 2l1 1.6 1.8-.5.9 1.6-.9 1.7 1.7 1.7 1.7-.9 1.6.9-.5 1.8L21 12',
        'escudo'    => 'M12 3l7 3v6c0 4.4-3 7.6-7 9-4-1.4-7-4.6-7-9V6l7-3z',
        default     => 'M12 6v12M6 12h12',
    };
};
?>
<aside data-menu-lateral
       class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform lg:static lg:flex lg:translate-x-0">

    <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-marinho-800 text-sm font-bold text-white">TI</span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-semibold text-slate-900"><?= View::e($nomeApp) ?></span>
            <span class="block text-xs text-slate-500">Relatório Semanal</span>
        </span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        <?php foreach ($navegacao as $seccao): ?>
            <?php if (($seccao['apenasAdmin'] ?? false) && !Auth::admin()) { continue; } ?>

            <p class="px-3 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-400 first:pt-0">
                <?= View::e($seccao['grupo']) ?>
            </p>

            <ul class="space-y-0.5">
                <?php foreach ($seccao['itens'] as $item): ?>
                    <?php
                    $ativo = $item['disponivel']
                        && ($caminhoAtual === $item['href']
                            || ($item['href'] !== '/' && str_starts_with($caminhoAtual, $item['href'])));
                    ?>
                    <li>
                        <?php if ($item['disponivel']): ?>
                            <a href="<?= View::e($item['href']) ?>"
                               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                                      <?= $ativo ? 'bg-marinho-50 text-marinho-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
                                <svg class="h-4.5 w-4.5 shrink-0" style="width:1.125rem;height:1.125rem"
                                     fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icone($item['icone']) ?>"/>
                                </svg>
                                <?= View::e($item['rotulo']) ?>
                            </a>
                        <?php else: ?>
                            <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-300"
                                  title="Disponível numa fase seguinte">
                                <svg class="shrink-0" style="width:1.125rem;height:1.125rem"
                                     fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icone($item['icone']) ?>"/>
                                </svg>
                                <?= View::e($item['rotulo']) ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <?php if ($utilizadorAtual !== null): ?>
        <div class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
            <?= View::e($utilizadorAtual['papel'] === 'admin' ? 'Administrador' : 'Membro da equipa') ?>
        </div>
    <?php endif; ?>
</aside>

<div data-fundo-menu class="fixed inset-0 z-20 hidden bg-slate-900/40 lg:hidden"></div>
