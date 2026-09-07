<?php

/**
 * Campos do formulário de projeto, partilhados pela criação e pela edição.
 *
 * @var array<string, mixed>       $valores       Valores atuais do projeto
 * @var list<array<string, mixed>> $utilizadores
 * @var array<string, string>      $estados
 * @var array<string, string>      $prioridades
 * @var string                     $sufixo        Distingue os id dos campos quando há dois formulários na página
 */

use App\Core\View;

$sufixo      = $sufixo ?? '';
$classeCampo = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none '
    . 'focus:border-marinho-600 focus:ring-2 focus:ring-marinho-100';
$progresso   = (int) ($valores['progresso_pct'] ?? 0);
?>
<div>
    <label for="nome<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Nome</label>
    <input type="text" id="nome<?= View::e($sufixo) ?>" name="nome" required maxlength="180"
           value="<?= View::e($valores['nome'] ?? '') ?>"
           class="<?= $classeCampo ?>" placeholder="ex.: Migração do servidor de correio">
</div>

<div>
    <label for="descricao<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Descrição</label>
    <textarea id="descricao<?= View::e($sufixo) ?>" name="descricao" rows="3"
              class="<?= $classeCampo ?>"
              placeholder="Âmbito, sistemas envolvidos, critérios de conclusão…"><?= View::e($valores['descricao'] ?? '') ?></textarea>
</div>

<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label for="responsavel<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Responsável</label>
        <select id="responsavel<?= View::e($sufixo) ?>" name="responsavel_id" class="<?= $classeCampo ?>">
            <option value="">Sem responsável</option>
            <?php foreach ($utilizadores as $utilizador): ?>
                <option value="<?= (int) $utilizador['id'] ?>"
                    <?= (int) ($valores['responsavel_id'] ?? 0) === (int) $utilizador['id'] ? 'selected' : '' ?>>
                    <?= View::e($utilizador['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="status<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Estado</label>
        <select id="status<?= View::e($sufixo) ?>" name="status" class="<?= $classeCampo ?>">
            <?php foreach ($estados as $chave => $rotulo): ?>
                <option value="<?= View::e($chave) ?>"
                    <?= ($valores['status'] ?? 'planeado') === $chave ? 'selected' : '' ?>>
                    <?= View::e($rotulo) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="prioridade<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Prioridade</label>
        <select id="prioridade<?= View::e($sufixo) ?>" name="prioridade" class="<?= $classeCampo ?>">
            <?php foreach ($prioridades as $chave => $rotulo): ?>
                <option value="<?= View::e($chave) ?>"
                    <?= ($valores['prioridade'] ?? 'media') === $chave ? 'selected' : '' ?>>
                    <?= View::e($rotulo) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="progresso<?= View::e($sufixo) ?>" class="mb-1 flex items-baseline justify-between text-xs font-medium text-slate-600">
            <span>Progresso declarado</span>
            <span class="font-mono text-marinho-800" data-valor-progresso><?= $progresso ?>%</span>
        </label>
        <input type="range" id="progresso<?= View::e($sufixo) ?>" name="progresso_pct"
               min="0" max="100" step="5" value="<?= $progresso ?>"
               data-slider-progresso
               class="mt-2 w-full accent-marinho-800">
    </div>

    <div>
        <label for="data_inicio<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Data de início</label>
        <input type="date" id="data_inicio<?= View::e($sufixo) ?>" name="data_inicio"
               value="<?= View::e($valores['data_inicio'] ?? '') ?>" class="<?= $classeCampo ?>">
    </div>

    <div>
        <label for="prazo<?= View::e($sufixo) ?>" class="mb-1 block text-xs font-medium text-slate-600">Prazo</label>
        <input type="date" id="prazo<?= View::e($sufixo) ?>" name="prazo"
               value="<?= View::e($valores['prazo'] ?? '') ?>" class="<?= $classeCampo ?>">
    </div>
</div>
