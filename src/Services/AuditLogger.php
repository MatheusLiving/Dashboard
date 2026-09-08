<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use Throwable;

/**
 * Registo de auditoria.
 *
 * Guarda em `audit_log` quem alterou o quê e quando, com o estado anterior e
 * posterior em `dados_json`. A auditoria nunca interrompe a operação em curso:
 * se falhar, o erro é apenas registado no log de erros.
 */
final class AuditLogger
{
    public const CRIAR      = 'criar';
    public const ATUALIZAR  = 'atualizar';
    public const ELIMINAR   = 'eliminar';
    public const MOVER      = 'mover';
    public const ENTREGAR   = 'entregar';
    public const REABRIR    = 'reabrir';
    public const GERAR      = 'gerar';
    public const ENVIAR     = 'enviar_email';
    public const DESATIVAR  = 'desativar';
    public const ATIVAR     = 'ativar';

    /**
     * Regista uma criação.
     *
     * @param array<string, mixed> $depois
     */
    public static function criado(string $entidade, int $entidadeId, array $depois): void
    {
        self::registar($entidade, $entidadeId, self::CRIAR, ['depois' => $depois]);
    }

    /**
     * Regista uma alteração, guardando apenas os campos que mudaram.
     *
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $depois
     */
    public static function atualizado(string $entidade, int $entidadeId, array $antes, array $depois): void
    {
        $alteracoes = self::diferencas($antes, $depois);

        // Nada mudou de facto: não vale a pena poluir a auditoria.
        if ($alteracoes === []) {
            return;
        }

        self::registar($entidade, $entidadeId, self::ATUALIZAR, $alteracoes);
    }

    /**
     * Regista uma eliminação, guardando o estado que desapareceu.
     *
     * @param array<string, mixed> $antes
     */
    public static function eliminado(string $entidade, int $entidadeId, array $antes): void
    {
        self::registar($entidade, $entidadeId, self::ELIMINAR, ['antes' => $antes]);
    }

    /**
     * Regista uma ação com dados livres.
     *
     * @param array<string, mixed> $dados
     */
    public static function registar(string $entidade, ?int $entidadeId, string $acao, array $dados = []): void
    {
        try {
            Database::inserir('audit_log', [
                'user_id'     => Auth::id(),
                'entidade'    => mb_substr($entidade, 0, 60),
                'entidade_id' => $entidadeId,
                'acao'        => mb_substr($acao, 0, 40),
                'dados_json'  => $dados === []
                    ? null
                    : json_encode(self::limpar($dados), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip'          => Request::ip(),
            ]);
        } catch (Throwable $e) {
            // A auditoria é secundária: nunca deve fazer falhar a operação.
            error_log('Falha ao registar auditoria: ' . $e->getMessage());
        }
    }

    /**
     * Campos que mudaram entre dois estados, com o valor antigo e o novo.
     *
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $depois
     * @return array<string, array{antes: mixed, depois: mixed}>
     */
    private static function diferencas(array $antes, array $depois): array
    {
        $alteracoes = [];

        foreach ($depois as $campo => $valorNovo) {
            $valorAntigo = $antes[$campo] ?? null;

            // Comparação solta: o PDO pode devolver números como inteiros e o
            // formulário entrega-os como texto.
            if ((string) ($valorAntigo ?? '') === (string) ($valorNovo ?? '')) {
                continue;
            }

            $alteracoes[$campo] = ['antes' => $valorAntigo, 'depois' => $valorNovo];
        }

        return $alteracoes;
    }

    /**
     * Remove do registo campos que nunca devem ser guardados.
     *
     * @param array<string, mixed> $dados
     * @return array<string, mixed>
     */
    private static function limpar(array $dados): array
    {
        $proibidos = ['senha', 'senha_hash', 'senha_confirmacao', 'senha_atual', '_token', 'payload'];

        array_walk_recursive($dados, static function (mixed &$valor, string|int $chave) use ($proibidos): void {
            if (is_string($chave) && in_array($chave, $proibidos, true)) {
                $valor = '[omitido]';
            }
        });

        return $dados;
    }
}
