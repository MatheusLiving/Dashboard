<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `settings`.
 *
 * Guarda as opções de negócio editáveis na interface — distintas do .env,
 * que tem as credenciais e a configuração de infraestrutura.
 */
final class Setting
{
    /** @var array<string, ?string>|null Cache dentro do pedido. */
    private static ?array $cache = null;

    /**
     * Lê uma configuração, com valor por omissão.
     */
    public static function get(string $chave, ?string $omissao = null): ?string
    {
        if (self::$cache === null) {
            self::carregar();
        }

        $valor = self::$cache[$chave] ?? null;

        return $valor === null || $valor === '' ? $omissao : $valor;
    }

    /**
     * Lê uma configuração como inteiro.
     */
    public static function inteiro(string $chave, int $omissao): int
    {
        $valor = self::get($chave);

        return $valor === null || !is_numeric($valor) ? $omissao : (int) $valor;
    }

    /**
     * Todas as configurações, com descrição, para a página de administração.
     *
     * @return list<array<string, mixed>>
     */
    public static function todas(): array
    {
        return Database::todos('SELECT chave, valor, descricao FROM settings ORDER BY chave ASC');
    }

    /**
     * Escreve uma configuração, criando-a se ainda não existir.
     */
    public static function definir(string $chave, ?string $valor): void
    {
        Database::executar(
            'INSERT INTO settings (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [':chave' => $chave, ':valor' => $valor]
        );

        self::$cache = null;
    }

    /**
     * Slugs das etiquetas que classificam uma tarefa como incidente.
     *
     * @return list<string>
     */
    public static function tagsIncidente(): array
    {
        $bruto = (string) self::get('tags_incidente', 'suporte,incidente');

        $slugs = array_map('trim', explode(',', $bruto));
        $slugs = array_filter($slugs, static fn (string $s): bool => $s !== '');

        return array_values($slugs);
    }

    /**
     * Nome do departamento apresentado na interface e no relatório.
     *
     * A configuração ganha ao valor do .env, que serve apenas de recurso
     * quando ainda não foi definida.
     */
    public static function departamento(string $omissao = 'Departamento de TI'): string
    {
        try {
            return (string) self::get('departamento_nome', $omissao);
        } catch (\Throwable) {
            // Antes das migrações a tabela ainda não existe: a aplicação tem de
            // continuar a arrancar para que o erro real seja legível.
            return $omissao;
        }
    }

    /**
     * Dias após o fim da semana para entrega do relatório.
     */
    public static function prazoEntregaDias(): int
    {
        return self::inteiro('prazo_entrega_dias', 2);
    }

    /**
     * Carrega todas as configurações de uma só vez.
     */
    private static function carregar(): void
    {
        self::$cache = [];

        foreach (Database::todos('SELECT chave, valor FROM settings') as $linha) {
            self::$cache[(string) $linha['chave']] = $linha['valor'] === null ? null : (string) $linha['valor'];
        }
    }
}
