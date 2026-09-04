<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Gestor de sessões que guarda os dados na tabela `sessions`.
 *
 * Manter as sessões em base de dados permite listá-las, invalidá-las à
 * distância e registar o IP e o agente de cada uma — coisas que os ficheiros
 * de sessão do PHP não dão.
 */
final class SessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private readonly int $duracao)
    {
    }

    /** Não há nada a abrir: a ligação PDO é criada a pedido. */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * Lê o conteúdo da sessão, desde que ainda esteja dentro do prazo.
     */
    public function read(string $id): string|false
    {
        $linha = Database::primeiro(
            'SELECT payload, last_activity FROM sessions WHERE id = :id LIMIT 1',
            [':id' => $id]
        );

        if ($linha === null) {
            return '';
        }

        // Sessão expirada: trata-se como inexistente e limpa-se o registo.
        if ((int) $linha['last_activity'] + $this->duracao < time()) {
            $this->destroy($id);

            return '';
        }

        return (string) $linha['payload'];
    }

    /**
     * Grava a sessão, criando ou atualizando o registo correspondente.
     */
    public function write(string $id, string $data): bool
    {
        $utilizadorId = $_SESSION['utilizador_id'] ?? null;

        Database::executar(
            'INSERT INTO sessions (id, user_id, payload, last_activity, ip, user_agent)
             VALUES (:id, :user_id, :payload, :last_activity, :ip, :user_agent)
             ON DUPLICATE KEY UPDATE
                user_id       = VALUES(user_id),
                payload       = VALUES(payload),
                last_activity = VALUES(last_activity),
                ip            = VALUES(ip),
                user_agent    = VALUES(user_agent)',
            [
                ':id'            => $id,
                ':user_id'       => $utilizadorId === null ? null : (int) $utilizadorId,
                ':payload'       => $data,
                ':last_activity' => time(),
                ':ip'            => self::ip(),
                ':user_agent'    => self::agente(),
            ]
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        Database::executar('DELETE FROM sessions WHERE id = :id', [':id' => $id]);

        return true;
    }

    /**
     * Recolha de sessões expiradas. Devolve o número de registos removidos.
     */
    public function gc(int $max_lifetime): int|false
    {
        return Database::executar(
            'DELETE FROM sessions WHERE last_activity < :limite',
            [':limite' => time() - $max_lifetime]
        );
    }

    /**
     * Gera identificadores de sessão com entropia criptográfica.
     */
    public function create_sid(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Confirma se um identificador de sessão já existe em base de dados.
     */
    public function validateId(string $id): bool
    {
        $existe = Database::valor(
            'SELECT 1 FROM sessions WHERE id = :id LIMIT 1',
            [':id' => $id]
        );

        return $existe !== null;
    }

    /**
     * Atualiza apenas a marca temporal, quando o conteúdo não mudou.
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        Database::executar(
            'UPDATE sessions SET last_activity = :agora WHERE id = :id',
            [':agora' => time(), ':id' => $id]
        );

        return true;
    }

    /** Endereço IP do cliente, truncado ao tamanho da coluna. */
    private static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /** Agente do cliente, truncado ao tamanho da coluna. */
    private static function agente(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
