<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acesso à tabela `users`.
 *
 * Os utilizadores nunca são apagados: a desativação faz-se com `ativo = 0`,
 * para que o histórico de tarefas e relatórios continue a fazer sentido.
 */
final class User
{
    /** Colunas devolvidas nas leituras — o hash da palavra-passe fica de fora. */
    private const CAMPOS = 'id, nome, email, funcao_cargo, papel, ativo, created_at, updated_at';

    /**
     * Procura um utilizador pelo identificador.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        return Database::primeiro(
            'SELECT ' . self::CAMPOS . ' FROM users WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Procura um utilizador pelo endereço de correio.
     * Inclui `senha_hash`, por ser usada apenas na autenticação.
     *
     * @return array<string, mixed>|null
     */
    public static function porEmail(string $email): ?array
    {
        return Database::primeiro(
            'SELECT ' . self::CAMPOS . ', senha_hash FROM users WHERE email = :email LIMIT 1',
            [':email' => mb_strtolower(trim($email))]
        );
    }

    /**
     * Indica se um endereço já está atribuído, opcionalmente ignorando um id
     * (útil na edição do próprio utilizador).
     */
    public static function emailExiste(string $email, ?int $exceto = null): bool
    {
        $sql        = 'SELECT 1 FROM users WHERE email = :email';
        $parametros = [':email' => mb_strtolower(trim($email))];

        if ($exceto !== null) {
            $sql .= ' AND id <> :exceto';
            $parametros[':exceto'] = $exceto;
        }

        return Database::valor($sql . ' LIMIT 1', $parametros) !== null;
    }

    /**
     * Lista de utilizadores, por omissão apenas os ativos.
     *
     * @return list<array<string, mixed>>
     */
    public static function todos(bool $apenasAtivos = true): array
    {
        $sql = 'SELECT ' . self::CAMPOS . ' FROM users';

        if ($apenasAtivos) {
            $sql .= ' WHERE ativo = 1';
        }

        return Database::todos($sql . ' ORDER BY nome ASC');
    }

    /**
     * Cria um utilizador e devolve o identificador gerado.
     *
     * @param array{nome: string, email: string, senha_hash: string, funcao_cargo: ?string, papel: string, ativo?: int} $dados
     */
    public static function criar(array $dados): int
    {
        return Database::inserir('users', [
            'nome'         => $dados['nome'],
            'email'        => mb_strtolower(trim($dados['email'])),
            'senha_hash'   => $dados['senha_hash'],
            'funcao_cargo' => $dados['funcao_cargo'] ?? null,
            'papel'        => $dados['papel'],
            'ativo'        => $dados['ativo'] ?? 1,
        ]);
    }

    /**
     * Atualiza os dados de um utilizador.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(int $id, array $dados): int
    {
        if (isset($dados['email'])) {
            $dados['email'] = mb_strtolower(trim((string) $dados['email']));
        }

        return Database::atualizar('users', $id, $dados);
    }

    /**
     * Substitui o hash da palavra-passe.
     */
    public static function atualizarSenha(int $id, string $hash): int
    {
        return Database::atualizar('users', $id, ['senha_hash' => $hash]);
    }

    /**
     * Devolve o hash atual da palavra-passe, para confirmação da anterior.
     */
    public static function hashAtual(int $id): ?string
    {
        $hash = Database::valor(
            'SELECT senha_hash FROM users WHERE id = :id LIMIT 1',
            [':id' => $id]
        );

        return is_string($hash) ? $hash : null;
    }

    /**
     * Ativa ou desativa um utilizador. Nunca há remoção definitiva.
     */
    public static function definirAtivo(int $id, bool $ativo): int
    {
        return Database::atualizar('users', $id, ['ativo' => $ativo ? 1 : 0]);
    }

    /**
     * Iniciais do nome, usadas nos avatares do quadro Kanban.
     */
    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $partes = array_values(array_filter($partes, static fn (string $p): bool => $p !== ''));

        if ($partes === []) {
            return '?';
        }

        if (count($partes) === 1) {
            return mb_strtoupper(mb_substr($partes[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[count($partes) - 1], 0, 1));
    }

    /**
     * Número de administradores ativos. Impede a desativação do último admin.
     */
    public static function totalAdminsAtivos(): int
    {
        return (int) Database::valor(
            "SELECT COUNT(*) FROM users WHERE papel = 'admin' AND ativo = 1"
        );
    }
}
