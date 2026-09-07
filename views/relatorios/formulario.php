<?php

/**
 * Formulário do relatório semanal.
 *
 * Segue a estrutura do template .docx: cabeçalho, as oito secções numeradas
 * e, entre a 5 e a 6, a secção de dificuldades.
 *
 * @var array<string, mixed>|null  $relatorio
 * @var int                        $ano
 * @var int                        $semana
 * @var array{inicio: string, fim: string} $intervalo
 * @var string                     $rotulo
 * @var list<array{ano: int, semana: int, rotulo: string}> $semanas
 * @var array<string, mixed>       $linhas
 * @var string|null                $sugestaoAuto
 * @var array{tarefas: int, concluidas: int, minutos: int, incidentes: int} $resumo
 * @var array<string, mixed>       $utilizador
 * @var string                     $dataEntrega
 * @var array<string, mixed>       $antigos
 */

use App\Core\Csrf;
use App\Core\Semana;
use App\Core\View;

View::titulo('Relatório semanal');

/** Lê um campo, dando prioridade ao que foi submetido antes de um erro. */
$valor = static function (string $campo, mixed $omissao = '') use ($antigos, $relatorio): string {
    if (array_key_exists($campo, $antigos)) {
        return (string) $antigos[$campo];
    }

    if ($relatorio !== null && array_key_exists($campo, $relatorio)) {
        return (string) ($relatorio[$campo] ?? '');
    }

    return (string) $omissao;
};

$temSugestao = array_key_exists('tem_sugestao', $antigos)
    ? $antigos['tem_sugestao'] === '1'
    : ($relatorio !== null && (int) $relatorio['tem_sugestao'] === 1);

$classeTexto = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';

$cartoes = [
    ['rotulo' => 'Tarefas com atividade', 'valor' => (string) $resumo['tarefas']],
    ['rotulo' => 'Concluídas na semana',  'valor' => (string) $resumo['concluidas']],
    ['rotulo' => 'Tempo registado',       'valor' => Semana::minutosParaTexto($resumo['minutos'])],
    ['rotulo' => 'Incidentes/Suporte',    'valor' => (string) $resumo['incidentes']],
];
?>
<form method="post" action="/relatorios" class="space-y-6" novalidate data-form-relatorio>
    <?= Csrf::campo() ?>
    <input type="hidden" name="ano" value="<?= (int) $ano ?>">
    <input type="hidden" name="numero_semana" value="<?= (int) $semana ?>">
    <input type="hidden" name="accao" value="rascunho" data-campo-accao>

    <!-- Escolha da semana e cabeçalho do relatório -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Relatório Semanal de Atividade</h2>
                <p class="mt-0.5 text-sm text-slate-500"><?= View::e($rotulo) ?></p>
            </div>

            <div class="flex items-end gap-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-slate-600">Semana</span>
                    <select data-seletor-semana class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                        <?php foreach ($semanas as $opcao): ?>
                            <option value="<?= $opcao['ano'] ?>-<?= $opcao['semana'] ?>"
                                <?= ($opcao['ano'] === $ano && $opcao['semana'] === $semana) ? 'selected' : '' ?>>
                                <?= View::e($opcao['rotulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($relatorio !== null): ?>
                    <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700">
                        rascunho guardado
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cabeçalho, como no template -->
        <div class="grid gap-3 rounded-lg bg-marinho-50 p-4 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold text-marinho-800">Colaborador</p>
                <p class="text-sm text-slate-700"><?= View::e($utilizador['nome']) ?></p>
            </div>
            <div>
                <p class="text-xs font-semibold text-marinho-800">Semana / Período</p>
                <p class="text-sm text-slate-700">
                    <?= View::e(date('d/m/Y', strtotime($intervalo['inicio']))) ?>
                    a
                    <?= View::e(date('d/m/Y', strtotime($intervalo['fim']))) ?>
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold text-marinho-800">Função / Cargo</p>
                <p class="text-sm text-slate-700">
                    <?= View::e($utilizador['funcao_cargo'] ?: '—') ?>
                    <a href="/perfil" class="ml-1 text-xs text-marinho-600 underline-offset-2 hover:underline">alterar</a>
                </p>
            </div>
            <div>
                <label for="data_entrega" class="text-xs font-semibold text-marinho-800">Data de entrega</label>
                <input type="date" id="data_entrega" name="data_entrega"
                       value="<?= View::e($valor('data_entrega', $dataEntrega)) ?>"
                       class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
            </div>
        </div>

        <!-- Números da semana -->
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($cartoes as $cartao): ?>
                <div class="rounded-lg border border-slate-200 px-3 py-2.5">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400"><?= View::e($cartao['rotulo']) ?></p>
                    <p class="mt-0.5 text-lg font-semibold text-slate-900"><?= View::e($cartao['valor']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 1. Resumo Executivo -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h3 class="mb-1 text-sm font-semibold text-marinho-800">1. Resumo Executivo</h3>
        <p class="mb-3 text-xs italic text-slate-500">
            Breve súmula (3-5 linhas) da semana: o que foi mais relevante, principais entregas e estado geral.
        </p>
        <textarea name="resumo_executivo" rows="4" class="<?= $classeTexto ?>"
                  placeholder="Esta semana…"><?= View::e($valor('resumo_executivo')) ?></textarea>
        <p class="mt-1 text-[11px] text-slate-400">Obrigatório para entregar; um rascunho pode ficar em branco.</p>
    </section>

    <!-- 2. Atividades Realizadas -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-1 flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-marinho-800">2. Atividades Realizadas</h3>
            <button type="button" data-repor-seccao="report_activities"
                    class="text-xs text-slate-400 underline-offset-2 hover:text-marinho-800 hover:underline">
                Repor a partir do quadro
            </button>
        </div>
        <p class="mb-3 text-xs italic text-slate-500">
            Liste as principais tarefas concluídas ou em curso durante a semana.
        </p>

        <?= View::parcial('partials/tabela-relatorio', [
            'tabela'  => 'report_activities',
            'ocultos' => ['task_id'],
            'colunas' => [
                ['campo' => 'data',               'rotulo' => 'Data',              'tipo' => 'date',   'largura' => '9rem'],
                ['campo' => 'descricao',          'rotulo' => 'Tarefa / Descrição', 'placeholder' => 'O que foi feito'],
                ['campo' => 'projeto_area',       'rotulo' => 'Projeto / Área',    'largura' => '11rem'],
                ['campo' => 'estado',             'rotulo' => 'Estado',            'largura' => '8rem'],
                ['campo' => 'tempo_dedicado_min', 'rotulo' => 'Tempo (min)',       'tipo' => 'number', 'largura' => '7rem'],
            ],
            'linhas' => $linhas['report_activities'] ?? [],
            'vazio'  => 'Sem atividades registadas nesta semana. Acrescente as que forem precisas.',
        ]) ?>
    </section>

    <!-- 3. Incidentes e Pedidos de Suporte -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-1 flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-marinho-800">3. Incidentes e Pedidos de Suporte</h3>
            <button type="button" data-repor-seccao="report_incidents"
                    class="text-xs text-slate-400 underline-offset-2 hover:text-marinho-800 hover:underline">
                Repor a partir do quadro
            </button>
        </div>
        <p class="mb-3 text-xs italic text-slate-500">
            Resumo dos tickets/incidentes tratados: novos, resolvidos e em aberto.
        </p>

        <?= View::parcial('partials/tabela-relatorio', [
            'tabela'  => 'report_incidents',
            'ocultos' => ['task_id'],
            'colunas' => [
                ['campo' => 'descricao',       'rotulo' => 'Descrição',        'placeholder' => 'O que aconteceu'],
                ['campo' => 'prioridade',      'rotulo' => 'Prioridade',       'largura' => '8rem'],
                ['campo' => 'estado',          'rotulo' => 'Estado',           'largura' => '8rem'],
                ['campo' => 'resolucao_notas', 'rotulo' => 'Resolução / Notas', 'tipo' => 'textarea'],
            ],
            'linhas' => $linhas['report_incidents'] ?? [],
            'vazio'  => 'Sem incidentes nesta semana.',
        ]) ?>
    </section>

    <!-- 4. Projetos em Curso -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-1 flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-marinho-800">4. Projetos em Curso</h3>
            <button type="button" data-repor-seccao="report_projects"
                    class="text-xs text-slate-400 underline-offset-2 hover:text-marinho-800 hover:underline">
                Repor a partir do quadro
            </button>
        </div>
        <p class="mb-3 text-xs italic text-slate-500">
            Ponto de situação dos projetos ativos (ex.: migrações, implementações, manutenções planeadas).
        </p>

        <?= View::parcial('partials/tabela-relatorio', [
            'tabela'  => 'report_projects',
            'ocultos' => ['project_id'],
            'colunas' => [
                ['campo' => 'nome_snapshot',   'rotulo' => 'Projeto',         'largura' => '14rem'],
                ['campo' => 'progresso',       'rotulo' => 'Progresso',       'largura' => '7rem'],
                ['campo' => 'proximos_passos', 'rotulo' => 'Próximos Passos', 'tipo' => 'textarea'],
                ['campo' => 'observacoes',     'rotulo' => 'Observações',     'tipo' => 'textarea'],
            ],
            'linhas' => $linhas['report_projects'] ?? [],
            'vazio'  => 'Sem projetos ativos associados a si nesta semana.',
        ]) ?>
    </section>

    <!-- 5. Bloqueios, Riscos e Dependências -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h3 class="mb-1 text-sm font-semibold text-marinho-800">5. Bloqueios, Riscos e Dependências</h3>
        <p class="mb-3 text-xs italic text-slate-500">
            Identifique obstáculos que impedem ou atrasam o trabalho, e o que é necessário para os resolver.
        </p>
        <textarea name="bloqueios_riscos" rows="3" class="<?= $classeTexto ?>"><?= View::e($valor('bloqueios_riscos')) ?></textarea>
    </section>

    <!-- Dificuldades Encontradas (sem número, entre a 5 e a 6) -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h3 class="mb-1 text-sm font-semibold text-marinho-800">Dificuldades Encontradas</h3>
        <p class="mb-3 text-xs italic text-slate-500">
            Quais foram as dificuldades encontradas durante o trabalho?
        </p>
        <textarea name="dificuldades" rows="3" class="<?= $classeTexto ?>"><?= View::e($valor('dificuldades', $sugestaoAuto ?? '')) ?></textarea>
        <?php if ($relatorio === null && !empty($sugestaoAuto)): ?>
            <p class="mt-1 text-[11px] text-slate-400">
                Reunido a partir do campo «Dificuldades» das tarefas em que trabalhou esta semana.
            </p>
        <?php endif; ?>
    </section>

    <!-- 6. Sugestões de Melhoria -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h3 class="mb-1 text-sm font-semibold text-marinho-800">6. Sugestões de Melhoria</h3>
        <p class="mb-3 text-xs italic text-slate-500">
            Propostas para otimizar processos, ferramentas, segurança ou infraestrutura.
        </p>

        <div class="mb-3 flex gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="radio" name="tem_sugestao" value="0" <?= $temSugestao ? '' : 'checked' ?>
                       data-radio-sugestao class="text-marinho-800 focus:ring-marinho-100">
                Não
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="radio" name="tem_sugestao" value="1" <?= $temSugestao ? 'checked' : '' ?>
                       data-radio-sugestao class="text-marinho-800 focus:ring-marinho-100">
                Sim
            </label>
        </div>

        <div data-caixa-sugestao class="<?= $temSugestao ? '' : 'hidden' ?>">
            <textarea name="sugestao_texto" rows="3" class="<?= $classeTexto ?>"
                      placeholder="Que proposta gostaria de deixar?"><?= View::e($valor('sugestao_texto')) ?></textarea>
            <p class="mt-1 text-[11px] text-slate-400">Obrigatório quando responde «Sim».</p>
        </div>

        <p data-aviso-sem-sugestao class="text-xs text-slate-400 <?= $temSugestao ? 'hidden' : '' ?>">
            O relatório indicará «Não há sugestões nesta semana.»
        </p>
    </section>

    <!-- 7. Planeamento para a Próxima Semana -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="mb-1 flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-marinho-800">7. Planeamento para a Próxima Semana</h3>
            <button type="button" data-repor-seccao="report_next_week"
                    class="text-xs text-slate-400 underline-offset-2 hover:text-marinho-800 hover:underline">
                Repor a partir do quadro
            </button>
        </div>
        <p class="mb-3 text-xs italic text-slate-500">Principais prioridades e tarefas previstas.</p>

        <?= View::parcial('partials/tabela-relatorio', [
            'tabela'  => 'report_next_week',
            'ocultos' => ['task_id'],
            'colunas' => [
                ['campo' => 'tarefa',     'rotulo' => 'Tarefa / Prioridade Prevista'],
                ['campo' => 'prioridade', 'rotulo' => 'Prioridade', 'largura' => '8rem'],
                ['campo' => 'prazo',      'rotulo' => 'Prazo',      'tipo' => 'date', 'largura' => '9rem'],
            ],
            'linhas' => $linhas['report_next_week'] ?? [],
            'vazio'  => 'Sem tarefas previstas. Acrescente as que planeia fazer.',
        ]) ?>
    </section>

    <!-- 8. Observações Adicionais -->
    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h3 class="mb-3 text-sm font-semibold text-marinho-800">8. Observações Adicionais</h3>
        <textarea name="observacoes_adicionais" rows="3" class="<?= $classeTexto ?>"><?= View::e($valor('observacoes_adicionais')) ?></textarea>
    </section>

    <!-- Ações -->
    <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-6 py-4 shadow-lg">
        <p class="text-xs text-slate-500">
            <strong class="text-slate-700">Entregar</strong> congela o conteúdo: deixa de ser possível editar.
        </p>

        <div class="flex gap-2">
            <?php if ($relatorio !== null): ?>
                <button type="button" data-eliminar-rascunho="<?= (int) $relatorio['id'] ?>"
                        class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                    Eliminar rascunho
                </button>
            <?php endif; ?>

            <button type="submit" data-guardar="rascunho"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Guardar rascunho
            </button>
            <button type="submit" data-guardar="entregar"
                    class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700">
                Entregar
            </button>
        </div>
    </div>
</form>

<?php if ($relatorio !== null): ?>
    <form method="post" action="/relatorios/<?= (int) $relatorio['id'] ?>/eliminar" class="hidden" data-form-eliminar>
        <?= Csrf::campo() ?>
    </form>
<?php endif; ?>

<script src="/assets/js/relatorio.js"></script>
