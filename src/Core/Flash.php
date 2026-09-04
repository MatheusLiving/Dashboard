<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Mensagens de uma única leitura, guardadas na sessão.
 *
 * Servem para dar retorno ao utilizador depois de um redirecionamento
 * (sucesso, erro, aviso) e desaparecem assim que são apresentadas.
 */
final class Flash
{
    private const CHAVE      = '_flash';
    private const CHAVE_ANT  = '_flash_antigos';

    /**
     * Guarda uma mensagem de sucesso.
     */
    public static function sucesso(string $mensagem): void
    {
        self::adicionar('sucesso', $mensagem);
    }

    /**
     * Guarda uma mensagem de erro.
     */
    public static function erro(string $mensagem): void
    {
        self::adicionar('erro', $mensagem);
    }

    /**
     * Guarda uma mensagem de aviso.
     */
    public static function aviso(string $mensagem): void
    {
        self::adicionar('aviso', $mensagem);
    }

    /**
     * Guarda uma mensagem informativa.
     */
    public static function info(string $mensagem): void
    {
        self::adicionar('info', $mensagem);
    }

    /**
     * Acrescenta uma mensagem de um dado tipo.
     */
    public static function adicionar(string $tipo, string $mensagem): void
    {
        $mensagens   = Session::get(self::CHAVE, []);
        $mensagens   = is_array($mensagens) ? $mensagens : [];
        $mensagens[] = ['tipo' => $tipo, 'texto' => $mensagem];

        Session::set(self::CHAVE, $mensagens);
    }

    /**
     * Devolve todas as mensagens pendentes e limpa-as.
     *
     * @return list<array{tipo: string, texto: string}>
     */
    public static function consumir(): array
    {
        $mensagens = Session::retirar(self::CHAVE, []);

        return is_array($mensagens) ? $mensagens : [];
    }

    /**
     * Guarda os valores submetidos, para repovoar o formulário após um erro.
     *
     * @param array<string, mixed> $dados
     */
    public static function guardarAntigos(array $dados): void
    {
        // A palavra-passe nunca volta para o formulário.
        unset($dados['senha'], $dados['senha_confirmacao'], $dados['_token']);

        Session::set(self::CHAVE_ANT, $dados);
    }

    /**
     * Devolve os valores submetidos no pedido anterior e limpa-os.
     *
     * @return array<string, mixed>
     */
    public static function antigos(): array
    {
        $antigos = Session::retirar(self::CHAVE_ANT, []);

        return is_array($antigos) ? $antigos : [];
    }
}
