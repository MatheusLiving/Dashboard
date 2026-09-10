<?php

/**
 * Conteúdo de um Relatório de Alteração de Software, em modo de leitura.
 *
 * Serve tanto o relatório atual como qualquer versão do histórico: recebe os
 * campos já resolvidos, sem saber de onde vieram.
 *
 * @var array<string, mixed>       $dados       Campos do relatório
 * @var list<array<string, mixed>> $atividades  Linhas da secção 2
 * @var array<string, string>      $rotulosTipo
 * @var array<string, string>      $rotulosPrioridade
 */

use App\Core\View;

/** Uma caixa de texto livre, com o estado vazio tratado. */
$caixa = static function (string $titulo, ?string $conteudo): void {
    ?>
    <div>
        <h3 class="mb-1 text-sm font-semibold text-slate-700"><?= View::e($titulo) ?></h3>
        <?php if ($conteudo === null || trim($conteudo) === ''): ?>
            <p class="rounded-lg border border-dashed border-slate-200 px-3 py-2 text-sm italic text-slate-400">
                Por preencher.
            </p>
        <?php else: ?>
            <p class="whitespace-pre-line rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm leading-relaxed text-slate-700">
                <?= View::nl($conteudo) ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
};

$data = static fn (mixed $v): string => $v ? date('d/m/Y', strtotime((string) $v)) : '—';

/** Linha de caixas de opção, como no documento. */
$opcoes = static function (array $rotulos, ?string $escolhida): string {
    $partes = [];

    foreach ($rotulos as $chave => $rotulo) {
        $partes[] = ($chave === $escolhida ? '☒' : '☐') . ' ' . $rotulo;
    }

    return implode('   ', $partes);
};
?>
<article class="space-y-6 rounded-xl border border-slate-200 bg-white p-8">

    <header class="border-b border-slate-200 pb-5 text-center">
        <h1 class="text-lg font-bold text-marinho-800">
            RELATÓRIO DE PEDIDO E ACOMPANHAMENTO
        </h1>
        <p class="text-lg font-bold text-marinho-800">DE ALTERAÇÃO DE SOFTWARE</p>
        <p class="mt-2 text-xs text-slate-500">
            Documento interno — a preencher pelos programadores antes de qualquer alteração
            e a acompanhar até à entrega ao cliente
        </p>
    </header>

    <!-- Identificação -->
    <table class="w-full border-collapse text-sm">
        <tbody>
            <?php
            $identificacao = [
                'Data de Abertura'            => View::e($data($dados['data_abertura'] ?? null)),
                'Solicitado por (Programador)' => View::e($dados['solicitado_por'] ?: '—'),
                'Cliente / Projeto'           => View::e($dados['cliente_projeto'] ?: '—'),
                'Sistema / Aplicação Afetada' => View::e($dados['sistema_afetado'] ?: '—'),
                'Tipo de Alteração'           => View::e($opcoes($rotulosTipo, $dados['tipo'] ?? null)),
                'Prioridade'                  => View::e($opcoes($rotulosPrioridade, $dados['prioridade'] ?? null)),
                'Data Prevista de Entrega'    => View::e($data($dados['data_prevista'] ?? null)),
            ];
            ?>
            <?php foreach ($identificacao as $rotulo => $valor): ?>
                <tr class="border-b border-slate-100">
                    <th class="w-64 bg-marinho-50 px-3 py-2 text-left text-xs font-semibold text-marinho-800">
                        <?= View::e($rotulo) ?>
                    </th>
                    <td class="px-3 py-2 text-slate-700"><?= $valor ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 1 -->
    <section class="space-y-4">
        <h2 class="border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">
            1. DESCRIÇÃO DO PEDIDO
        </h2>
        <?php $caixa('1.1 Descrição do problema / necessidade', $dados['desc_problema'] ?? null); ?>
        <?php $caixa('1.2 Objetivo da alteração', $dados['objetivo'] ?? null); ?>
        <?php $caixa('1.3 Âmbito da alteração', $dados['ambito'] ?? null); ?>
        <?php $caixa('1.4 Módulos / ficheiros / sistemas afetados', $dados['modulos'] ?? null); ?>
        <?php $caixa('1.5 Análise de impacto e riscos', $dados['impacto_riscos'] ?? null); ?>
        <?php $caixa('1.6 Estimativa de esforço e plano de testes previsto', $dados['estimativa'] ?? null); ?>
        <?php $caixa('1.7 Programador(es) responsável(eis)', $dados['programadores'] ?? null); ?>
    </section>

    <!-- 2 -->
    <section class="space-y-4">
        <h2 class="border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">
            2. ACOMPANHAMENTO DO DESENVOLVIMENTO
        </h2>

        <?php if ($atividades === []): ?>
            <p class="text-sm italic text-slate-400">Ainda sem registos de acompanhamento.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full min-w-[40rem] border-collapse text-sm">
                    <thead>
                        <tr class="bg-marinho-800 text-left text-xs font-semibold text-white">
                            <th class="px-3 py-2">Data</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Descrição da atividade / progresso</th>
                            <th class="px-3 py-2">Responsável</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($atividades as $linha): ?>
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2 align-top text-slate-700"><?= View::e($data($linha['data'] ?? null)) ?></td>
                                <td class="px-3 py-2 align-top text-slate-700"><?= View::e($linha['estado'] ?: '—') ?></td>
                                <td class="px-3 py-2 align-top text-slate-700"><?= View::nl($linha['descricao'] ?? '') ?></td>
                                <td class="px-3 py-2 align-top text-slate-700"><?= View::e($linha['responsavel'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php $caixa('2.1 Desvios em relação ao pedido inicial', $dados['desvios'] ?? null); ?>
    </section>

    <!-- 3 -->
    <section class="space-y-4">
        <h2 class="border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">
            3. TESTES E VALIDAÇÃO
        </h2>
        <?php $caixa('3.1 Testes realizados', $dados['testes_realizados'] ?? null); ?>
        <?php $caixa('3.2 Resultados obtidos / problemas encontrados e correções efetuadas', $dados['resultados'] ?? null); ?>
        <?php $caixa('3.3 Validado por', $dados['validado_por'] ?? null); ?>
    </section>

    <!-- 4 -->
    <section class="space-y-4">
        <h2 class="border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">
            4. RELATÓRIO FINAL DE EXECUÇÃO
        </h2>
        <?php $caixa('4.1 Resumo do que foi efetivamente executado', $dados['resumo_execucao'] ?? null); ?>
        <?php $caixa('4.2 Diferenças em relação ao pedido inicial', $dados['diferencas'] ?? null); ?>
        <?php $caixa('4.3 Notas / instruções para o cliente', $dados['notas_cliente'] ?? null); ?>
        <?php $caixa('4.4 Identificação do(s) commit(s) no Bitbucket', $dados['commits'] ?? null); ?>
        <?php $caixa('4.5 Data de conclusão e programador(es) envolvido(s)', $dados['conclusao_programadores'] ?? null); ?>
    </section>

    <!-- 5 -->
    <section class="space-y-4">
        <h2 class="border-b border-marinho-800 pb-1 text-sm font-bold text-marinho-800">5. ENTREGA</h2>
        <?php $caixa('Entregue ao cliente em (data) / por', $dados['entrega'] ?? null); ?>
    </section>
</article>
