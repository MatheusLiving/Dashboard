<?php

/**
 * Vista de um Relatório de Alteração de Software.
 *
 * @var array<string, mixed>       $relatorio
 * @var list<array<string, mixed>> $atividades
 * @var list<array<string, mixed>> $versoes
 * @var list<array<string, mixed>> $exportacoes
 * @var list<array<string, mixed>> $envios
 * @var array<string, string>      $rotulosEstado
 * @var array<string, string>      $coresEstado
 * @var array<string, string>      $rotulosTipo
 * @var array<string, string>      $rotulosPrioridade
 * @var array<string, string>      $rotulosMotivo
 * @var bool                       $podeEditar
 * @var bool                       $podeDecidir
 * @var bool                       $emailAtivo
 * @var string                     $assuntoEmail
 * @var array<string, mixed>       $antigos
 */

use App\Core\Csrf;
use App\Core\View;
use App\Models\ChangeReport;

View::titulo((string) ($relatorio['referencia'] ?? 'Relatório de alteração'));

$estado   = (string) $relatorio['estado'];
$ultimo   = $exportacoes[0] ?? null;
$decisao  = null;

// A última nota escrita numa decisão é o que o autor precisa de ler primeiro.
foreach ($versoes as $versao) {
    if (in_array($versao['motivo'], ['alteracoes_pedidas', 'aprovacao'], true) && !empty($versao['nota'])) {
        $decisao = $versao;
        break;
    }
}

$classeCampo = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div class="mx-auto max-w-4xl space-y-5">

    <nav class="text-xs text-slate-400">
        <a href="/alteracoes" class="hover:text-marinho-800">Alterações</a>
        <span class="mx-1">/</span>
        <span class="font-mono text-slate-600"><?= View::e($relatorio['referencia'] ?? '') ?></span>
    </nav>

    <!-- Estado e ações -->
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded px-2.5 py-1 text-xs font-semibold <?= View::e($coresEstado[$estado] ?? '') ?>">
                <?= View::e($rotulosEstado[$estado] ?? $estado) ?>
            </span>
            <span class="text-xs text-slate-400">versão <?= (int) $relatorio['versao'] ?></span>

            <?php if (!empty($relatorio['projeto_nome'])): ?>
                <a href="/projetos/<?= (int) $relatorio['project_id'] ?>"
                   class="text-xs text-slate-500 underline-offset-2 hover:text-marinho-800 hover:underline">
                    Projeto: <?= View::e($relatorio['projeto_nome']) ?>
                </a>
            <?php endif; ?>

            <?php if (!empty($relatorio['tarefa_titulo'])): ?>
                <span class="text-xs text-slate-500">Tarefa: <?= View::e($relatorio['tarefa_titulo']) ?></span>
            <?php endif; ?>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <?php if ($ultimo !== null): ?>
                <a href="/alteracoes/download?id=<?= (int) $ultimo['id'] ?>"
                   class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    Descarregar .docx
                </a>
            <?php endif; ?>

            <form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>/gerar">
                <?= Csrf::campo() ?>
                <button type="submit"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <?= $exportacoes === [] ? 'Gerar .docx' : 'Gerar nova versão do .docx' ?>
                </button>
            </form>

            <?php if ($podeEditar && $estado !== ChangeReport::APROVADO): ?>
                <a href="/alteracoes/<?= (int) $relatorio['id'] ?>/editar"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Editar
                </a>
            <?php endif; ?>

            <?php if ($emailAtivo): ?>
                <button type="button" data-abrir-email
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Enviar por email
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Decisão mais recente -->
    <?php if ($decisao !== null): ?>
        <div class="rounded-lg border px-4 py-3
                    <?= $decisao['motivo'] === 'aprovacao'
                            ? 'border-emerald-200 bg-emerald-50'
                            : 'border-amber-200 bg-amber-50' ?>">
            <p class="text-xs font-semibold uppercase tracking-wide
                      <?= $decisao['motivo'] === 'aprovacao' ? 'text-emerald-800' : 'text-amber-800' ?>">
                <?= View::e($rotulosMotivo[$decisao['motivo']] ?? $decisao['motivo']) ?>
                — v<?= (int) $decisao['versao'] ?>,
                <?= View::e(date('d/m/Y H:i', strtotime((string) $decisao['created_at']))) ?>,
                por <?= View::e($decisao['autor'] ?? '—') ?>
            </p>
            <p class="mt-1 whitespace-pre-line text-sm
                      <?= $decisao['motivo'] === 'aprovacao' ? 'text-emerald-900' : 'text-amber-900' ?>">
                <?= View::nl($decisao['nota']) ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Fluxo de aprovação -->
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Aprovação</h2>
        <p class="mb-4 text-xs text-slate-500">
            Cada passo guarda uma versão completa do relatório. O que foi aprovado continua
            a poder ser lido tal como foi aprovado, mesmo depois de o documento mudar.
        </p>

        <?php if ($estado === ChangeReport::EM_APROVACAO && $podeDecidir): ?>
            <div class="space-y-3">
                <p class="text-sm text-slate-600">Este relatório está à espera de decisão.</p>

                <!-- A ação do formulário é a mais conservadora das duas: submeter com
                     Enter, sem carregar em nenhum botão, pede alterações (e exige a nota)
                     em vez de aprovar sem querer. -->
                <form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>/alteracoes" class="space-y-3">
                    <?= Csrf::campo() ?>
                    <textarea name="nota" rows="3" maxlength="2000"
                              placeholder="Nota da decisão. Obrigatória para pedir alterações."
                              class="<?= $classeCampo ?>"><?= View::e($antigos['nota'] ?? '') ?></textarea>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" formaction="/alteracoes/<?= (int) $relatorio['id'] ?>/aprovar"
                                class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Aprovar
                        </button>
                        <button type="submit" formaction="/alteracoes/<?= (int) $relatorio['id'] ?>/alteracoes"
                                class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">
                            Pedir alterações
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($estado === ChangeReport::EM_APROVACAO): ?>
            <p class="text-sm text-slate-600">
                Enviado para aprovação. A decisão é registada por um administrador.
            </p>
        <?php elseif ($estado === ChangeReport::APROVADO): ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-emerald-800">
                    Relatório aprovado. Para o corrigir, é preciso reabri-lo.
                </p>
                <?php if ($podeEditar): ?>
                    <form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>/reabrir"
                          onsubmit="return confirm('Reabrir este relatório aprovado? A versão aprovada mantém-se no histórico.');">
                        <?= Csrf::campo() ?>
                        <button type="submit"
                                class="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
                            Reabrir
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif ($podeEditar): ?>
            <form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>/aprovacao" class="space-y-3">
                <?= Csrf::campo() ?>
                <textarea name="nota" rows="2" maxlength="2000"
                          placeholder="Nota para quem vai aprovar (opcional)."
                          class="<?= $classeCampo ?>"></textarea>
                <button type="submit"
                        class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    Enviar para aprovação
                </button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-500">Só o autor pode enviar este relatório para aprovação.</p>
        <?php endif; ?>
    </section>

    <!-- Formulário de envio por email -->
    <?php if ($emailAtivo): ?>
        <section data-painel-email class="hidden rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Enviar por email</h2>
            <p class="mb-4 text-xs text-slate-500">
                Segue em anexo
                <strong><?= View::e($ultimo !== null ? basename((string) $ultimo['caminho_arquivo']) : 'o ficheiro a gerar') ?></strong>.
                Fica registada a versão enviada.
            </p>

            <form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>/email" class="space-y-4" novalidate>
                <?= Csrf::campo() ?>

                <div>
                    <label for="destinatarios" class="mb-1 block text-xs font-medium text-slate-600">Destinatários</label>
                    <input type="text" id="destinatarios" name="destinatarios" required
                           value="<?= View::e($antigos['destinatarios'] ?? '') ?>"
                           placeholder="cliente@empresa.pt, chefia@empresa.pt" class="<?= $classeCampo ?>">
                    <p class="mt-1 text-[11px] text-slate-400">Separe vários por vírgula. Máximo de 10 por envio.</p>
                </div>

                <div>
                    <label for="assunto" class="mb-1 block text-xs font-medium text-slate-600">Assunto</label>
                    <input type="text" id="assunto" name="assunto" required maxlength="255"
                           value="<?= View::e($antigos['assunto'] ?? $assuntoEmail) ?>" class="<?= $classeCampo ?>">
                </div>

                <div>
                    <label for="mensagem" class="mb-1 block text-xs font-medium text-slate-600">
                        Mensagem <span class="font-normal text-slate-400">(opcional)</span>
                    </label>
                    <textarea id="mensagem" name="mensagem" rows="3" maxlength="5000"
                              class="<?= $classeCampo ?>"><?= View::e($antigos['mensagem'] ?? '') ?></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" data-fechar-email
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                        Enviar
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- O documento -->
    <?= View::parcial('partials/documento-alteracao', [
        'dados'             => $relatorio,
        'atividades'        => $atividades,
        'rotulosTipo'       => $rotulosTipo,
        'rotulosPrioridade' => $rotulosPrioridade,
    ]) ?>

    <!-- Histórico -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">Histórico</h2>
        <p class="mb-3 text-xs text-slate-500">
            Cada versão guarda o relatório por inteiro, tal como estava naquele momento.
        </p>

        <?php if ($versoes === []): ?>
            <p class="text-sm text-slate-500">Ainda não há versões guardadas.</p>
        <?php else: ?>
            <ol class="divide-y divide-slate-100">
                <?php foreach ($versoes as $versao): ?>
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                        <span class="min-w-0">
                            <span class="text-sm font-medium text-slate-700">
                                v<?= (int) $versao['versao'] ?>
                                · <?= View::e($rotulosMotivo[$versao['motivo']] ?? $versao['motivo']) ?>
                            </span>
                            <span class="block text-[11px] text-slate-400">
                                <?= View::e(date('d/m/Y H:i', strtotime((string) $versao['created_at']))) ?>
                                · <?= View::e($versao['autor'] ?? '—') ?>
                                · estado: <?= View::e($rotulosEstado[$versao['estado']] ?? $versao['estado']) ?>
                            </span>
                            <?php if (!empty($versao['nota'])): ?>
                                <span class="mt-1 block whitespace-pre-line text-xs text-slate-600">
                                    <?= View::nl($versao['nota']) ?>
                                </span>
                            <?php endif; ?>
                        </span>

                        <a href="/alteracoes/versoes/<?= (int) $versao['id'] ?>"
                           class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Ver esta versão
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <!-- Ficheiros gerados -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">Ficheiros gerados</h2>

        <?php if ($exportacoes === []): ?>
            <p class="text-sm text-slate-500">Ainda não foi gerado nenhum ficheiro.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($exportacoes as $exportacao): ?>
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate text-sm text-slate-700">
                                <?= View::e(basename((string) $exportacao['caminho_arquivo'])) ?>
                            </span>
                            <span class="block text-[11px] text-slate-400">
                                v<?= (int) $exportacao['versao'] ?>
                                · <?= View::e(date('d/m/Y H:i', strtotime((string) $exportacao['gerado_em']))) ?>
                                · <?= View::e($exportacao['gerado_por_nome'] ?? '—') ?>
                                · <span class="font-mono"><?= View::e(substr((string) $exportacao['hash'], 0, 12)) ?>…</span>
                            </span>
                        </span>
                        <a href="/alteracoes/download?id=<?= (int) $exportacao['id'] ?>"
                           class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Descarregar
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Envios -->
    <?php if ($envios !== []): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">Enviado por email</h2>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($envios as $envio): ?>
                    <li class="py-2.5">
                        <p class="text-sm text-slate-700"><?= View::e($envio['destinatarios']) ?></p>
                        <p class="text-[11px] text-slate-400">
                            v<?= (int) $envio['versao'] ?>
                            · <?= View::e(date('d/m/Y H:i', strtotime((string) $envio['enviado_em']))) ?>
                            · por <?= View::e($envio['enviado_por_nome'] ?? '—') ?>
                            · <?= View::e($envio['assunto']) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- Eliminar -->
    <?php if ($podeEditar): ?>
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
            <div>
                <h2 class="text-sm font-semibold text-rose-800">Eliminar relatório</h2>
                <p class="mt-0.5 text-xs text-rose-700">
                    Desaparecem o conteúdo, todo o histórico de versões, os ficheiros gerados
                    e o registo dos envios. Não há como voltar atrás.
                </p>
            </div>

            <form method="post" action="/alteracoes/<?= (int) $relatorio['id'] ?>/eliminar"
                  onsubmit="return confirm('Eliminar definitivamente <?= View::e($relatorio['referencia'] ?? '') ?> e todo o seu histórico?');">
                <?= Csrf::campo() ?>
                <button type="submit"
                        class="shrink-0 rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                    Eliminar
                </button>
            </form>
        </section>
    <?php endif; ?>
</div>

<script>
    // Abre e fecha o formulário de envio por email.
    (function () {
        var painel = document.querySelector('[data-painel-email]');
        var abrir = document.querySelector('[data-abrir-email]');
        var fechar = document.querySelector('[data-fechar-email]');

        if (!painel || !abrir) {
            return;
        }

        abrir.addEventListener('click', function () {
            painel.classList.toggle('hidden');

            if (!painel.classList.contains('hidden')) {
                painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                painel.querySelector('#destinatarios').focus();
            }
        });

        if (fechar) {
            fechar.addEventListener('click', function () {
                painel.classList.add('hidden');
            });
        }

        // Depois de um erro de validação o formulário reabre já preenchido.
        if (painel.querySelector('#destinatarios').value.trim() !== '') {
            painel.classList.remove('hidden');
        }
    }());
</script>
