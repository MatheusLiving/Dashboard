<?php

/**
 * Vista de um relatório gravado, na ordem das secções do template .docx.
 *
 * @var array<string, mixed>       $relatorio
 * @var array<string, mixed>       $linhas
 * @var list<array<string, mixed>> $exportacoes
 * @var string                     $rotulo
 * @var bool                       $podeEditar
 */

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
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-marinho-800">Ficheiros gerados</h2>

        <?php if ($exportacoes === []): ?>
            <p class="text-sm text-slate-500">
                Ainda não foi gerado nenhum ficheiro a partir deste relatório.
                <span class="text-slate-400">A geração do .docx entra na fase seguinte.</span>
            </p>
        <?php else: ?>
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
</div>
