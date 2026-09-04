<?php

/**
 * Layout principal: barra lateral de navegação e área de conteúdo.
 *
 * @var string                    $conteudo
 * @var array<string, mixed>|null $utilizadorAtual
 * @var string                    $caminhoAtual
 * @var string                    $nomeApp
 */

use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

$titulo = $titulo ?? 'Relatório Semanal de Atividade';
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($titulo) ?> · <?= View::e($nomeApp) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Paleta alinhada com o template .docx do relatório.
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        marinho: {
                            50:  '#EEF2F9',
                            100: '#DCE6F1',
                            300: '#8FA8CC',
                            600: '#2F4F8F',
                            700: '#26406F',
                            800: '#1F3864',
                            900: '#17284A',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">

<div class="flex min-h-full">
    <?= View::parcial('partials/sidebar') ?>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3 lg:px-8">
            <div class="flex items-center gap-3">
                <button type="button"
                        class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        data-alternar-menu
                        aria-label="Abrir menu de navegação">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="text-base font-semibold text-slate-900 lg:text-lg"><?= View::e($titulo) ?></h1>
            </div>

            <?php if ($utilizadorAtual !== null): ?>
                <div class="flex items-center gap-3">
                    <a href="/perfil" class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-marinho-800 text-xs font-semibold text-white">
                            <?= View::e(User::iniciais((string) $utilizadorAtual['nome'])) ?>
                        </span>
                        <span class="hidden text-sm sm:block">
                            <span class="block font-medium leading-tight text-slate-900"><?= View::e($utilizadorAtual['nome']) ?></span>
                            <span class="block text-xs leading-tight text-slate-500"><?= View::e($utilizadorAtual['funcao_cargo'] ?: 'Colaborador') ?></span>
                        </span>
                    </a>
                    <form method="post" action="/logout">
                        <?= Csrf::campo() ?>
                        <button type="submit"
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            Terminar sessão
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <main class="flex-1 px-4 py-6 lg:px-8">
            <?= View::parcial('partials/flash') ?>
            <?= $conteudo ?>
        </main>

        <footer class="border-t border-slate-200 px-4 py-4 text-xs text-slate-400 lg:px-8">
            <?= View::e($nomeApp) ?> — Relatório Semanal de Atividade
        </footer>
    </div>
</div>

<script>window.CSRF_TOKEN = <?= json_encode(Csrf::token()) ?>;</script>
<script src="/assets/js/app.js"></script>
</body>
</html>
