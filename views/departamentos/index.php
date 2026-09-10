<?php

/**
 * Quadro de assistências por departamento.
 *
 * Informativo, para uso interno da equipa de TI: nada daqui entra no
 * relatório semanal.
 *
 * @var list<array<string, mixed>> $classificacao
 * @var string                     $periodo
 * @var array{de: ?string, ate: ?string, rotulo: string} $intervalo
 * @var int                        $totalPeriodo
 * @var int                        $totalSempre
 * @var list<array<string, mixed>> $porAutor
 * @var list<array{rotulo: string, votos: int}> $evolucao
 * @var list<array<string, mixed>> $recentes
 * @var list<array<string, mixed>> $paraVotar
 * @var bool                       $ehAdmin
 * @var array<string, mixed>       $antigos
 * @var array<string, mixed>|null  $utilizadorAtual
 */

use App\Core\Csrf;
use App\Core\View;
use App\Models\Department;
use App\Models\Tag;

View::titulo('Assistências por departamento');

$eu = (int) ($utilizadorAtual['id'] ?? 0);

$rotulosPeriodo = [
    'semana' => 'Esta semana',
    'mes'    => 'Este mês',
    'ano'    => 'Este ano',
    'tudo'   => 'Desde sempre',
];

// A barra de cada departamento é relativa ao primeiro da tabela, não ao total:
// com muitos departamentos, as percentagens do total ficariam todas rasteiras
// e o quadro deixaria de se ler.
$maximo = 0;
foreach ($classificacao as $linha) {
    $maximo = max($maximo, (int) $linha['votos']);
}

$maximoEvolucao = 1;
foreach ($evolucao as $ponto) {
    $maximoEvolucao = max($maximoEvolucao, (int) $ponto['votos']);
}

$classeCampo = 'rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';

/** Medalha das três primeiras posições; a partir daí, só o número. */
$medalha = static function (int $posicao): string {
    return match ($posicao) {
        1       => 'bg-amber-100 text-amber-800 ring-1 ring-amber-300',
        2       => 'bg-slate-200 text-slate-700 ring-1 ring-slate-300',
        3       => 'bg-orange-100 text-orange-800 ring-1 ring-orange-300',
        default => 'bg-slate-50 text-slate-400',
    };
};
?>
<div class="space-y-4">

    <!-- Cabeçalho e período -->
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-marinho-800">Assistências por departamento</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Cada registo é um pedido de assistência técnica atribuído a um departamento.
                    Serve para percebermos de onde vem o trabalho — <strong class="font-semibold text-slate-600">é
                    informação nossa e não entra no relatório semanal</strong>.
                </p>
            </div>

            <div class="text-right">
                <p class="text-3xl font-bold text-marinho-800"><?= (int) $totalPeriodo ?></p>
                <p class="text-xs text-slate-400">
                    <?= View::e($intervalo['rotulo']) ?>
                    <?php if ($periodo !== 'tudo'): ?>
                        · <?= (int) $totalSempre ?> desde sempre
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-1.5">
            <?php foreach ($rotulosPeriodo as $chave => $rotulo): ?>
                <a href="/departamentos?periodo=<?= View::e($chave) ?>"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                          <?= $periodo === $chave
                                ? 'bg-marinho-800 text-white'
                                : 'border border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
                    <?= View::e($rotulo) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Registar uma assistência -->
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">
            Registar assistência
        </h2>

        <?php if ($paraVotar === []): ?>
            <p class="text-sm text-slate-500">
                Ainda não há departamentos na lista.
                <?= $ehAdmin ? 'Crie o primeiro na secção de gestão, mais abaixo.' : 'Peça a um administrador que os configure.' ?>
            </p>
        <?php else: ?>
            <form method="post" action="/departamentos/voto" class="flex flex-wrap items-end gap-2">
                <?= Csrf::campo() ?>
                <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">

                <label class="block">
                    <span class="mb-1 block text-[11px] text-slate-500">Departamento</span>
                    <select name="department_id" class="<?= $classeCampo ?>">
                        <?php foreach ($paraVotar as $departamento): ?>
                            <option value="<?= (int) $departamento['id'] ?>"
                                <?= (int) ($antigos['department_id'] ?? 0) === (int) $departamento['id'] ? 'selected' : '' ?>>
                                <?= View::e($departamento['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="block min-w-[16rem] flex-1">
                    <span class="mb-1 block text-[11px] text-slate-500">Nota <span class="text-slate-400">(opcional)</span></span>
                    <input type="text" name="nota" maxlength="200"
                           value="<?= View::e($antigos['nota'] ?? '') ?>"
                           placeholder="Impressora da contabilidade sem rede"
                           class="<?= $classeCampo ?> w-full">
                </label>

                <button type="submit"
                        class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    + Registar
                </button>
            </form>
            <p class="mt-2 text-[11px] text-slate-400">
                Uma assistência de cada vez. Se o mesmo departamento ligar três vezes no mesmo dia, registe três.
            </p>
        <?php endif; ?>
    </section>

    <!-- Classificação -->
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">Classificação</h2>
            <span class="text-xs text-slate-400"><?= View::e($intervalo['rotulo']) ?></span>
        </div>

        <?php if ($classificacao === []): ?>
            <p class="text-sm text-slate-500">Ainda não há departamentos para classificar.</p>
        <?php elseif ($totalPeriodo === 0): ?>
            <p class="text-sm text-slate-500">
                Nenhuma assistência registada neste período. Use o formulário acima ou escolha um período mais largo.
            </p>
        <?php else: ?>
            <ol class="space-y-2">
                <?php foreach ($classificacao as $indice => $linha): ?>
                    <?php
                    $votos      = (int) $linha['votos'];
                    $posicao    = $indice + 1;
                    $largura    = $maximo > 0 ? max(2, (int) round($votos / $maximo * 100)) : 0;
                    $percentagem = $totalPeriodo > 0 ? round($votos / $totalPeriodo * 100) : 0;
                    ?>
                    <li class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold <?= $medalha($posicao) ?>">
                            <?= $posicao ?>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="truncate text-sm font-medium text-slate-800">
                                    <?= View::e($linha['nome']) ?>
                                    <?php if ((int) $linha['ativo'] === 0): ?>
                                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                                            desativado
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="shrink-0 text-xs text-slate-400">
                                    <strong class="text-sm font-semibold text-slate-700"><?= $votos ?></strong>
                                    <?php if ($votos > 0): ?>
                                        · <?= $percentagem ?>%
                                    <?php endif; ?>
                                </span>
                            </span>

                            <span class="mt-1 block h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <span class="block h-full rounded-full"
                                      style="width: <?= $largura ?>%; background-color: <?= View::e($linha['cor_hex']) ?>"></span>
                            </span>
                        </span>

                        <?php if ((int) $linha['ativo'] === 1): ?>
                            <form method="post" action="/departamentos/voto" class="shrink-0">
                                <?= Csrf::campo() ?>
                                <input type="hidden" name="department_id" value="<?= (int) $linha['id'] ?>">
                                <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">
                                <button type="submit"
                                        title="Registar mais uma assistência de <?= View::e($linha['nome']) ?>"
                                        class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-marinho-600 hover:text-marinho-800">
                                    +1
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">

        <!-- Evolução das últimas semanas -->
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Últimas 8 semanas
            </h2>

            <?php if ($totalSempre === 0): ?>
                <p class="text-sm text-slate-500">Sem registos para mostrar.</p>
            <?php else: ?>
                <div class="flex h-32 items-end gap-2">
                    <?php foreach ($evolucao as $ponto): ?>
                        <?php $altura = (int) round((int) $ponto['votos'] / $maximoEvolucao * 100); ?>
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <span class="text-[10px] font-semibold text-slate-500"><?= (int) $ponto['votos'] ?></span>
                            <div class="flex w-full flex-1 items-end">
                                <div class="w-full rounded-t bg-marinho-800/80"
                                     style="height: <?= max(2, $altura) ?>%"></div>
                            </div>
                            <span class="text-[10px] text-slate-400"><?= View::e($ponto['rotulo']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Quem registou -->
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Quem registou
            </h2>

            <?php if ($porAutor === []): ?>
                <p class="text-sm text-slate-500">Ninguém registou assistências neste período.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($porAutor as $autor): ?>
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-slate-700"><?= View::e($autor['autor']) ?></span>
                            <span class="font-semibold text-slate-600"><?= (int) $autor['votos'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <!-- Últimos registos -->
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">
            Últimos registos
        </h2>

        <?php if ($recentes === []): ?>
            <p class="text-sm text-slate-500">Ainda não há registos.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($recentes as $voto): ?>
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                  style="background-color: <?= View::e($voto['cor_hex']) ?>"></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-slate-700">
                                    <?= View::e($voto['departamento']) ?>
                                    <?php if (!empty($voto['nota'])): ?>
                                        <span class="text-slate-400">— <?= View::e($voto['nota']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="block text-[11px] text-slate-400">
                                    <?= View::e(date('d/m/Y H:i', strtotime((string) $voto['created_at']))) ?>
                                    · <?= View::e($voto['autor'] ?? '—') ?>
                                </span>
                            </span>
                        </span>

                        <?php if ($ehAdmin || (int) ($voto['user_id'] ?? 0) === $eu): ?>
                            <form method="post" action="/departamentos/votos/<?= (int) $voto['id'] ?>/anular"
                                  class="shrink-0"
                                  onsubmit="return confirm('Anular este registo de «<?= View::e($voto['departamento']) ?>»?');">
                                <?= Csrf::campo() ?>
                                <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">
                                <button type="submit"
                                        class="text-xs font-medium text-slate-400 underline-offset-2 hover:text-rose-600 hover:underline">
                                    Anular
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Gestão dos departamentos -->
    <?php if ($ehAdmin): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Departamentos
            </h2>
            <p class="mb-4 text-xs text-slate-500">
                Desativar tira o departamento do quadro de registo sem apagar a contagem.
                Eliminar só é possível enquanto não tiver assistências registadas.
            </p>

            <form method="post" action="/departamentos" class="mb-5 flex flex-wrap items-end gap-2">
                <?= Csrf::campo() ?>
                <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">

                <label class="block min-w-[14rem] flex-1">
                    <span class="mb-1 block text-[11px] text-slate-500">Nome</span>
                    <input type="text" name="nome" required maxlength="80"
                           value="<?= View::e($antigos['nome'] ?? '') ?>"
                           class="<?= $classeCampo ?> w-full">
                </label>

                <label class="block">
                    <span class="mb-1 block text-[11px] text-slate-500">Cor</span>
                    <select name="cor_hex" class="<?= $classeCampo ?>">
                        <?php foreach (Tag::paleta() as $cor): ?>
                            <option value="<?= View::e($cor['hex']) ?>"><?= View::e($cor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit"
                        class="rounded-lg border border-marinho-800 px-4 py-2 text-sm font-semibold text-marinho-800 hover:bg-marinho-50">
                    + Criar departamento
                </button>
            </form>

            <ul class="divide-y divide-slate-100">
                <?php foreach ($classificacao as $departamento): ?>
                    <?php $inativo = (int) $departamento['ativo'] === 0; ?>
                    <li class="flex flex-wrap items-center gap-2 py-2.5">
                        <form method="post" action="/departamentos/<?= (int) $departamento['id'] ?>"
                              class="flex flex-1 flex-wrap items-center gap-2">
                            <?= Csrf::campo() ?>
                            <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">

                            <span class="h-3 w-3 shrink-0 rounded-full"
                                  style="background-color: <?= View::e($departamento['cor_hex']) ?>"></span>

                            <input type="text" name="nome" required maxlength="80"
                                   value="<?= View::e($departamento['nome']) ?>"
                                   class="<?= $classeCampo ?> min-w-[12rem] flex-1 <?= $inativo ? 'text-slate-400' : '' ?>">

                            <select name="cor_hex" class="<?= $classeCampo ?>">
                                <?php foreach (Tag::paleta() as $cor): ?>
                                    <option value="<?= View::e($cor['hex']) ?>"
                                        <?= strtoupper((string) $departamento['cor_hex']) === strtoupper($cor['hex']) ? 'selected' : '' ?>>
                                        <?= View::e($cor['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <span class="w-24 shrink-0 text-right text-xs text-slate-400">
                                <?= (int) $departamento['votos'] ?> no período
                            </span>

                            <button type="submit"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                Guardar
                            </button>
                        </form>

                        <form method="post" action="/departamentos/<?= (int) $departamento['id'] ?>/alternar" class="shrink-0">
                            <?= Csrf::campo() ?>
                            <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">
                            <button type="submit"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                <?= $inativo ? 'Reativar' : 'Desativar' ?>
                            </button>
                        </form>

                        <form method="post" action="/departamentos/<?= (int) $departamento['id'] ?>/eliminar" class="shrink-0"
                              onsubmit="return confirm('Eliminar o departamento «<?= View::e($departamento['nome']) ?>»?');">
                            <?= Csrf::campo() ?>
                            <input type="hidden" name="periodo" value="<?= View::e($periodo) ?>">
                            <button type="submit"
                                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                Eliminar
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
