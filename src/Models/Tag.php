<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `tags`.
 *
 * Uma etiqueta desativada desaparece dos seletores, mas continua a ser
 * apresentada nas tarefas que já a usavam — o histórico não se reescreve.
 */
final class Tag
{
    /**
     * Lista de etiquetas, por omissão apenas as ativas.
     *
     * @return list<array<string, mixed>>
     */
    public static function todas(bool $apenasAtivas = true): array
    {
        $sql = 'SELECT id, nome, slug, cor_hex, ativa, created_at FROM tags';

        if ($apenasAtivas) {
            $sql .= ' WHERE ativa = 1';
        }

        return Database::todos($sql . ' ORDER BY nome ASC');
    }

    /**
     * Etiquetas com o número de tarefas associadas, para a página de gestão.
     *
     * @return list<array<string, mixed>>
     */
    public static function comContagem(): array
    {
        return Database::todos(
            'SELECT t.id, t.nome, t.slug, t.cor_hex, t.ativa, t.created_at,
                    COUNT(tt.task_id) AS total_tarefas
             FROM tags t
             LEFT JOIN task_tag tt ON tt.tag_id = t.id
             GROUP BY t.id, t.nome, t.slug, t.cor_hex, t.ativa, t.created_at
             ORDER BY t.ativa DESC, t.nome ASC'
        );
    }

    /**
     * Uma etiqueta pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT id, nome, slug, cor_hex, ativa, created_at FROM tags WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Uma etiqueta pelo slug.
     *
     * @return array<string, mixed>|null
     */
    public static function porSlug(string $slug): ?array
    {
        return Database::primeiro(
            'SELECT id, nome, slug, cor_hex, ativa, created_at FROM tags WHERE slug = :slug LIMIT 1',
            [':slug' => $slug]
        );
    }

    /**
     * Indica se um slug já está em uso, opcionalmente ignorando um id.
     */
    public static function slugExiste(string $slug, ?int $exceto = null): bool
    {
        $sql        = 'SELECT 1 FROM tags WHERE slug = :slug';
        $parametros = [':slug' => $slug];

        if ($exceto !== null) {
            $sql .= ' AND id <> :exceto';
            $parametros[':exceto'] = $exceto;
        }

        return Database::valor($sql . ' LIMIT 1', $parametros) !== null;
    }

    /**
     * Cria uma etiqueta e devolve o identificador gerado.
     */
    public static function criar(string $nome, string $corHex): int
    {
        return Database::inserir('tags', [
            'nome'    => $nome,
            'slug'    => self::slug($nome),
            'cor_hex' => strtoupper($corHex),
            'ativa'   => 1,
        ]);
    }

    /**
     * Atualiza uma etiqueta.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        if (isset($dados['nome'])) {
            $dados['slug'] = self::slug((string) $dados['nome']);
        }

        if (isset($dados['cor_hex'])) {
            $dados['cor_hex'] = strtoupper((string) $dados['cor_hex']);
        }

        return Database::atualizar('tags', $id, $dados);
    }

    /**
     * Ativa ou desativa uma etiqueta.
     */
    public static function definirAtiva(int $id, bool $ativa): int
    {
        return Database::atualizar('tags', $id, ['ativa' => $ativa ? 1 : 0]);
    }

    /**
     * Elimina definitivamente uma etiqueta. Só é permitido quando nenhuma
     * tarefa a usa; caso contrário, deve ser desativada.
     */
    public static function eliminar(int $id): int
    {
        return Database::executar('DELETE FROM tags WHERE id = :id', [':id' => $id]);
    }

    /**
     * Número de tarefas associadas a uma etiqueta.
     */
    public static function totalTarefas(int $id): int
    {
        return (int) Database::valor(
            'SELECT COUNT(*) FROM task_tag WHERE tag_id = :id',
            [':id' => $id]
        );
    }

    /**
     * Converte um nome em slug: minúsculas, sem acentos, com hífenes.
     */
    public static function slug(string $nome): string
    {
        $slug = mb_strtolower(trim($nome), 'UTF-8');

        // Translitera os acentos mais comuns em português antes de limpar.
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

        return $slug === '' ? 'etiqueta' : substr($slug, 0, 80);
    }

    /**
     * Paleta pré-definida oferecida no seletor de cor, alinhada com o Tailwind.
     *
     * @return list<array{nome: string, hex: string}>
     */
    public static function paleta(): array
    {
        return [
            ['nome' => 'Marinho',   'hex' => '#1F3864'],
            ['nome' => 'Azul',      'hex' => '#2563EB'],
            ['nome' => 'Céu',       'hex' => '#0EA5E9'],
            ['nome' => 'Turquesa',  'hex' => '#0D9488'],
            ['nome' => 'Verde',     'hex' => '#16A34A'],
            ['nome' => 'Lima',      'hex' => '#65A30D'],
            ['nome' => 'Âmbar',     'hex' => '#D97706'],
            ['nome' => 'Laranja',   'hex' => '#EA580C'],
            ['nome' => 'Vermelho',  'hex' => '#DC2626'],
            ['nome' => 'Rosa',      'hex' => '#DB2777'],
            ['nome' => 'Violeta',   'hex' => '#7C3AED'],
            ['nome' => 'Ardósia',   'hex' => '#475569'],
        ];
    }
}
