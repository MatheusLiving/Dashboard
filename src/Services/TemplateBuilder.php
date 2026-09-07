<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Constrói o template .docx com marcadores a partir do documento original.
 *
 * O ficheiro `Relatorio_Semanal_TI.docx` é copiado tal e qual; apenas
 * `word/document.xml` é alterado, e sempre por manipulação da árvore DOM, para
 * que nada da formatação se perca. O cabeçalho, o rodapé («Página X de Y»),
 * os estilos e o tema nunca são tocados.
 *
 * As tabelas de dados são reduzidas a cabeçalho + uma linha-modelo, que o
 * PhpWord clona com `cloneRow()` conforme o número de registos.
 */
final class TemplateBuilder
{
    /** Espaço de nomes principal do WordprocessingML. */
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Cor do cabeçalho das tabelas de dados, usada para as identificar. */
    private const COR_CABECALHO = '1F3864';

    private DOMDocument $documento;
    private DOMXPath $xpath;

    /** @var list<string> Avisos e passos dados, para o relatório da construção. */
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

        $pasta = dirname($this->destino);

        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $pasta);
        }

        // Trabalha-se sobre uma cópia: o original nunca é alterado.
        if (!copy($this->origem, $this->destino)) {
            throw new RuntimeException('Não foi possível copiar o documento original.');
        }

        $xml = $this->lerDocumentXml($this->destino);

        $this->carregar($xml);
        $this->aplicarMarcadores();
        $this->gravarDocumentXml($this->destino, $this->documento->saveXML());

        $this->registo[] = 'Template gravado em ' . $this->destino;

        return $this->registo;
    }

    // -----------------------------------------------------------------------
    // Leitura e escrita do pacote
    // -----------------------------------------------------------------------

    /**
     * Lê `word/document.xml` de dentro do .docx.
     */
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

    /**
     * Escreve `word/document.xml` de volta para dentro do .docx.
     */
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

    /**
     * Carrega o XML preservando espaços em branco significativos.
     */
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

    // -----------------------------------------------------------------------
    // Colocação dos marcadores
    // -----------------------------------------------------------------------

    /**
     * Percorre as tabelas do documento e coloca os marcadores.
     *
     * A ordem das tabelas no original é conhecida e estável:
     * 0 cabeçalho, 1 §1, 2 §2, 3 §3, 4 §4, 5 §5, 6 §6, 7 §7, 8 §8.
     */
    private function aplicarMarcadores(): void
    {
        $tabelas = $this->tabelas();

        if (count($tabelas) !== 9) {
            throw new RuntimeException(sprintf(
                'Esperavam-se 9 tabelas no documento original, encontradas %d. '
                . 'O template não corresponde ao esperado.',
                count($tabelas)
            ));
        }

        // Cabeçalho: as células de valor são as de índice ímpar de cada linha.
        $this->preencherCabecalho($tabelas[0]);

        // Caixas de texto livre — uma linha, uma célula.
        $this->preencherCaixa($tabelas[1], '${resumo_executivo}', '§1 Resumo Executivo');
        $this->preencherCaixa($tabelas[5], '${bloqueios}', '§5 Bloqueios, Riscos e Dependências');
        $this->preencherCaixa($tabelas[6], '${sugestao}', '§6 Sugestões de Melhoria');
        $this->preencherCaixa($tabelas[8], '${observacoes}', '§8 Observações Adicionais');

        // Tabelas de dados — reduzidas a cabeçalho + linha-modelo.
        $this->prepararTabelaDeDados(
            $tabelas[2],
            ['${ativ_data}', '${ativ_tarefa}', '${ativ_projeto}', '${ativ_estado}', '${ativ_tempo}'],
            '§2 Atividades Realizadas'
        );

        $this->prepararTabelaDeDados(
            $tabelas[3],
            ['${inc_descricao}', '${inc_prioridade}', '${inc_estado}', '${inc_resolucao}'],
            '§3 Incidentes e Pedidos de Suporte'
        );

        $this->prepararTabelaDeDados(
            $tabelas[4],
            ['${proj_nome}', '${proj_progresso}', '${proj_passos}', '${proj_obs}'],
            '§4 Projetos em Curso'
        );

        $this->prepararTabelaDeDados(
            $tabelas[7],
            ['${prox_tarefa}', '${prox_prazo}'],
            '§7 Planeamento para a Próxima Semana'
        );

        // A secção nova entra depois da caixa da §5, sem número, para não
        // alterar a numeração das secções seguintes.
        $this->inserirSeccaoDificuldades($tabelas[5]);
    }

    /**
     * Tabelas de topo do corpo do documento, pela ordem em que aparecem.
     *
     * @return list<DOMElement>
     */
    private function tabelas(): array
    {
        $nos     = $this->xpath->query('/w:document/w:body/w:tbl');
        $tabelas = [];

        if ($nos !== false) {
            foreach ($nos as $no) {
                if ($no instanceof DOMElement) {
                    $tabelas[] = $no;
                }
            }
        }

        return $tabelas;
    }

    /**
     * Coloca os marcadores nas células de valor do cabeçalho.
     */
    private function preencherCabecalho(DOMElement $tabela): void
    {
        $marcadores = [
            '${colaborador}',
            '${semana_periodo}',
            '${funcao_cargo}',
            '${data_entrega}',
        ];

        $indice = 0;

        foreach ($this->filhos($tabela, 'tr') as $linha) {
            $celulas = $this->filhos($linha, 'tc');

            // As células de valor são as que vêm a seguir a cada rótulo.
            foreach ($celulas as $posicao => $celula) {
                if ($posicao % 2 === 0) {
                    continue;
                }

                if (!isset($marcadores[$indice])) {
                    continue;
                }

                $this->escreverNaCelula($celula, $marcadores[$indice]);
                $indice++;
            }
        }

        $this->registo[] = 'Cabeçalho: ' . implode(', ', array_slice($marcadores, 0, $indice));
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

        $this->escreverNaCelula($celulas[0], $marcador);

        $this->registo[] = $rotulo . ': ' . $marcador;
    }

    /**
     * Reduz uma tabela a cabeçalho + linha-modelo e coloca os marcadores.
     *
     * O `cloneRow()` do PhpWord procura o marcador, encontra a linha que o
     * contém e clona-a; por isso basta uma linha-modelo com um marcador por
     * coluna. As linhas em branco do original são removidas.
     *
     * @param list<string> $marcadores Um por coluna, pela ordem das colunas
     */
    private function prepararTabelaDeDados(DOMElement $tabela, array $marcadores, string $rotulo): void
    {
        $linhas = $this->filhos($tabela, 'tr');

        if (count($linhas) < 2) {
            throw new RuntimeException('Tabela sem linhas de dados em ' . $rotulo);
        }

        $modelo  = $linhas[1];
        $celulas = $this->filhos($modelo, 'tc');

        if (count($celulas) !== count($marcadores)) {
            throw new RuntimeException(sprintf(
                '%s: esperavam-se %d colunas, encontradas %d.',
                $rotulo,
                count($marcadores),
                count($celulas)
            ));
        }

        foreach ($celulas as $posicao => $celula) {
            $this->escreverNaCelula($celula, $marcadores[$posicao]);
        }

        // As restantes linhas em branco deixam de fazer sentido: o número de
        // linhas passa a ser o número de registos.
        $removidas = 0;

        foreach (array_slice($linhas, 2) as $linha) {
            $linha->parentNode?->removeChild($linha);
            $removidas++;
        }

        $this->registo[] = sprintf(
            '%s: %s (removidas %d linhas em branco)',
            $rotulo,
            implode(' ', $marcadores),
            $removidas
        );
    }

    /**
     * Insere a secção «Dificuldades Encontradas» a seguir à caixa da §5.
     *
     * A secção é criada por clonagem do título, do subtítulo e da caixa da §5,
     * o que garante formatação idêntica sem escrever estilos à mão. Fica sem
     * número, para que a numeração 1–8 do documento original se mantenha.
     */
    private function inserirSeccaoDificuldades(DOMElement $caixaBloqueios): void
    {
        $titulo    = $this->paragrafoComTexto('5. Bloqueios, Riscos e Dependências');
        $subtitulo = $this->paragrafoComTexto(
            'Identifique obstáculos que impedem ou atrasam o trabalho, e o que é necessário para os resolver.'
        );

        if ($titulo === null || $subtitulo === null) {
            throw new RuntimeException('Não foi possível localizar a secção 5 para clonar a formatação.');
        }

        // O parágrafo de espaçamento que separa as secções.
        $espacador = $titulo->previousSibling;
        while ($espacador !== null && !$espacador instanceof DOMElement) {
            $espacador = $espacador->previousSibling;
        }

        $novoEspacador = $espacador instanceof DOMElement && $espacador->localName === 'p'
            ? $espacador->cloneNode(true)
            : null;

        $novoTitulo    = $titulo->cloneNode(true);
        $novoSubtitulo = $subtitulo->cloneNode(true);
        $novaCaixa     = $caixaBloqueios->cloneNode(true);

        if (
            !$novoTitulo instanceof DOMElement
            || !$novoSubtitulo instanceof DOMElement
            || !$novaCaixa instanceof DOMElement
        ) {
            throw new RuntimeException('Falha ao clonar a estrutura da secção 5.');
        }

        $this->substituirTexto($novoTitulo, 'Dificuldades Encontradas');
        $this->substituirTexto($novoSubtitulo, 'Quais foram as dificuldades encontradas durante o trabalho?');

        $linhas  = $this->filhos($novaCaixa, 'tr');
        $celulas = $linhas === [] ? [] : $this->filhos($linhas[0], 'tc');

        if ($celulas === []) {
            throw new RuntimeException('A caixa clonada não tem células.');
        }

        $this->escreverNaCelula($celulas[0], '${dificuldades}', true);

        // Inserção logo a seguir à caixa da §5, pela ordem correta.
        $referencia = $caixaBloqueios;
        $corpo      = $caixaBloqueios->parentNode;

        if ($corpo === null) {
            throw new RuntimeException('A caixa da secção 5 não tem elemento pai.');
        }

        foreach (array_filter([$novoEspacador, $novoTitulo, $novoSubtitulo, $novaCaixa]) as $no) {
            $referencia = $corpo->insertBefore($no, $referencia->nextSibling);
        }

        $this->registo[] = 'Secção «Dificuldades Encontradas» inserida após a §5 (sem número): ${dificuldades}';
    }

    // -----------------------------------------------------------------------
    // Manipulação de parágrafos e células
    // -----------------------------------------------------------------------

    /**
     * Primeiro parágrafo de topo cujo texto seja exatamente o indicado.
     */
    private function paragrafoComTexto(string $texto): ?DOMElement
    {
        $nos = $this->xpath->query('/w:document/w:body/w:p');

        if ($nos === false) {
            return null;
        }

        foreach ($nos as $no) {
            if ($no instanceof DOMElement && trim($no->textContent) === $texto) {
                return $no;
            }
        }

        return null;
    }

    /**
     * Substitui o texto de um parágrafo, mantendo a formatação do primeiro run.
     */
    private function substituirTexto(DOMElement $paragrafo, string $texto): void
    {
        $nos = $paragrafo->getElementsByTagNameNS(self::NS_W, 't');

        if ($nos->length === 0) {
            return;
        }

        $primeiro = $nos->item(0);

        if ($primeiro instanceof DOMElement) {
            $primeiro->nodeValue = '';
            $primeiro->appendChild($this->documento->createTextNode($texto));
        }

        // Runs adicionais são esvaziados, para não repetir o texto antigo.
        for ($i = 1; $i < $nos->length; $i++) {
            $no = $nos->item($i);

            if ($no instanceof DOMElement) {
                $no->nodeValue = '';
            }
        }
    }

    /**
     * Escreve um marcador dentro de uma célula.
     *
     * O marcador vai num único run, para que o PhpWord o encontre inteiro:
     * um marcador partido por vários runs passa despercebido e fica visível
     * no documento final.
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

        // Remove runs anteriores do parágrafo, deixando as propriedades (w:pPr).
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

        // Nas caixas de texto livre há um segundo parágrafo vazio que dá altura
        // à caixa; nas células de tabela ele não existe. Só se limpa quando pedido.
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
     * Marcadores que o template deve conter, para verificação posterior.
     *
     * @return list<string>
     */
    public static function marcadoresEsperados(): array
    {
        return [
            'colaborador', 'semana_periodo', 'funcao_cargo', 'data_entrega',
            'resumo_executivo', 'bloqueios', 'dificuldades', 'sugestao', 'observacoes',
            'ativ_data', 'ativ_tarefa', 'ativ_projeto', 'ativ_estado', 'ativ_tempo',
            'inc_descricao', 'inc_prioridade', 'inc_estado', 'inc_resolucao',
            'proj_nome', 'proj_progresso', 'proj_passos', 'proj_obs',
            'prox_tarefa', 'prox_prazo',
        ];
    }

    /**
     * Cor do cabeçalho das tabelas de dados.
     */
    public static function corCabecalho(): string
    {
        return self::COR_CABECALHO;
    }
}
