<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Ponto único de acesso à base de dados (padrão singleton sobre PDO).
 *
 * Todas as consultas do sistema passam por aqui e usam sempre prepared
 * statements — nunca há interpolação de valores dentro do SQL.
 */
final class Database
{
    private static ?PDO $ligacao = null;

    /** Impede a instanciação: a classe é usada apenas de forma estática. */
    private function __construct()
    {
    }

    /**
     * Devolve a ligação PDO, criando-a na primeira utilização.
     */
    public static function ligacao(): PDO
    {
        if (self::$ligacao instanceof PDO) {
            return self::$ligacao;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('db.host', '127.0.0.1'),
            (int) Config::get('db.porta', 3306),
            (string) Config::get('db.nome', ''),
            (string) Config::get('db.charset', 'utf8mb4')
        );

        try {
            self::$ligacao = new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.password', ''),
                self::opcoes()
            );
        } catch (PDOException $e) {
            throw new DatabaseUnavailableException(
                'Não foi possível ligar à base de dados: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return self::$ligacao;
    }

    /**
     * Liga-se ao servidor MySQL sem selecionar base de dados.
     * Usado pelo script de migrações, que precisa de poder criar o esquema.
     */
    public static function ligacaoServidor(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            (string) Config::get('db.host', '127.0.0.1'),
            (int) Config::get('db.porta', 3306),
            (string) Config::get('db.charset', 'utf8mb4')
        );

        try {
            return new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.password', ''),
                self::opcoes()
            );
        } catch (PDOException $e) {
            throw new DatabaseUnavailableException(
                'Não foi possível ligar ao servidor MySQL: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Opções comuns a todas as ligações PDO.
     *
     * @return array<int, mixed>
     */
    private static function opcoes(): array
    {
        return [
            // Erros como exceções: nunca falhamos em silêncio.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statements reais no servidor, não emulados.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];
    }

    /**
     * Executa uma consulta preparada e devolve o statement.
     *
     * @param array<int|string, mixed> $parametros
     */
    public static function query(string $sql, array $parametros = []): PDOStatement
    {
        $stmt = self::ligacao()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt;
    }

    /**
     * Devolve a primeira linha do resultado, ou null se não houver nenhuma.
     *
     * @param array<int|string, mixed> $parametros
     * @return array<string, mixed>|null
     */
    public static function primeiro(string $sql, array $parametros = []): ?array
    {
        $linha = self::query($sql, $parametros)->fetch();

        return $linha === false ? null : $linha;
    }

    /**
     * Devolve todas as linhas do resultado.
     *
     * @param array<int|string, mixed> $parametros
     * @return list<array<string, mixed>>
     */
    public static function todos(string $sql, array $parametros = []): array
    {
        /** @var list<array<string, mixed>> $linhas */
        $linhas = self::query($sql, $parametros)->fetchAll();

        return $linhas;
    }

    /**
     * Devolve o valor da primeira coluna da primeira linha.
     *
     * @param array<int|string, mixed> $parametros
     */
    public static function valor(string $sql, array $parametros = []): mixed
    {
        $valor = self::query($sql, $parametros)->fetchColumn();

        return $valor === false ? null : $valor;
    }

    /**
     * Executa uma instrução de escrita e devolve o número de linhas afetadas.
     *
     * @param array<int|string, mixed> $parametros
     */
    public static function executar(string $sql, array $parametros = []): int
    {
        return self::query($sql, $parametros)->rowCount();
    }

    /**
     * Insere uma linha a partir de um array coluna => valor e devolve o id gerado.
     *
     * Os nomes das colunas vêm sempre de código nosso, nunca do pedido HTTP;
     * os valores seguem em marcadores nomeados.
     *
     * @param array<string, mixed> $dados
     */
    public static function inserir(string $tabela, array $dados): int
    {
        $colunas    = array_keys($dados);
        $marcadores = array_map(static fn (string $coluna): string => ':' . $coluna, $colunas);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $tabela,
            implode('`, `', $colunas),
            implode(', ', $marcadores)
        );

        $parametros = [];
        foreach ($dados as $coluna => $valor) {
            $parametros[':' . $coluna] = $valor;
        }

        self::query($sql, $parametros);

        return (int) self::ligacao()->lastInsertId();
    }

    /**
     * Atualiza uma linha identificada pela chave primária `id`.
     *
     * @param array<string, mixed> $dados
     */
    public static function atualizar(string $tabela, int $id, array $dados): int
    {
        if ($dados === []) {
            return 0;
        }

        $atribuicoes = [];
        $parametros  = [':id_alvo' => $id];

        foreach ($dados as $coluna => $valor) {
            $atribuicoes[]             = sprintf('`%s` = :%s', $coluna, $coluna);
            $parametros[':' . $coluna] = $valor;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = :id_alvo',
            $tabela,
            implode(', ', $atribuicoes)
        );

        return self::executar($sql, $parametros);
    }

    /** Inicia uma transação, se ainda não houver uma em curso. */
    public static function iniciarTransacao(): void
    {
        if (!self::ligacao()->inTransaction()) {
            self::ligacao()->beginTransaction();
        }
    }

    /** Confirma a transação em curso. */
    public static function confirmar(): void
    {
        if (self::ligacao()->inTransaction()) {
            self::ligacao()->commit();
        }
    }

    /** Anula a transação em curso. */
    public static function anular(): void
    {
        if (self::ligacao()->inTransaction()) {
            self::ligacao()->rollBack();
        }
    }
}
