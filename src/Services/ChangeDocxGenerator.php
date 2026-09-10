<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\ChangeReport;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Geração do Relatório de Alteração de Software em .docx.
 *
 * Cada geração produz um ficheiro novo — nunca há substituição — e o nome
 * inclui a versão do relatório, para que dois ficheiros do mesmo processo se
 * distingam à vista: `ALT-2026-0007_v3.docx`.
 *
 * Ao contrário do relatório semanal, este documento não precisa de reposição
 * de zebra: no original, as linhas de dados da tabela de acompanhamento não
 * têm sombreado nenhum, só o cabeçalho.
 */
final class ChangeDocxGenerator
{
    /** Marca de campo sem conteúdo. */
    private const VAZIO = '—';

    /** Separador entre as opções das linhas de caixas, igual ao do original. */
    private const SEPARADOR_OPCOES = '   ';

    /**
     * Campos de texto livre: marcador do template => coluna da base de dados.
     *
     * @var array<string, string>
     */
    private const CAMPOS_LIVRES = [
        'desc_problema'           => 'desc_problema',
        'objetivo'                => 'objetivo',
        'ambito'                  => 'ambito',
        'modulos'                 => 'modulos',
        'impacto_riscos'          => 'impacto_riscos',
        'estimativa'              => 'estimativa',
        'programadores'           => 'programadores',
        'desvios'                 => 'desvios',
        'testes_realizados'       => 'testes_realizados',
        'resultados'              => 'resultados',
        'validado_por'            => 'validado_por',
        'resumo_execucao'         => 'resumo_execucao',
        'diferencas'              => 'diferencas',
        'notas_cliente'           => 'notas_cliente',
        'commits'                 => 'commits',
        'conclusao_programadores' => 'conclusao_programadores',
        'entrega'                 => 'entrega',
    ];

    /**
     * Gera o .docx de um relatório de alteração.
     *
     * @param array<string, mixed> $relatorio Linha de `change_reports`
     * @return array{caminho: string, hash: string, nome: string, versao: int}
     */
    public function gerar(array $relatorio): array
    {
        $template = (string) Config::get('alteracao.template');

        if (!is_file($template)) {
            throw new RuntimeException(
                'Template não encontrado em ' . $template
                . '. Execute: php bin/preparar-template.php'
            );
        }

        $id          = (int) $relatorio['id'];
        $processador = new TemplateProcessor($template);

        $this->preencherCabecalho($processador, $relatorio);
        $this->preencherCamposLivres($processador, $relatorio);
        $this->preencherAcompanhamento($processador, ChangeReport::atividades($id));

        $destino = $this->caminhoDeDestino($relatorio);
        $processador->saveAs($destino);

        $hash = hash_file('sha256', $destino);

        if ($hash === false) {
            throw new RuntimeException('Não foi possível calcular o resumo do ficheiro gerado.');
        }

        return [
            'caminho' => $this->caminhoRelativo($destino),
            'hash'    => $hash,
            'nome'    => basename($destino),
            'versao'  => (int) $relatorio['versao'],
        ];
    }

    // -----------------------------------------------------------------------
    // Preenchimento
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $relatorio
     */
    private function preencherCabecalho(TemplateProcessor $processador, array $relatorio): void
    {
        $processador->setValue('data_abertura', $this->texto($this->data($relatorio['data_abertura'] ?? null)));
        $processador->setValue('solicitado_por', $this->texto($relatorio['solicitado_por'] ?? null));
        $processador->setValue('cliente_projeto', $this->texto($relatorio['cliente_projeto'] ?? null));
        $processador->setValue('sistema_afetado', $this->texto($relatorio['sistema_afetado'] ?? null));
        $processador->setValue('data_prevista', $this->texto($this->data($relatorio['data_prevista'] ?? null)));

        // As caixas de opção do original mantêm-se todas; só muda a que fica
        // assinalada. Substituir a linha por um valor solto faria o documento
        // deixar de ser o formulário que a equipa conhece.
        $processador->setValue('tipo_alteracao', $this->texto($this->opcoes(
            ChangeReport::rotulosTipo(),
            (string) ($relatorio['tipo'] ?? '')
        )));

        $processador->setValue('prioridade', $this->texto($this->opcoes(
            ChangeReport::rotulosPrioridade(),
            (string) ($relatorio['prioridade'] ?? '')
        )));
    }

    /**
     * @param array<string, mixed> $relatorio
     */
    private function preencherCamposLivres(TemplateProcessor $processador, array $relatorio): void
    {
        foreach (self::CAMPOS_LIVRES as $marcador => $campo) {
            $valor = $relatorio[$campo] ?? null;

            $processador->setValue(
                $marcador,
                $this->texto(
                    $valor === null || trim((string) $valor) === '' ? self::VAZIO : $valor
                )
            );
        }
    }

    /**
     * Preenche a tabela de acompanhamento do desenvolvimento.
     *
     * @param list<array<string, mixed>> $linhas
     */
    private function preencherAcompanhamento(TemplateProcessor $processador, array $linhas): void
    {
        // Sem registos clona-se na mesma uma linha: deixar a linha-modelo
        // intacta faria aparecer «${ac_descricao}» no documento entregue.
        $total = max(1, count($linhas));

        $processador->cloneRow('ac_descricao', $total);

        for ($i = 1; $i <= $total; $i++) {
            $linha = $linhas[$i - 1] ?? null;

            $processador->setValue('ac_data#' . $i, $this->texto(
                $linha === null ? self::VAZIO : $this->data($linha['data'] ?? null)
            ));
            $processador->setValue('ac_estado#' . $i, $this->texto(
                $linha === null ? self::VAZIO : ($linha['estado'] ?? self::VAZIO)
            ));
            $processador->setValue('ac_descricao#' . $i, $this->texto(
                $linha === null ? self::VAZIO : ($linha['descricao'] ?? self::VAZIO)
            ));
            $processador->setValue('ac_responsavel#' . $i, $this->texto(
                $linha === null ? self::VAZIO : ($linha['responsavel'] ?? self::VAZIO)
            ));
        }
    }

    /**
     * Linha de caixas de opção, com a escolhida assinalada.
     *
     * @param array<string, string> $rotulos
     */
    private function opcoes(array $rotulos, string $escolhida): string
    {
        $partes = [];

        foreach ($rotulos as $chave => $rotulo) {
            $partes[] = ($chave === $escolhida ? '☒' : '☐') . ' ' . $rotulo;
        }

        return implode(self::SEPARADOR_OPCOES, $partes);
    }

    // -----------------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------------

    /**
     * Data no formato português, ou o traço quando não há.
     */
    private function data(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return self::VAZIO;
        }

        $marca = strtotime((string) $valor);

        return $marca === false ? self::VAZIO : date('d/m/Y', $marca);
    }

    /**
     * Prepara um valor para entrar no XML do documento.
     *
     * O escape é feito aqui, e não pelo PhpWord, porque as quebras de linha
     * têm de sair como `<w:br/>` — que não pode ser escapado.
     */
    private function texto(mixed $valor): string
    {
        $texto = trim((string) ($valor ?? ''));

        if ($texto === '') {
            return '';
        }

        $texto = htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return str_replace(["\r\n", "\r", "\n"], '</w:t><w:br/><w:t>', $texto);
    }

    /**
     * Caminho do ficheiro a gerar, sem nunca substituir um existente.
     *
     * @param array<string, mixed> $relatorio
     */
    private function caminhoDeDestino(array $relatorio): string
    {
        $ano   = substr((string) ($relatorio['data_abertura'] ?? date('Y-m-d')), 0, 4);
        $pasta = (string) Config::get('alteracao.saida') . DIRECTORY_SEPARATOR . $ano;

        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $pasta);
        }

        $referencia = (string) ($relatorio['referencia'] ?? ('ALT-' . (int) $relatorio['id']));
        $base       = sprintf('%s_v%d', $this->slug($referencia), (int) $relatorio['versao']);

        $caminho = $pasta . DIRECTORY_SEPARATOR . $base . '.docx';

        // Duas gerações da mesma versão são possíveis (por exemplo, para
        // reenviar): a segunda ganha um sufixo em vez de apagar a primeira.
        $sufixo = 2;

        while (file_exists($caminho)) {
            $caminho = $pasta . DIRECTORY_SEPARATOR . $base . '-' . $sufixo . '.docx';
            $sufixo++;
        }

        return $caminho;
    }

    /**
     * Caminho relativo à raiz do projeto, que é o que fica em base de dados.
     */
    private function caminhoRelativo(string $absoluto): string
    {
        $raiz = Config::raiz() . DIRECTORY_SEPARATOR;

        if (str_starts_with($absoluto, $raiz)) {
            $absoluto = substr($absoluto, strlen($raiz));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $absoluto);
    }

    /**
     * Converte um texto em nome de ficheiro seguro.
     */
    private function slug(string $nome): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($nome)) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'alteracao' : substr($slug, 0, 60);
    }
}
