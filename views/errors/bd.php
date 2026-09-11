<?php

/**
 * Página apresentada quando a base de dados não responde.
 *
 * É deliberadamente autónoma: não usa o layout, o `View`, a sessão nem as
 * mensagens flash, porque nenhuma dessas coisas funciona sem base de dados —
 * as sessões vivem lá. Também não usa o Tailwind do CDN, para continuar a ser
 * legível numa máquina sem rede.
 *
 * @var Throwable $e      Exceção apanhada pelo front-controller
 * @var bool      $debug  Modo de depuração
 */

$detalhe = ($debug ?? false) ? $e->getMessage() : '';
$escapar = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Base de dados indisponível</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 2.5rem 1rem; background: #f1f5f9; color: #1e293b;
            font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .caixa { width: 100%; max-width: 40rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 2rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.15rem; color: #1F3864; }
        p { margin: 0 0 1rem; color: #475569; }
        h2 { margin: 1.5rem 0 .5rem; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        ol { margin: 0; padding-left: 1.2rem; color: #475569; }
        li { margin-bottom: .6rem; }
        code {
            display: inline-block; background: #0f172a; color: #e2e8f0; padding: .2rem .5rem;
            border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .82rem;
        }
        .detalhe {
            margin-top: 1.5rem; padding: .85rem 1rem; background: #fff1f2; border: 1px solid #fecdd3;
            border-radius: 10px; color: #9f1239; font-size: .82rem; word-break: break-word;
        }
        .rodape { margin: 1.5rem 0 0; font-size: .78rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="caixa">
        <h1>A base de dados não está disponível</h1>
        <p>
            A aplicação arrancou, mas não conseguiu ligar-se ao MySQL. Nada se perdeu —
            é só a ligação que falta.
        </p>

        <h2>O que verificar</h2>
        <ol>
            <li>
                O MySQL está a correr?
                <br><code>docker compose up -d mysql</code>
            </li>
            <li>
                Com Docker Desktop, confirme que o próprio Docker está aberto — o contentor
                não arranca sem ele.
            </li>
            <li>
                O estado do contentor:
                <br><code>docker ps</code>
            </li>
            <li>
                As variáveis <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>,
                <code>DB_USERNAME</code> e <code>DB_PASSWORD</code> no ficheiro <code>.env</code>.
            </li>
        </ol>

        <?php if ($detalhe !== ''): ?>
            <p class="detalhe"><?= $escapar($detalhe) ?></p>
        <?php endif; ?>

        <p class="rodape">
            O erro ficou registado em <code>storage/logs/php.log</code>.
            Assim que a base de dados responder, recarregue a página.
        </p>
    </div>
</body>
</html>
