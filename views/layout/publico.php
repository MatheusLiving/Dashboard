<?php

/**
 * Layout das páginas sem sessão iniciada (início de sessão).
 *
 * @var string $conteudo
 * @var string $nomeApp
 */

use App\Core\View;

$titulo = $titulo ?? 'Iniciar sessão';
?>
<!DOCTYPE html>
<html lang="pt-PT" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($titulo) ?> · <?= View::e($nomeApp) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
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
<body class="flex h-full items-center justify-center bg-slate-100 px-4 py-10 text-slate-800 antialiased">

<div class="w-full max-w-md">
    <?= View::parcial('partials/flash') ?>
    <?= $conteudo ?>
</div>

</body>
</html>
