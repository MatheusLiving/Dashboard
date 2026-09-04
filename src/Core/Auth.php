<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Autenticação e controlo de acesso.
 *
 * As palavras-passe usam ARGON2ID. A sessão guarda apenas o identificador do
 * utilizador; os restantes dados são lidos da base de dados a cada pedido,
 * para que uma desativação tenha efeito imediato.
 */
final class Auth
{
    private const CHAVE_UTILIZADOR = 'utilizador_id';
    private const CHAVE_DESTINO    = '_destino_pretendido';

    /** @var array<string, mixed>|null Cache do utilizador dentro do pedido. */
    private static ?array $utilizador = null;

    /** Comprimento mínimo exigido às palavras-passe. */
    public const MIN_SENHA = 8;

    /**
     * Gera o hash de uma palavra-passe.
     */
    public static function hash(string $senha): string
    {
        return password_hash($senha, PASSWORD_ARGON2ID);
    }

    /**
     * Tenta autenticar um utilizador. Devolve true em caso de sucesso.
     */
    public static function tentar(string $email, string $senha): bool
    {
        $utilizador = User::porEmail($email);

        // Mesmo sem utilizador correspondente, calculamos um hash para que o
        // tempo de resposta não revele se o endereço existe.
        if ($utilizador === null) {
            password_verify($senha, '$argon2id$v=19$m=65536,t=4,p=1$YmFzZWxpbmVzYWx0$0000000000000000000000000000000000000000000');

            return false;
        }

        if ((int) $utilizador['ativo'] !== 1) {
            return false;
        }

        if (!password_verify($senha, (string) $utilizador['senha_hash'])) {
            return false;
        }

        // Reforça o hash caso os parâmetros do algoritmo tenham mudado.
        if (password_needs_rehash((string) $utilizador['senha_hash'], PASSWORD_ARGON2ID)) {
            User::atualizarSenha((int) $utilizador['id'], self::hash($senha));
        }

        self::iniciarSessao((int) $utilizador['id']);

        return true;
    }

    /**
     * Marca a sessão como autenticada, regenerando o identificador.
     */
    public static function iniciarSessao(int $utilizadorId): void
    {
        Session::regenerar();
        Session::set(self::CHAVE_UTILIZADOR, $utilizadorId);
        Csrf::renovar();

        self::$utilizador = null;
    }

    /**
     * Termina a sessão e apaga o respetivo registo.
     */
    public static function terminarSessao(): void
    {
        self::$utilizador = null;
        Session::destruir();
    }

    /**
     * Indica se há um utilizador autenticado e ativo.
     */
    public static function autenticado(): bool
    {
        return self::utilizador() !== null;
    }

    /**
     * Devolve o utilizador autenticado, ou null.
     *
     * @return array<string, mixed>|null
     */
    public static function utilizador(): ?array
    {
        if (self::$utilizador !== null) {
            return self::$utilizador;
        }

        $id = Session::get(self::CHAVE_UTILIZADOR);

        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        $utilizador = User::porId((int) $id);

        // Conta apagada ou desativada durante a sessão: expulsa-se de imediato.
        if ($utilizador === null || (int) $utilizador['ativo'] !== 1) {
            Session::esquecer(self::CHAVE_UTILIZADOR);

            return null;
        }

        return self::$utilizador = $utilizador;
    }

    /**
     * Identificador do utilizador autenticado, ou null.
     */
    public static function id(): ?int
    {
        $utilizador = self::utilizador();

        return $utilizador === null ? null : (int) $utilizador['id'];
    }

    /**
     * Indica se o utilizador autenticado é administrador.
     */
    public static function admin(): bool
    {
        $utilizador = self::utilizador();

        return $utilizador !== null && $utilizador['papel'] === 'admin';
    }

    /**
     * Indica se o utilizador autenticado é administrador ou o próprio dono
     * do recurso — a regra de acesso mais usada na aplicação.
     */
    public static function adminOuProprio(?int $donoId): bool
    {
        if (self::admin()) {
            return true;
        }

        return $donoId !== null && self::id() === $donoId;
    }

    /**
     * Exige sessão iniciada; caso contrário, envia para o formulário de acesso.
     */
    public static function requireAuth(): void
    {
        if (self::autenticado()) {
            return;
        }

        if (Request::esperaJson()) {
            Response::erroJson('Sessão terminada. Inicie sessão novamente.', 401);
        }

        // Guarda o destino para regressar a ele depois da autenticação.
        Session::set(self::CHAVE_DESTINO, Request::caminho());
        Flash::aviso('Inicie sessão para aceder a esta página.');
        Response::redirecionar('/login');
    }

    /**
     * Exige que o utilizador seja administrador.
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();

        if (self::admin()) {
            return;
        }

        if (Request::esperaJson()) {
            Response::erroJson('Não tem permissões para esta operação.', 403);
        }

        Response::estado(403);
        View::render('errors/403');
        exit;
    }

    /**
     * Exige que NÃO haja sessão iniciada (páginas de acesso).
     */
    public static function requireConvidado(): void
    {
        if (self::autenticado()) {
            Response::redirecionar('/');
        }
    }

    /**
     * Devolve e limpa o destino guardado antes do pedido de autenticação.
     */
    public static function destinoPretendido(string $omissao = '/'): string
    {
        $destino = Session::retirar(self::CHAVE_DESTINO);

        return is_string($destino) && $destino !== '' && $destino !== '/login' ? $destino : $omissao;
    }
}
