<?php

/**
 * Modal de detalhe e edição de uma tarefa.
 *
 * A marcação está toda aqui; o preenchimento e as gravações são feitos em
 * kanban.js, por `fetch`, sem recarregar a página.
 *
 * @var list<array<string, mixed>> $utilizadores
 * @var list<array<string, mixed>> $projetos
 * @var list<array<string, mixed>> $etiquetas
 * @var array<string, string>      $prioridades
 */

use App\Core\View;

$classeCampo = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
?>
<div data-modal-tarefa
     class="fixed inset-0 z-40 hidden items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-8"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-titulo">

    <div class="w-full max-w-3xl rounded-xl bg-white shadow-xl" data-modal-caixa>

        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3.5">
            <h2 id="modal-titulo" class="text-sm font-semibold text-slate-900" data-modal-cabecalho>
                Nova tarefa
            </h2>
            <div class="flex items-center gap-2">
                <button type="button" data-eliminar-tarefa
                        class="hidden rounded-md border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">
                    Eliminar
                </button>
                <button type="button" data-fechar-modal
                        class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Fechar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>
        </header>

        <div data-modal-erro class="hidden border-b border-rose-200 bg-rose-50 px-5 py-2.5 text-sm text-rose-800"></div>

        <form data-form-tarefa class="max-h-[70vh] overflow-y-auto px-5 py-4 scroll-fino" novalidate>
            <input type="hidden" name="id" value="">
            <input type="hidden" name="column_id" value="">

            <div class="grid gap-4 lg:grid-cols-3">

                <!-- Coluna principal -->
                <div class="space-y-4 lg:col-span-2">
                    <div>
                        <label for="t_titulo" class="mb-1 block text-xs font-medium text-slate-600">Título</label>
                        <input type="text" id="t_titulo" name="titulo" required maxlength="200"
                               class="<?= $classeCampo ?>" placeholder="O que é preciso fazer?">
                    </div>

                    <div>
                        <label for="t_descricao" class="mb-1 block text-xs font-medium text-slate-600">Descrição</label>
                        <textarea id="t_descricao" name="descricao" rows="4"
                                  class="<?= $classeCampo ?>" placeholder="Contexto, passos, referências…"></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Etiquetas</label>

                        <div data-tags-escolhidas class="mb-2 flex flex-wrap gap-1.5"></div>

                        <div class="relative">
                            <input type="text" data-tag-procura autocomplete="off"
                                   class="<?= $classeCampo ?>"
                                   placeholder="Escreva para procurar ou criar uma etiqueta…">

                            <ul data-tag-sugestoes
                                class="absolute left-0 right-0 top-full z-10 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg scroll-fino"></ul>
                        </div>
                    </div>

                    <div>
                        <label for="t_dificuldades" class="mb-1 block text-xs font-medium text-slate-600">
                            Dificuldades encontradas
                        </label>
                        <textarea id="t_dificuldades" name="dificuldades" rows="2"
                                  class="<?= $classeCampo ?>"
                                  placeholder="O que travou ou complicou este trabalho?"></textarea>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Este texto fica disponível para o relatório semanal.
                        </p>
                    </div>
                </div>

                <!-- Coluna lateral -->
                <div class="space-y-4">
                    <div>
                        <label for="t_assignee" class="mb-1 block text-xs font-medium text-slate-600">Responsável</label>
                        <select id="t_assignee" name="assignee_id" class="<?= $classeCampo ?>">
                            <option value="">Sem responsável</option>
                            <?php foreach ($utilizadores as $utilizador): ?>
                                <option value="<?= (int) $utilizador['id'] ?>"><?= View::e($utilizador['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="t_projeto" class="mb-1 block text-xs font-medium text-slate-600">Projeto</label>
                        <select id="t_projeto" name="project_id" class="<?= $classeCampo ?>">
                            <option value="">Sem projeto</option>
                            <?php foreach ($projetos as $projeto): ?>
                                <option value="<?= (int) $projeto['id'] ?>"><?= View::e($projeto['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="t_prioridade" class="mb-1 block text-xs font-medium text-slate-600">Prioridade</label>
                        <select id="t_prioridade" name="prioridade" class="<?= $classeCampo ?>">
                            <?php foreach ($prioridades as $chave => $rotulo): ?>
                                <option value="<?= View::e($chave) ?>" <?= $chave === 'media' ? 'selected' : '' ?>>
                                    <?= View::e($rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="t_data_inicio" class="mb-1 block text-xs font-medium text-slate-600">Data de início</label>
                        <input type="date" id="t_data_inicio" name="data_inicio" class="<?= $classeCampo ?>">
                    </div>

                    <div>
                        <label for="t_estimativa" class="mb-1 block text-xs font-medium text-slate-600">
                            Estimativa (minutos)
                        </label>
                        <input type="number" id="t_estimativa" name="estimativa_min" min="0" step="15"
                               class="<?= $classeCampo ?>" placeholder="ex.: 120">
                    </div>

                    <div data-info-estado class="hidden rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-slate-500">
                        <p>Coluna: <strong data-info-coluna class="text-slate-700"></strong></p>
                        <p class="mt-1">Concluída em: <strong data-info-conclusao class="text-slate-700">—</strong></p>
                        <p class="mt-1">Criada por: <strong data-info-criador class="text-slate-700">—</strong></p>
                    </div>
                </div>
            </div>
        </form>

        <!-- Tempo e histórico, apenas em tarefas já existentes -->
        <div data-seccao-existente class="hidden border-t border-slate-200">
            <div class="grid gap-5 px-5 py-4 lg:grid-cols-2">

                <section>
                    <h3 class="mb-2 flex items-baseline justify-between text-xs font-semibold uppercase tracking-wide text-marinho-800">
                        Registo de tempo
                        <span class="text-[11px] font-normal normal-case text-slate-500" data-tempo-total></span>
                    </h3>

                    <form data-form-tempo class="mb-3 flex flex-wrap items-end gap-2">
                        <div class="w-28">
                            <label for="t_duracao" class="mb-1 block text-[11px] text-slate-500">Duração</label>
                            <input type="text" id="t_duracao" name="duracao" required placeholder="1h30"
                                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                        </div>
                        <div class="w-36">
                            <label for="t_data_log" class="mb-1 block text-[11px] text-slate-500">Data</label>
                            <input type="date" id="t_data_log" name="data" required
                                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                        </div>
                        <div class="min-w-[8rem] flex-1">
                            <label for="t_nota" class="mb-1 block text-[11px] text-slate-500">Nota (opcional)</label>
                            <input type="text" id="t_nota" name="nota" maxlength="255"
                                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm outline-none focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100">
                        </div>
                        <button type="submit"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Registar
                        </button>
                    </form>

                    <ul data-lista-tempo class="max-h-40 space-y-1 overflow-y-auto text-sm scroll-fino"></ul>
                </section>

                <section>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-marinho-800">Histórico</h3>
                    <ul data-lista-historico class="max-h-56 space-y-2 overflow-y-auto text-sm scroll-fino"></ul>
                </section>
            </div>
        </div>

        <footer class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-3">
            <button type="button" data-fechar-modal
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                Cancelar
            </button>
            <button type="button" data-guardar-tarefa
                    class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-700 disabled:opacity-60">
                Guardar
            </button>
        </footer>
    </div>
</div>
