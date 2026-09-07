<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Semana;
use App\Models\Report;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

/**
 * Geração do relatório semanal em .docx a partir do template.
 *
 * Cada geração produz um ficheiro novo — nunca há substituição — registado
 * em `report_exports` com o respetivo resumo SHA-256.
 */
final class DocxGenerator
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Texto impresso na §6 quando o colaborador respondeu «Não». */
    private const SEM_SUGESTAO = 'Não há sugestões nesta semana.';

    /** Marca de célula sem conteúdo. */
    private const VAZIO = '—';

    /**
     * Definição das quatro tabelas: marcador que identifica a linha-modelo e
     * os campos a preencher em cada coluna.
     *
     * @var array<string, array{tabela: string, marcador: string, colunas: array<string, string>}>
     */
    private const TABELAS = [
        'atividades' => [
            'tabela'   => 'report_activities',
            'marcador' => 'ativ_tarefa',
            'colunas'  => [
                'ativ_data'    => 'data',
                'ativ_tarefa'  => 'descricao',
                'ativ_projeto' => 'projeto_area',
                'ativ_estado'  => 'estado',
                'ativ_tempo'   => 'tempo_dedicado_min',
            ],
        ],
        'incidentes' => [
            'tabela'   => 'report_incidents',
            'marcador' => 'inc_descricao',
            'colunas'  => [
                'inc_descricao'  => 'descricao',
                'inc_prioridade' => 'prioridade',
                'inc_estado'     => 'estado',
                'inc_resolucao'  => 'resolucao_notas',
            ],
        ],
        'projetos' => [
            'tabela'   => 'report_projects',
            'marcador' => 'proj_nome',
            'colunas'  => [
                'proj_nome'      => 'nome_snapshot',
                'proj_progresso' => 'progresso',
                'proj_passos'    => 'proximos_passos',
                'proj_obs'       => 'observacoes',
            ],
        ],
        'proxima' => [
            'tabela'   => 'report_next_week',
            'marcador' => 'prox_tarefa',
            'colunas'  => [
                'prox_tarefa' => 'tarefa',
                'prox_prazo'  => 'prazo',
            ],
        ],
    ];

    /**
     * Gera o .docx de um relatório e devolve o caminho e o resumo do ficheiro.
     *
     * @param array<string, mixed> $relatorio  Linha de `reports` com o colaborador
     * @return array{caminho: string, hash: string, nome: string}
     */
    public function gerar(array $relatorio): array
    {
        $template = (string) Config::get('relatorio.template');

        if (!is_file($template)) {
            throw new RuntimeException(
                'Template não encontrado em ' . $template
                . '. Execute: php bin/preparar-template.php'
            );
        }

        $reportId = (int) $relatorio['id'];
        $linhas   = Report::todasAsLinhas($reportId);

        $processador = new TemplateProcessor($template);

        $this->preencherCabecalho($processador, $relatorio);
        $this->preencherCamposLivres($processador, $relatorio);

        foreach (self::TABELAS as $definicao) {
            $this->preencherTabela($processador, $definicao, $linhas[$definicao['tabela']] ?? []);
        }

        $destino = $this->caminhoDeDestino($relatorio);
        $temp    = $destino . '.tmp';

        $processador->saveAs($temp);

        // O cloneRow copia o sombreado da linha-modelo, pelo que todas as
        // linhas sairiam iguais. A zebra do original é reposta aqui.
        $this->reporZebra($temp);

        if (!rename($temp, $destino)) {
            @unlink($temp);

            throw new RuntimeException('Não foi possível gravar o ficheiro em ' . $destino);
        }

        $hash = hash_file('sha256', $destino);

        if ($hash === false) {
            throw new RuntimeException('Não foi possível calcular o resumo do ficheiro.');
        }

        return [
            'caminho' => $this->caminhoRelativo($destino),
            'hash'    => $hash,
            'nome'    => basename($destino),
        ];
    }

    // -----------------------------------------------------------------------
    // Preenchimento
    // -----------------------------------------------------------------------

    /**
     * Campos do cabeçalho do documento.
     *
     * @param array<string, mixed> $relatorio
     */
    private function preencherCabecalho(TemplateProcessor $processador, array $relatorio): void
    {
        $ano    = (int) $relatorio['ano'];
        $semana = (int) $relatorio['numero_semana'];

        $processador->setValue('colaborador', $this->texto($relatorio['colaborador'] ?? ''));
        $processador->setValue('funcao_cargo', $this->texto($relatorio['funcao_cargo'] ?? self::VAZIO));
        $processador->setValue('semana_periodo', $this->texto(Semana::rotulo($ano, $semana)));
        $processador->setValue(
            'data_entrega',
            $this->texto($this->data($relatorio['data_entrega'] ?? null))
        );
    }

    /**
     * Secções de texto livre.
     *
     * @param array<string, mixed> $relatorio
     */
    private function preencherCamposLivres(TemplateProcessor $processador, array $relatorio): void
    {
        $processador->setValue('resumo_executivo', $this->texto($relatorio['resumo_executivo'] ?? ''));
        $processador->setValue('bloqueios', $this->texto($relatorio['bloqueios_riscos'] ?? ''));
        $processador->setValue('dificuldades', $this->texto($relatorio['dificuldades'] ?? ''));
        $processador->setValue('observacoes', $this->texto($relatorio['observacoes_adicionais'] ?? ''));

        // A §6 imprime a frase combinada quando não há sugestão a registar.
        $sugestao = (int) ($relatorio['tem_sugestao'] ?? 0) === 1
            ? (string) ($relatorio['sugestao_texto'] ?? '')
            : self::SEM_SUGESTAO;

        $processador->setValue('sugestao', $this->texto($sugestao));
    }

    /**
     * Clona a linha-modelo de uma tabela e preenche-a com os registos.
     *
     * @param array{tabela: string, marcador: string, colunas: array<string, string>} $definicao
     * @param list<array<string, mixed>> $registos
     */
    private function preencherTabela(TemplateProcessor $processador, array $definicao, array $registos): void
    {
        // Sem registos, clona-se na mesma uma linha: deixar a linha-modelo
        // intacta faria aparecer os marcadores no documento final.
        $total = max(1, count($registos));

        $processador->cloneRow($definicao['marcador'], $total);

        for ($i = 1; $i <= $total; $i++) {
            $registo = $registos[$i - 1] ?? null;

            foreach ($definicao['colunas'] as $marcador => $campo) {
                $valor = $registo === null ? null : ($registo[$campo] ?? null);

                $processador->setValue(
                    $marcador . '#' . $i,
                    $this->texto($this->formatar($campo, $valor))
                );
            }
        }
    }

    /**
     * Dá a cada campo a forma com que aparece no documento.
     */
    private function formatar(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return self::VAZIO;
        }

        return match ($campo) {
            'data', 'prazo'          => $this->data($valor),
            'tempo_dedicado_min'     => Semana::minutosParaTexto((int) $valor),
            default                  => (string) $valor,
        };
    }

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

    // -----------------------------------------------------------------------
    // Zebra
    // -----------------------------------------------------------------------

    /**
     * Repõe o sombreado alternado das tabelas de dados.
     *
     * Só são tocadas as tabelas cujo cabeçalho tem a cor de fundo do template;
     * as caixas de texto livre e a tabela de identificação ficam intactas.
     */
    private function reporZebra(string $ficheiro): void
    {
        $zip = new ZipArchive();

        if ($zip->open($ficheiro) !== true) {
            throw new RuntimeException('Não foi possível abrir o documento gerado.');
        }

        $xml = $zip->getFromName('word/document.xml');

        if ($xml === false) {
            $zip->close();

            throw new RuntimeException('O documento gerado não contém word/document.xml.');
        }

        $documento                     = new DOMDocument('1.0', 'UTF-8');
        $documento->preserveWhiteSpace = true;
        $documento->formatOutput       = false;
        $documento->loadXML($xml, LIBXML_NONET);

        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('w', self::NS_W);

        $tabelas = $xpath->query('//w:tbl');

        if ($tabelas !== false) {
            foreach ($tabelas as $tabela) {
                if ($tabela instanceof DOMElement) {
                    $this->pintarTabela($tabela, $xpath);
                }
            }
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', (string) $documento->saveXML());
        $zip->close();
    }

    /**
     * Aplica a zebra a uma tabela de dados.
     */
    private function pintarTabela(DOMElement $tabela, DOMXPath $xpath): void
    {
        $linhas = [];

        foreach ($tabela->childNodes as $filho) {
            if ($filho instanceof DOMElement && $filho->localName === 'tr') {
                $linhas[] = $filho;
            }
        }

        // Uma tabela de dados tem cabeçalho e pelo menos uma linha.
        if (count($linhas) < 2 || !$this->ehCabecalhoDeDados($linhas[0], $xpath)) {
            return;
        }

        foreach (array_slice($linhas, 1) as $posicao => $linha) {
            // A primeira linha de dados é branca, a seguinte cinzenta.
            $cor    = $posicao % 2 === 0 ? 'FFFFFF' : 'F2F2F2';
            $sombra = $xpath->query('.//w:shd', $linha);

            if ($sombra === false) {
                continue;
            }

            foreach ($sombra as $no) {
                if ($no instanceof DOMElement) {
                    $no->setAttributeNS(self::NS_W, 'w:fill', $cor);
                }
            }
        }
    }

    /**
     * Indica se uma linha é o cabeçalho de uma tabela de dados, pela cor.
     */
    private function ehCabecalhoDeDados(DOMElement $linha, DOMXPath $xpath): bool
    {
        $sombra = $xpath->query('.//w:shd', $linha);

        if ($sombra === false) {
            return false;
        }

        foreach ($sombra as $no) {
            if (
                $no instanceof DOMElement
                && strtoupper($no->getAttributeNS(self::NS_W, 'fill')) === TemplateBuilder::corCabecalho()
            ) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Caminhos
    // -----------------------------------------------------------------------

    /**
     * Caminho completo do ficheiro a gerar, sem nunca substituir um existente.
     *
     * @param array<string, mixed> $relatorio
     */
    private function caminhoDeDestino(array $relatorio): string
    {
        $ano    = (int) $relatorio['ano'];
        $semana = (int) $relatorio['numero_semana'];

        $pasta = (string) Config::get('relatorio.saida')
            . DIRECTORY_SEPARATOR . $ano
            . DIRECTORY_SEPARATOR . sprintf('%02d', $semana);

        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $pasta);
        }

        $base = sprintf(
            'RelatorioSemanal_%d-S%02d_%s',
            $ano,
            $semana,
            $this->slug((string) ($relatorio['colaborador'] ?? 'colaborador'))
        );

        $caminho = $pasta . DIRECTORY_SEPARATOR . $base . '.docx';

        // Cada geração é um ficheiro novo: o histórico de versões mantém-se.
        $sufixo = 2;

        while (file_exists($caminho)) {
            $caminho = $pasta . DIRECTORY_SEPARATOR . $base . '_v' . $sufixo . '.docx';
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
     * Converte um nome em texto seguro para nome de ficheiro.
     */
    private function slug(string $nome): string
    {
        $slug = mb_strtolower(trim($nome), 'UTF-8');

        $slug = strtr($slug, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'colaborador' : substr($slug, 0, 60);
    }
}
