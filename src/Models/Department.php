<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Semana;

/**
 * Departamentos da empresa e os votos de assistência técnica.
 *
 * Cada voto é um pedido de assistência atribuído a um departamento: serve
 * para a equipa de TI perceber de onde vem o trabalho. É informação interna
 * e não entra no relatório semanal.
 *
 * Um departamento desativado sai do quadro de registo, mas os votos que já
 * tinha continuam a contar — a contagem passada não se reescreve.
 */
final class Department
{
    /** Períodos oferecidos no quadro. */
    public const PERIODOS = ['semana', 'mes', 'ano', 'tudo'];

    public const PERIODO_OMISSAO = 'mes';

    /**
     * Lista de departamentos, por omissão apenas os ativos.
     *
     * @return list<array<string, mixed>>
     */
    public static function todos(bool $apenasAtivos = true): array
    {
        $sql = 'SELECT id, nome, slug, cor_hex, ativo, created_at FROM departments';

        if ($apenasAtivos) {
            $sql .= ' WHERE ativo = 1';
        }

        return Database::todos($sql . ' ORDER BY nome ASC');
    }

    /**
     * Um departamento pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT id, nome, slug, cor_hex, ativo, created_at FROM departments WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Indica se um slug já está em uso, opcionalmente ignorando um id.
     */
    public static function slugExiste(string $slug, ?int $exceto = null): bool
    {
        $sql        = 'SELECT 1 FROM departments WHERE slug = :slug';
        $parametros = [':slug' => $slug];

        if ($exceto !== null) {
            $sql .= ' AND id <> :exceto';
            $parametros[':exceto'] = $exceto;
        }

        return Database::valor($sql . ' LIMIT 1', $parametros) !== null;
    }

    /**
     * Cria um departamento e devolve o identificador gerado.
     */
    public static function criar(string $nome, string $corHex): int
    {
        return Database::inserir('departments', [
            'nome'    => $nome,
            'slug'    => self::slugDe($nome),
            'cor_hex' => strtoupper($corHex),
            'ativo'   => 1,
        ]);
    }

    /**
     * Atualiza o nome e a cor de um departamento.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        if (isset($dados['nome'])) {
            $dados['slug'] = self::slugDe((string) $dados['nome']);
        }

        if (isset($dados['cor_hex'])) {
            $dados['cor_hex'] = strtoupper((string) $dados['cor_hex']);
        }

        return Database::atualizar('departments', $id, $dados);
    }

    /**
     * Converte o nome em slug.
     *
     * A transliteração dos acentos vive em Tag::slug e não vale a pena
     * duplicá-la; só muda o valor de recurso quando o nome não tem um único
     * caractere aproveitável.
     */
    public static function slugDe(string $nome): string
    {
        return preg_match('/[a-zA-Z0-9]/', $nome) === 1 ? Tag::slug($nome) : 'departamento';
    }

    /**
     * Ativa ou desativa um departamento.
     */
    public static function definirAtivo(int $id, bool $ativo): int
    {
        return Database::atualizar('departments', $id, ['ativo' => $ativo ? 1 : 0]);
    }

    /**
     * Elimina um departamento. Só deve ser usado quando não tem votos: com
     * votos, o caminho é desativá-lo, para não apagar a contagem histórica.
     */
    public static function eliminar(int $id): int
    {
        return Database::executar('DELETE FROM departments WHERE id = :id', [':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // Períodos
    // -----------------------------------------------------------------------

    /**
     * Normaliza o período pedido, recusando o que não conheça.
     */
    public static function periodoValido(?string $periodo): string
    {
        return in_array($periodo, self::PERIODOS, true) ? $periodo : self::PERIODO_OMISSAO;
    }

    /**
     * Datas de início e fim de um período, prontas para SQL.
     *
     * A hora do fim vai explícita: `created_at` é um TIMESTAMP e uma
     * comparação só com a data deixaria de fora tudo o que aconteceu no
     * último dia depois da meia-noite.
     *
     * @return array{de: ?string, ate: ?string, rotulo: string}
     */
    public static function intervalo(string $periodo): array
    {
        $agora = Semana::agora();

        return match ($periodo) {
            'semana' => (static function () use ($agora): array {
                $iso       = Semana::deData($agora);
                $intervalo = Semana::intervalo($iso['ano'], $iso['semana']);

                return [
                    'de'     => $intervalo['inicio'] . ' 00:00:00',
                    'ate'    => $intervalo['fim'] . ' 23:59:59',
                    'rotulo' => 'Semana ' . Semana::rotuloCurto($iso['ano'], $iso['semana']),
                ];
            })(),
            'ano' => [
                'de'     => $agora->format('Y') . '-01-01 00:00:00',
                'ate'    => $agora->format('Y') . '-12-31 23:59:59',
                'rotulo' => 'Ano de ' . $agora->format('Y'),
            ],
            'tudo' => [
                'de'     => null,
                'ate'    => null,
                'rotulo' => 'Desde sempre',
            ],
            default => [
                'de'     => $agora->format('Y-m-01') . ' 00:00:00',
                'ate'    => $agora->format('Y-m-t') . ' 23:59:59',
                'rotulo' => 'Mês de ' . $agora->format('m/Y'),
            ],
        };
    }

    // -----------------------------------------------------------------------
    // Votos
    // -----------------------------------------------------------------------

    /**
     * Classificação dos departamentos por número de votos no período.
     *
     * Os departamentos sem votos aparecem na mesma, com zero: saber quem não
     * pede ajuda é tão informativo como saber quem pede.
     *
     * @return list<array<string, mixed>>
     */
    public static function classificacao(?string $de, ?string $ate, bool $incluirInativos = false): array
    {
        $parametros = [];
        $janela     = '';

        // A janela temporal fica no ON e não no WHERE: no WHERE, um LEFT JOIN
        // sem linhas passaria a comportar-se como INNER e os departamentos
        // sem votos no período desapareceriam do quadro.
        if ($de !== null) {
            $janela .= ' AND v.created_at >= :de';
            $parametros[':de'] = $de;
        }

        if ($ate !== null) {
            $janela .= ' AND v.created_at <= :ate';
            $parametros[':ate'] = $ate;
        }

        $onde = $incluirInativos ? '' : ' WHERE d.ativo = 1';

        return Database::todos(
            'SELECT d.id, d.nome, d.slug, d.cor_hex, d.ativo,
                    COUNT(v.id) AS votos,
                    MAX(v.created_at) AS ultimo_voto
             FROM departments d
             LEFT JOIN department_votes v ON v.department_id = d.id' . $janela . '
             ' . $onde . '
             GROUP BY d.id, d.nome, d.slug, d.cor_hex, d.ativo
             ORDER BY votos DESC, d.nome ASC',
            $parametros
        );
    }

    /**
     * Total de votos num período, para a percentagem de cada departamento.
     */
    public static function totalVotos(?string $de, ?string $ate): int
    {
        $condicoes  = [];
        $parametros = [];

        if ($de !== null) {
            $condicoes[]       = 'created_at >= :de';
            $parametros[':de'] = $de;
        }

        if ($ate !== null) {
            $condicoes[]        = 'created_at <= :ate';
            $parametros[':ate'] = $ate;
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return (int) Database::valor('SELECT COUNT(*) FROM department_votes' . $onde, $parametros);
    }

    /**
     * Número de votos de um departamento, desde sempre.
     */
    public static function votosDoDepartamento(int $id): int
    {
        return (int) Database::valor(
            'SELECT COUNT(*) FROM department_votes WHERE department_id = :id',
            [':id' => $id]
        );
    }

    /**
     * Regista um voto e devolve o identificador gerado.
     */
    public static function votar(int $departmentId, ?int $userId, ?string $nota): int
    {
        return Database::inserir('department_votes', [
            'department_id' => $departmentId,
            'user_id'       => $userId,
            'nota'          => $nota === null || $nota === '' ? null : mb_substr($nota, 0, 200),
        ]);
    }

    /**
     * Um voto pelo identificador, com o departamento e o autor.
     *
     * @return array<string, mixed>|null
     */
    public static function voto(int $id): ?array
    {
        return Database::primeiro(
            'SELECT v.*, d.nome AS departamento, u.nome AS autor
             FROM department_votes v
             INNER JOIN departments d ON d.id = v.department_id
             LEFT JOIN users u ON u.id = v.user_id
             WHERE v.id = :id
             LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Anula um voto.
     */
    public static function anularVoto(int $id): int
    {
        return Database::executar('DELETE FROM department_votes WHERE id = :id', [':id' => $id]);
    }

    /**
     * Últimos votos registados, para se poder corrigir um engano.
     *
     * @return list<array<string, mixed>>
     */
    public static function votosRecentes(int $limite = 12): array
    {
        // O limite é um inteiro do próprio código, nunca do pedido.
        $limite = max(1, min(50, $limite));

        return Database::todos(
            'SELECT v.id, v.nota, v.created_at, v.user_id,
                    d.nome AS departamento, d.cor_hex,
                    u.nome AS autor
             FROM department_votes v
             INNER JOIN departments d ON d.id = v.department_id
             LEFT JOIN users u ON u.id = v.user_id
             ORDER BY v.created_at DESC, v.id DESC
             LIMIT ' . $limite
        );
    }

    /**
     * Quem registou votos no período, e quantos.
     *
     * @return list<array<string, mixed>>
     */
    public static function porAutor(?string $de, ?string $ate): array
    {
        $condicoes  = [];
        $parametros = [];

        if ($de !== null) {
            $condicoes[]       = 'v.created_at >= :de';
            $parametros[':de'] = $de;
        }

        if ($ate !== null) {
            $condicoes[]        = 'v.created_at <= :ate';
            $parametros[':ate'] = $ate;
        }

        $onde = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return Database::todos(
            'SELECT COALESCE(u.nome, \'—\') AS autor, COUNT(*) AS votos
             FROM department_votes v
             LEFT JOIN users u ON u.id = v.user_id'
            . $onde .
            ' GROUP BY autor
             ORDER BY votos DESC, autor ASC',
            $parametros
        );
    }

    /**
     * Votos por semana ISO das últimas semanas, para a evolução no topo.
     *
     * @return list<array{rotulo: string, votos: int}>
     */
    public static function evolucaoSemanal(int $semanas = 8): array
    {
        $semanas = max(2, min(26, $semanas));
        $agora   = Semana::agora();
        $serie   = [];

        for ($i = $semanas - 1; $i >= 0; $i--) {
            $data      = $agora->modify('-' . $i . ' weeks');
            $iso       = Semana::deData($data);
            $intervalo = Semana::intervalo($iso['ano'], $iso['semana']);

            $serie[] = [
                'rotulo' => 'S' . str_pad((string) $iso['semana'], 2, '0', STR_PAD_LEFT),
                'votos'  => self::totalVotos(
                    $intervalo['inicio'] . ' 00:00:00',
                    $intervalo['fim'] . ' 23:59:59'
                ),
            ];
        }

        return $serie;
    }
}
