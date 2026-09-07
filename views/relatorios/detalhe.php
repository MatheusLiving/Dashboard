<?php

/**
 * Vista de um relatório gravado, na ordem das secções do template .docx.
 *
 * @var array<string, mixed>       $relatorio
 * @var array<string, mixed>       $linhas
 * @var list<array<string, mixed>> $exportacoes
 * @var list<array<string, mixed>> $envios
 * @var string                     $rotulo
 * @var bool                       $podeEditar
 * @var bool                       $emailAtivo
 * @var string                     $assuntoEmail
 * @var array<string, mixed>       $antigos
 */

use App\Core\Csrf;
use App\Core\Semana;
use App\Core\View;
use App\Models\Report;

View::titulo('Relatório ' . Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana']));

$entregue = $relatorio['status'] === Report::ENTREGUE;

/** Desenha uma secção de texto livre, com o estado vazio tratado. */
$texto = static function (?string $conteudo): string {
    if ($conteudo === null || trim($conteudo) === '') {
        return '<p class="text-sm italic text-slate-400">Sem conteúdo.</p>';
    }

    return '<p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">' . View::nl($conteudo) . '</p>';
};

/** Desenha uma tabela de secção a partir das colunas indicadas. */
$tabela = static function (array $registos, array $colunas, string $vazio): string {
    if ($registos === []) {
        return '<p class="text-sm italic text-slate-400">' . View::e($vazio) . '</p>';
    }

    $html = '<div class="overflow-x-auto rounded-lg border border-slate-200"><table class="w-full min-w-[36rem] border-collapse text-sm">';
    $html .= '<thead><tr class="bg-marinho-800 text-left text-white">';

    foreach ($colunas as $coluna) {
        $html .= '<th class="px-3 py-2 text-xs font-semibold">' . View::e($coluna['rotulo']) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($registos as $indice => $registo) {
        // Zebra igual à do template .docx.
        $fundo = $indice % 2 === 0 ? 'bg-white' : 'bg-slate-50';
        $html .= '<tr class="border-t border-slate-100 ' . $fundo . '">';

        foreach ($colunas as $coluna) {
            $valor = $registo[$coluna['campo']] ?? null;

            if (isset($coluna['formato'])) {
                $valor = ($coluna['formato'])($valor);
            }

            $html .= '<td class="px-3 py-2 align-top text-slate-700">'
                . ($valor === null || $valor === '' ? '<span class="text-slate-300">—</span>' : View::nl((string) $valor))
                . '</td>';
        }

        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
};

$dataCurta = static fn (mixed $v): string => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$minutos   = static fn (mixed $v): string => $v ? Semana::minutosParaTexto((int) $v) : '';
?>
<div class="mx-auto max-w-4xl space-y-5">

    <nav class="text-xs text-slate-400">
        <a href="/relatorios" class="hover:text-marinho-800">Relatórios</a>
        <span class="mx-1">/</span>
        <span class="text-slate-600"><?= View::e(Semana::rotuloCurto((int) $relatorio['ano'], (int) $relatorio['numero_semana'])) ?></span>
    </nav>

    <?php
    // A versão mais recente do ficheiro, para os botões do topo.
    $ultimo = $exportacoes[0] ?? null;
    ?>

    <!-- Ações sobre o ficheiro -->
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3">
        <?php if ($ultimo !== null): ?>
            <a href="/relatorios/download?id=<?= (int) $ultimo['id'] ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                </svg>
                Descarregar .docx
            </a>
        <?php else: ?>
            <form method="post" action="/relatorios/<?= (int) $relatorio['id'] ?>/gerar">
                <?= Csrf::campo() ?>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                    Gerar .docx
                </button>
            </form>
        <?php endif; ?>

        <?php if ($emailAtivo): ?>
            <button type="button" data-abrir-email
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M3 6h18v12H3z"/>
                </svg>
                Enviar por email
            </button>
        <?php else: ?>
            <span class="text-xs text-slate-400" title="Configure as variáveis MAIL_* no ficheiro .env">
                Envio por email não configurado
            </span>
        <?php endif; ?>

        <?php if (count($envios) > 0): ?>
            <span class="ml-auto text-xs text-slate-400">
                enviado <?= count($envios) ?> vez<?= count($envios) === 1 ? '' : 'es' ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Formulário de envio, escondido até ser pedido -->
    <?php if ($emailAtivo): ?>
        <section data-painel-email class="hidden rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Enviar por email
            </h2>
            <p class="mb-4 text-xs text-slate-500">
                O ficheiro <strong><?= View::e($ultimo !== null ? basename((string) $ultimo['caminho_arquivo']) : 'a gerar') ?></strong>
                segue em anexo. As respostas voltam para o seu endereço.
            </p>

            <form method="post" action="/relatorios/<?= (int) $relatorio['id'] ?>/email" class="space-y-4" novalidate>
                <?= Csrf::campo() ?>

                <div>
                    <label for="destinatarios" class="mb-1 block text-xs font-medium text-slate-600">
                        Destinatários
                    </label>
                    <input type="text" id="destinatarios" name="destinatarios" required
                           value="<?= View::e($antigos['destinatarios'] ?? '') ?>"
                           placeholder="chefia@empresa.pt, direcao@empresa.pt"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                    <p class="mt-1 text-[11px] text-slate-400">
                        Separe vários endereços por vírgula. Máximo de 10 por envio.
                    </p>
                </div>

                <div>
                    <label for="assunto" class="mb-1 block text-xs font-medium text-slate-600">Assunto</label>
                    <input type="text" id="assunto" name="assunto" required maxlength="255"
                           value="<?= View::e($antigos['assunto'] ?? $assuntoEmail) ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                </div>

                <div>
                    <label for="mensagem" class="mb-1 block text-xs font-medium text-slate-600">
                        Mensagem <span class="font-normal text-slate-400">(opcional)</span>
                    </label>
                    <textarea id="mensagem" name="mensagem" rows="3" maxlength="5000"
                              placeholder="Uma nota para acompanhar o relatório…"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100"><?= View::e($antigos['mensagem'] ?? '') ?></textarea>
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

    <?php if ($entregue): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
            Relatório entregue. O conteúdo está congelado: renomear um projeto ou apagar uma tarefa
            já não altera o que aqui está escrito.
        </div>
    <?php else: ?>
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5">
            <span class="text-sm text-amber-800">Este relatório ainda é um rascunho.</span>
            <?php if ($podeEditar): ?>
                <a href="/relatorios/nova?ano=<?= (int) $relatorio['ano'] ?>&semana=<?= (int) $relatorio['numero_semana'] ?>"
                   class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-marinho-700">
                    Continuar a editar
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <article class="space-y-6 rounded-xl border border-slate-200 bg-white p-8">

        <!-- Cabeçalho -->
        <header class="border-b border-slate-200 pb-5 text-center">
            <h1 class="text-xl font-bold text-marinho-800">Relatório Semanal de Atividade</h1>
            <p class="mt-1 text-sm text-slate-500">Equipa de Tecnologias de Informação (TI)</p>

            <div class="mt-5 grid gap-2 text-left sm:grid-cols-2">
                <div class="flex gap-2 rounded bg-marinho-50 px-3 py-2">
                    <span class="text-xs font-semibold text-marinho-800">Colaborador:</span>
                    <span class="text-xs text-slate-700"><?= View::e($relatorio['colaborador']) ?></span>
                </div>
                <div class="flex gap-2 rounded bg-marinho-50 px-3 py-2">
                    <span class="text-xs font-semibold text-marinho-800">Semana / Período:</span>
                    <span class="text-xs text-slate-700"><?= View::e($rotulo) ?></span>
                </div>
                <div class="flex gap-2 rounded bg-marinho-50 px-3 py-2">
                    <span class="text-xs font-semibold text-marinho-800">Função / Cargo:</span>
                    <span class="text-xs text-slate-700"><?= View::e($relatorio['funcao_cargo'] ?: '—') ?></span>
                </div>
                <div class="flex gap-2 rounded bg-marinho-50 px-3 py-2">
                    <span class="text-xs font-semibold text-marinho-800">Data de entrega:</span>
                    <span class="text-xs text-slate-700"><?= View::e($dataCurta($relatorio['data_entrega']) ?: '—') ?></span>
                </div>
            </div>
        </header>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">1. Resumo Executivo</h2>
            <?= $texto($relatorio['resumo_executivo']) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">2. Atividades Realizadas</h2>
            <?= $tabela(
                $linhas['report_activities'] ?? [],
                [
                    ['campo' => 'data',               'rotulo' => 'Data',               'formato' => $dataCurta],
                    ['campo' => 'descricao',          'rotulo' => 'Tarefa / Descrição'],
                    ['campo' => 'projeto_area',       'rotulo' => 'Projeto / Área'],
                    ['campo' => 'estado',             'rotulo' => 'Estado'],
                    ['campo' => 'tempo_dedicado_min', 'rotulo' => 'Tempo dedicado',     'formato' => $minutos],
                ],
                'Sem atividades registadas nesta semana.'
            ) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">3. Incidentes e Pedidos de Suporte</h2>
            <?= $tabela(
                $linhas['report_incidents'] ?? [],
                [
                    ['campo' => 'descricao',       'rotulo' => 'Descrição'],
                    ['campo' => 'prioridade',      'rotulo' => 'Prioridade'],
                    ['campo' => 'estado',          'rotulo' => 'Estado'],
                    ['campo' => 'resolucao_notas', 'rotulo' => 'Resolução / Notas'],
                ],
                'Sem incidentes nesta semana.'
            ) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">4. Projetos em Curso</h2>
            <?= $tabela(
                $linhas['report_projects'] ?? [],
                [
                    ['campo' => 'nome_snapshot',   'rotulo' => 'Projeto'],
                    ['campo' => 'progresso',       'rotulo' => 'Progresso'],
                    ['campo' => 'proximos_passos', 'rotulo' => 'Próximos Passos'],
                    ['campo' => 'observacoes',     'rotulo' => 'Observações'],
                ],
                'Sem projetos em curso nesta semana.'
            ) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">5. Bloqueios, Riscos e Dependências</h2>
            <?= $texto($relatorio['bloqueios_riscos']) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">Dificuldades Encontradas</h2>
            <?= $texto($relatorio['dificuldades']) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">6. Sugestões de Melhoria</h2>
            <?php if ((int) $relatorio['tem_sugestao'] === 1): ?>
                <?= $texto($relatorio['sugestao_texto']) ?>
            <?php else: ?>
                <p class="text-sm italic text-slate-500">Não há sugestões nesta semana.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">7. Planeamento para a Próxima Semana</h2>
            <?= $tabela(
                $linhas['report_next_week'] ?? [],
                [
                    ['campo' => 'tarefa',     'rotulo' => 'Tarefa / Prioridade Prevista'],
                    ['campo' => 'prioridade', 'rotulo' => 'Prioridade'],
                    ['campo' => 'prazo',      'rotulo' => 'Prazo', 'formato' => $dataCurta],
                ],
                'Sem tarefas previstas.'
            ) ?>
        </section>

        <section>
            <h2 class="mb-2 border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">8. Observações Adicionais</h2>
            <?= $texto($relatorio['observacoes_adicionais']) ?>
        </section>
    </article>

    <!-- Ficheiros gerados -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-marinho-800">Ficheiros gerados</h2>

            <form method="post" action="/relatorios/<?= (int) $relatorio['id'] ?>/gerar">
                <?= Csrf::campo() ?>
                <button type="submit"
                        class="rounded-lg bg-marinho-800 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-marinho-700">
                    <?= $exportacoes === [] ? 'Gerar .docx' : 'Gerar nova versão' ?>
                </button>
            </form>
        </div>

        <?php if ($exportacoes === []): ?>
            <p class="text-sm text-slate-500">
                Ainda não foi gerado nenhum ficheiro a partir deste relatório.
            </p>
        <?php else: ?>
            <p class="mb-2 text-xs text-slate-400">
                Cada geração produz um ficheiro novo — as versões anteriores mantêm-se.
            </p>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($exportacoes as $exportacao): ?>
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate text-sm text-slate-700">
                                <?= View::e(basename((string) $exportacao['caminho_arquivo'])) ?>
                            </span>
                            <span class="block text-[11px] text-slate-400">
                                <?= View::e(date('d/m/Y H:i', strtotime((string) $exportacao['gerado_em']))) ?>
                                · <?= View::e($exportacao['gerado_por_nome'] ?? '—') ?>
                                · <span class="font-mono"><?= View::e(substr((string) $exportacao['hash'], 0, 12)) ?>…</span>
                            </span>
                        </span>
                        <a href="/relatorios/download?id=<?= (int) $exportacao['id'] ?>"
                           class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Descarregar
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Histórico de envios -->
    <?php if ($envios !== []): ?>
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">
                Enviado por email
            </h2>

            <ul class="divide-y divide-slate-100">
                <?php foreach ($envios as $envio): ?>
                    <li class="py-2.5">
                        <p class="text-sm text-slate-700"><?= View::e($envio['destinatarios']) ?></p>
                        <p class="text-[11px] text-slate-400">
                            <?= View::e(date('d/m/Y H:i', strtotime((string) $envio['enviado_em']))) ?>
                            · por <?= View::e($envio['enviado_por_nome'] ?? '—') ?>
                            · <?= View::e($envio['assunto']) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
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
