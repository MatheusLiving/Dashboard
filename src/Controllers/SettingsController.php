<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Setting;
use App\Models\Tag;
use App\Services\AuditLogger;

/**
 * Configurações de negócio, reservadas a administradores.
 *
 * Distinguem-se do .env: aqui ficam as opções que o departamento ajusta,
 * não as credenciais nem a configuração de infraestrutura.
 */
final class SettingsController extends Controller
{
    /**
     * Definições editáveis na página, com o respetivo tipo e ajuda.
     *
     * @return array<string, array{rotulo: string, tipo: string, ajuda: string, omissao: string}>
     */
    private function definicoes(): array
    {
        return [
            'departamento_nome' => [
                'rotulo'  => 'Nome do departamento',
                'tipo'    => 'texto',
                'ajuda'   => 'Aparece no cabeçalho da aplicação e do relatório.',
                'omissao' => 'Departamento de TI',
            ],
            'semana_dia_fecho' => [
                'rotulo'  => 'Dia de fecho da semana',
                'tipo'    => 'dia_semana',
                'ajuda'   => 'Dia em que se espera que o relatório seja preparado.',
                'omissao' => '5',
            ],
            'prazo_entrega_dias' => [
                'rotulo'  => 'Prazo de entrega (dias)',
                'tipo'    => 'inteiro',
                'ajuda'   => 'Dias após o fim da semana usados para propor a data de entrega.',
                'omissao' => '2',
            ],
            'template_caminho' => [
                'rotulo'  => 'Caminho do template .docx',
                'tipo'    => 'texto',
                'ajuda'   => 'Relativo à raiz do projeto. Informativo: o caminho usado na geração vem do .env.',
                'omissao' => 'storage/templates/Relatorio_Semanal_TI_template.docx',
            ],
            'tags_incidente' => [
                'rotulo'  => 'Etiquetas de incidente',
                'tipo'    => 'texto',
                'ajuda'   => 'Slugs separados por vírgula. Determinam o que entra na secção 3 do relatório.',
                'omissao' => 'suporte,incidente',
            ],
        ];
    }

    public function index(): void
    {
        $definicoes = $this->definicoes();
        $valores    = [];

        foreach ($definicoes as $chave => $definicao) {
            $valores[$chave] = (string) Setting::get($chave, $definicao['omissao']);
        }

        $template = (string) Config::get('relatorio.template');

        $this->ver('configuracoes/index', [
            'definicoes'      => $definicoes,
            'valores'         => $valores,
            'antigos'         => Flash::antigos(),
            'etiquetas'       => Tag::todas(),
            'diasSemana'      => $this->diasSemana(),
            'templateCaminho' => $template,
            'templateExiste'  => is_file($template),
            'pastaRelatorios' => (string) Config::get('relatorio.saida'),
            'fuso'            => (string) Config::get('app.fuso'),
            'ambiente'        => (string) Config::get('app.env'),
        ]);
    }

    /**
     * Grava as configurações submetidas.
     */
    public function guardar(): void
    {
        $definicoes = $this->definicoes();
        $dados      = Request::todosPost();

        $validador = Validator::para($dados)
            ->rotulos([
                'departamento_nome'  => 'nome do departamento',
                'semana_dia_fecho'   => 'dia de fecho da semana',
                'prazo_entrega_dias' => 'prazo de entrega',
                'template_caminho'   => 'caminho do template',
                'tags_incidente'     => 'etiquetas de incidente',
            ])
            ->obrigatorio('departamento_nome')
            ->maximo('departamento_nome', 150)
            ->inteiro('semana_dia_fecho', 1, 7)
            ->inteiro('prazo_entrega_dias', 0, 30)
            ->maximo('template_caminho', 255)
            ->maximo('tags_incidente', 255);

        // Só se aceitam slugs de etiquetas que existam: um slug errado deixaria
        // a secção 3 do relatório silenciosamente vazia.
        $slugs        = $this->slugs((string) Request::post('tags_incidente', ''));
        $desconhecidos = [];

        foreach ($slugs as $slug) {
            if (Tag::porSlug($slug) === null) {
                $desconhecidos[] = $slug;
            }
        }

        $validador->regra(
            'tags_incidente',
            $desconhecidos === [],
            'Não existem etiquetas com estes slugs: ' . implode(', ', $desconhecidos) . '.'
        );

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/configuracoes');
        }

        $antes     = [];
        $depois    = [];

        foreach (array_keys($definicoes) as $chave) {
            $valor = trim((string) ($dados[$chave] ?? ''));

            if ($chave === 'tags_incidente') {
                $valor = implode(',', $slugs);
            }

            $antes[$chave]  = (string) Setting::get($chave, '');
            $depois[$chave] = $valor;

            Setting::definir($chave, $valor);
        }

        AuditLogger::atualizado('configuracao', 0, $antes, $depois);

        Flash::sucesso('Configurações guardadas.');
        $this->redirecionar('/configuracoes');
    }

    /**
     * Normaliza a lista de slugs separados por vírgula.
     *
     * @return list<string>
     */
    private function slugs(string $bruto): array
    {
        $slugs = array_map(
            static fn (string $s): string => Tag::slug(trim($s)),
            explode(',', $bruto)
        );

        $slugs = array_filter($slugs, static fn (string $s): bool => $s !== '' && $s !== 'etiqueta');

        return array_values(array_unique($slugs));
    }

    /**
     * Nomes dos dias da semana, na ordem ISO.
     *
     * @return array<int, string>
     */
    private function diasSemana(): array
    {
        return [
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }
}
