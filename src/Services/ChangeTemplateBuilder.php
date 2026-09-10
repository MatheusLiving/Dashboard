<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Constrói o template .docx do Relatório de Alteração de Software.
 *
 * Segue a mesma disciplina do `TemplateBuilder` do relatório semanal: o
 * documento original é copiado tal e qual e só `word/document.xml` é alterado,
 * sempre por manipulação da árvore DOM. Estilos, numeração, cabeçalho e rodapé
 * ficam byte a byte iguais.
 *
 * As secções são localizadas pela **posição das tabelas**, não pelo texto. O
 * documento tem 19 tabelas, sempre pela mesma ordem; se esse número mudar, a
 * construção falha em vez de produzir um template partido.
 */
final class ChangeTemplateBuilder
{
    /** Espaço de nomes principal do WordprocessingML. */
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Número de tabelas que o documento original tem. */
    private const TOTAL_TABELAS = 19;

    private DOMDocument $documento;
    private DOMXPath $xpath;

    /** @var list<string> Registo do que foi feito, para o relatório da construção. */
    private array $registo = [];

    public function __construct(
        private readonly string $origem,
        private readonly string $destino
    ) {
    }

    /**
     * Constrói o template e devolve o registo do que foi feito.
     *
     * @return list<string>
     */
    public function construir(): array
    {
        if (!is_file($this->origem)) {
            throw new RuntimeException('Documento original não encontrado: ' . $this->origem);
        }

        if (realpath($this->origem) === realpath($this->destino)) {
            throw new RuntimeException('O template não pode ser gravado por cima do original.');
        }

        $pasta = dirname($this->destino);

        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $pasta);
        }

        // Trabalha-se sobre uma cópia: o original nunca é alterado.
        if (!copy($this->origem, $this->destino)) {
            throw new RuntimeException('Não foi possível copiar o documento original.');
        }

        $this->carregar($this->lerDocumentXml($this->destino));
        $this->aplicarMarcadores();
        $this->gravarDocumentXml($this->destino, $this->documento->saveXML());

        $this->registo[] = 'Template gravado em ' . $this->destino;

        return $this->registo;
    }

    // -----------------------------------------------------------------------
    // Colocação dos marcadores
    // -----------------------------------------------------------------------

    /**
     * Percorre as 19 tabelas do documento e coloca os marcadores.
     */
    private function aplicarMarcadores(): void
    {
        $tabelas = $this->xpath->query('//w:body/w:tbl');

        if ($tabelas === false || $tabelas->length !== self::TOTAL_TABELAS) {
            throw new RuntimeException(sprintf(
                'Estrutura inesperada: esperavam-se %d tabelas, encontradas %d. '
                . 'Se o documento original mudou, é preciso rever ChangeTemplateBuilder::aplicarMarcadores().',
                self::TOTAL_TABELAS,
                $tabelas === false ? 0 : $tabelas->length
            ));
        }

        /** @var list<DOMElement> $lista */
        $lista = [];
        foreach ($tabelas as $tabela) {
            if ($tabela instanceof DOMElement) {
                $lista[] = $tabela;
            }
        }

        // Tabela 1 — cabeçalho de identificação: rótulo à esquerda, valor à direita.
        $this->preencherCabecalho($lista[0], [
            'data_abertura',
            'solicitado_por',
            'cliente_projeto',
            'sistema_afetado',
            // As opções de «Tipo» e «Prioridade» são escritas por inteiro pelo
            // gerador, com a escolhida assinalada — assim a linha de caixas do
            // original mantém-se, em vez de ser substituída por um valor solto.
            'tipo_alteracao',
            'prioridade',
            'data_prevista',
        ]);

        // Tabelas 2 a 8 — caixas da secção 1.
        $caixasSeccao1 = [
            'desc_problema', 'objetivo', 'ambito', 'modulos',
            'impacto_riscos', 'estimativa', 'programadores',
        ];

        foreach ($caixasSeccao1 as $posicao => $marcador) {
            $this->preencherCaixa($lista[1 + $posicao], $marcador, '1.' . ($posicao + 1));
        }

        // Tabela 9 — acompanhamento do desenvolvimento (cabeçalho + linha-modelo).
        $this->prepararTabelaDeDados(
            $lista[8],
            ['ac_data', 'ac_estado', 'ac_descricao', 'ac_responsavel'],
            '2. Acompanhamento'
        );

        // Tabela 10 — desvios ao pedido inicial.
        $this->preencherCaixa($lista[9], 'desvios', '2.1');

        // Tabelas 11 a 13 — secção 3.
        foreach (['testes_realizados', 'resultados', 'validado_por'] as $posicao => $marcador) {
            $this->preencherCaixa($lista[10 + $posicao], $marcador, '3.' . ($posicao + 1));
        }

        // Tabelas 14 a 18 — secção 4.
        $caixasSeccao4 = ['resumo_execucao', 'diferencas', 'notas_cliente', 'commits', 'conclusao_programadores'];

        foreach ($caixasSeccao4 as $posicao => $marcador) {
            $this->preencherCaixa($lista[13 + $posicao], $marcador, '4.' . ($posicao + 1));
        }

        // Tabela 19 — entrega.
        $this->preencherCaixa($lista[18], 'entrega', '5. Entrega');
    }

    /**
     * Escreve os marcadores na coluna de valores da tabela de identificação.
     *
     * @param list<string> $marcadores Um por linha, pela ordem das linhas
     */
    private function preencherCabecalho(DOMElement $tabela, array $marcadores): void
    {
        $linhas = $this->filhos($tabela, 'tr');

        if (count($linhas) !== count($marcadores)) {
            throw new RuntimeException(sprintf(
                'Cabeçalho: esperavam-se %d linhas, encontradas %d.',
                count($marcadores),
                count($linhas)
            ));
        }

        foreach ($linhas as $posicao => $linha) {
            $celulas = $this->filhos($linha, 'tc');

            if (count($celulas) < 2) {
                throw new RuntimeException('Linha do cabeçalho sem célula de valor: ' . $marcadores[$posicao]);
            }

            // A primeira célula é o rótulo impresso («Data de Abertura»); só a
            // segunda é preenchida.
            $this->escreverNaCelula($celulas[1], $this->marcador($marcadores[$posicao]), true);
        }

        $this->registo[] = 'Cabeçalho: ' . implode(' ', array_map([$this, 'marcador'], $marcadores));
    }

    /**
     * Coloca um marcador numa caixa de texto livre (tabela de uma só célula).
     */
    private function preencherCaixa(DOMElement $tabela, string $marcador, string $rotulo): void
    {
        $linhas = $this->filhos($tabela, 'tr');

        if ($linhas === []) {
            throw new RuntimeException('Caixa sem linhas em ' . $rotulo);
        }

        $celulas = $this->filhos($linhas[0], 'tc');

        if ($celulas === []) {
            throw new RuntimeException('Caixa sem células em ' . $rotulo);
        }

        $this->escreverNaCelula($celulas[0], $this->marcador($marcador));

        $this->registo[] = $rotulo . ': ' . $this->marcador($marcador);
    }

    /**
     * Reduz a tabela de acompanhamento a cabeçalho + linha-modelo.
     *
     * O `cloneRow()` do PhpWord encontra a linha que contém o marcador e
     * clona-a tantas vezes quantos os registos; as linhas em branco do
     * original deixam de fazer sentido.
     *
     * @param list<string> $marcadores Um por coluna
     */
    private function prepararTabelaDeDados(DOMElement $tabela, array $marcadores, string $rotulo): void
    {
        $linhas = $this->filhos($tabela, 'tr');

        if (count($linhas) < 2) {
            throw new RuntimeException('Tabela sem linhas de dados em ' . $rotulo);
        }

        $celulas = $this->filhos($linhas[1], 'tc');

        if (count($celulas) !== count($marcadores)) {
            throw new RuntimeException(sprintf(
                '%s: esperavam-se %d colunas, encontradas %d.',
                $rotulo,
                count($marcadores),
                count($celulas)
            ));
        }

        foreach ($celulas as $posicao => $celula) {
            $this->escreverNaCelula($celula, $this->marcador($marcadores[$posicao]));
        }

        $removidas = 0;

        foreach (array_slice($linhas, 2) as $linha) {
            $linha->parentNode?->removeChild($linha);
            $removidas++;
        }

        $this->registo[] = sprintf(
            '%s: %s (removidas %d linhas em branco)',
            $rotulo,
            implode(' ', array_map([$this, 'marcador'], $marcadores)),
            $removidas
        );
    }

    /**
     * Escreve um marcador numa célula, sempre num único `<w:r>`.
     *
     * Isto não é um pormenor: o Word parte texto escrito à mão por vários
     * runs, e um marcador partido passa despercebido ao PhpWord e acaba
     * impresso no documento entregue.
     */
    private function escreverNaCelula(DOMElement $celula, string $marcador, bool $limparTudo = false): void
    {
        $paragrafos = $this->filhos($celula, 'p');

        if ($paragrafos === []) {
            $paragrafo = $this->documento->createElementNS(self::NS_W, 'w:p');
            $celula->appendChild($paragrafo);
            $paragrafos = [$paragrafo];
        }

        $primeiro = $paragrafos[0];

        // Remove os runs anteriores, deixando as propriedades do parágrafo.
        foreach (iterator_to_array($primeiro->childNodes) as $filho) {
            if ($filho instanceof DOMElement && $filho->localName !== 'pPr') {
                $primeiro->removeChild($filho);
            }
        }

        $run = $this->documento->createElementNS(self::NS_W, 'w:r');
        $t   = $this->documento->createElementNS(self::NS_W, 'w:t');

        // xml:space="preserve" evita que o Word coma espaços à volta do valor.
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->appendChild($this->documento->createTextNode($marcador));
        $run->appendChild($t);
        $primeiro->appendChild($run);

        // Nas caixas de texto livre os parágrafos seguintes dão altura à caixa
        // e ficam como estão; nas células do cabeçalho são limpos, para que o
        // valor não venha acompanhado de restos do original.
        if ($limparTudo) {
            foreach (array_slice($paragrafos, 1) as $extra) {
                foreach (iterator_to_array($extra->childNodes) as $filho) {
                    if ($filho instanceof DOMElement && $filho->localName !== 'pPr') {
                        $extra->removeChild($filho);
                    }
                }
            }
        }
    }

    // -----------------------------------------------------------------------
    // Leitura e escrita do pacote
    // -----------------------------------------------------------------------

    private function lerDocumentXml(string $ficheiro): string
    {
        $zip = new ZipArchive();

        if ($zip->open($ficheiro) !== true) {
            throw new RuntimeException('Não foi possível abrir o .docx: ' . $ficheiro);
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('O .docx não contém word/document.xml.');
        }

        return $xml;
    }

    private function gravarDocumentXml(string $ficheiro, string $xml): void
    {
        $zip = new ZipArchive();

        if ($zip->open($ficheiro) !== true) {
            throw new RuntimeException('Não foi possível reabrir o .docx para escrita.');
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }

    private function carregar(string $xml): void
    {
        $this->documento                     = new DOMDocument('1.0', 'UTF-8');
        $this->documento->preserveWhiteSpace = true;
        $this->documento->formatOutput       = false;

        if (!$this->documento->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('O document.xml não é XML válido.');
        }

        $this->xpath = new DOMXPath($this->documento);
        $this->xpath->registerNamespace('w', self::NS_W);
    }

    /**
     * Filhos diretos de um elemento com um dado nome local.
     *
     * @return list<DOMElement>
     */
    private function filhos(DOMElement $pai, string $nome): array
    {
        $resultado = [];

        foreach ($pai->childNodes as $filho) {
            if ($filho instanceof DOMElement && $filho->localName === $nome) {
                $resultado[] = $filho;
            }
        }

        return $resultado;
    }

    /**
     * Envolve um nome no formato de marcador que o PhpWord reconhece.
     */
    private function marcador(string $nome): string
    {
        return '${' . $nome . '}';
    }

    /**
     * Marcadores que o template deve conter, para verificação posterior.
     *
     * @return list<string>
     */
    public static function marcadoresEsperados(): array
    {
        return [
            'data_abertura', 'solicitado_por', 'cliente_projeto', 'sistema_afetado',
            'tipo_alteracao', 'prioridade', 'data_prevista',
            'desc_problema', 'objetivo', 'ambito', 'modulos',
            'impacto_riscos', 'estimativa', 'programadores',
            'ac_data', 'ac_estado', 'ac_descricao', 'ac_responsavel',
            'desvios',
            'testes_realizados', 'resultados', 'validado_por',
            'resumo_execucao', 'diferencas', 'notas_cliente', 'commits', 'conclusao_programadores',
            'entrega',
        ];
    }
}
