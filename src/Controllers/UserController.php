<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Gestão de utilizadores, reservada a administradores.
 *
 * Não há eliminação: uma conta desativa-se com `ativo = 0`, para que as
 * tarefas e os relatórios antigos continuem a ter autor identificável.
 */
final class UserController extends Controller
{
    public function index(): void
    {
        $this->ver('utilizadores/index', [
            'utilizadores' => User::todos(false),
            'antigos'      => Flash::antigos(),
            'totalAdmins'  => User::totalAdminsAtivos(),
        ]);
    }

    /**
     * Cria uma conta.
     */
    public function criar(): void
    {
        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'nome'         => 'nome',
                'email'        => 'endereço de correio',
                'senha'        => 'palavra-passe',
                'funcao_cargo' => 'função / cargo',
                'papel'        => 'papel',
            ])
            ->obrigatorio('nome')
            ->maximo('nome', 150)
            ->obrigatorio('email')
            ->email('email')
            ->maximo('email', 190)
            ->obrigatorio('senha')
            ->minimo('senha', Auth::MIN_SENHA)
            ->maximo('senha', 200)
            ->maximo('funcao_cargo', 150)
            ->em('papel', ['admin', 'membro']);

        $email = (string) Request::post('email', '');

        $validador->regra(
            'email',
            !User::emailExiste($email),
            'Já existe uma conta com esse endereço de correio.'
        );

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/utilizadores');
        }

        $id = User::criar([
            'nome'         => (string) Request::post('nome'),
            'email'        => $email,
            'senha_hash'   => Auth::hash((string) Request::post('senha')),
            'funcao_cargo' => Request::post('funcao_cargo') ?: null,
            'papel'        => (string) Request::post('papel', 'membro'),
        ]);

        AuditLogger::criado('utilizador', $id, [
            'nome'  => Request::post('nome'),
            'email' => $email,
            'papel' => Request::post('papel', 'membro'),
        ]);

        Flash::sucesso('Conta de «' . Request::post('nome') . '» criada.');
        $this->redirecionar('/utilizadores');
    }

    /**
     * Atualiza os dados de uma conta.
     */
    public function atualizar(string $id): void
    {
        $userId = (int) $id;
        $antes  = User::porId($userId);

        if ($antes === null) {
            Flash::erro('Utilizador não encontrado.');
            $this->redirecionar('/utilizadores');
        }

        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'nome'         => 'nome',
                'email'        => 'endereço de correio',
                'funcao_cargo' => 'função / cargo',
                'papel'        => 'papel',
            ])
            ->obrigatorio('nome')
            ->maximo('nome', 150)
            ->obrigatorio('email')
            ->email('email')
            ->maximo('email', 190)
            ->maximo('funcao_cargo', 150)
            ->em('papel', ['admin', 'membro']);

        $email = (string) Request::post('email', '');
        $papel = (string) Request::post('papel', 'membro');

        $validador->regra(
            'email',
            !User::emailExiste($email, $userId),
            'Já existe uma conta com esse endereço de correio.'
        );

        // Despromover o último administrador deixaria o sistema sem quem o gerisse.
        $validador->regra(
            'papel',
            !($antes['papel'] === 'admin' && $papel !== 'admin' && User::totalAdminsAtivos() <= 1),
            'Este é o único administrador ativo: promova outro antes de o despromover.'
        );

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/utilizadores');
        }

        User::atualizar($userId, [
            'nome'         => (string) Request::post('nome'),
            'email'        => $email,
            'funcao_cargo' => Request::post('funcao_cargo') ?: null,
            'papel'        => $papel,
        ]);

        AuditLogger::atualizado('utilizador', $userId, $antes, User::porId($userId) ?? []);

        Flash::sucesso('Conta atualizada.');
        $this->redirecionar('/utilizadores');
    }

    /**
     * Ativa ou desativa uma conta.
     */
    public function alternarAtivo(string $id): void
    {
        $userId     = (int) $id;
        $utilizador = User::porId($userId);

        if ($utilizador === null) {
            Flash::erro('Utilizador não encontrado.');
            $this->redirecionar('/utilizadores');
        }

        $ativar = (int) $utilizador['ativo'] === 0;

        // Desativar-se a si próprio deixaria o administrador de fora à porta.
        if (!$ativar && $userId === (int) Auth::id()) {
            Flash::erro('Não pode desativar a sua própria conta.');
            $this->redirecionar('/utilizadores');
        }

        if (!$ativar && $utilizador['papel'] === 'admin' && User::totalAdminsAtivos() <= 1) {
            Flash::erro('Este é o único administrador ativo: promova outro antes de o desativar.');
            $this->redirecionar('/utilizadores');
        }

        User::definirAtivo($userId, $ativar);

        AuditLogger::registar(
            'utilizador',
            $userId,
            $ativar ? AuditLogger::ATIVAR : AuditLogger::DESATIVAR,
            ['nome' => $utilizador['nome'], 'email' => $utilizador['email']]
        );

        Flash::sucesso(sprintf(
            'Conta de «%s» %s.',
            $utilizador['nome'],
            $ativar ? 'reativada' : 'desativada'
        ));

        $this->redirecionar('/utilizadores');
    }

    /**
     * Define uma palavra-passe nova para uma conta.
     *
     * O administrador não vê a palavra-passe antiga: define uma nova e
     * comunica-a ao utilizador, que a deve alterar no seu perfil.
     */
    public function redefinirSenha(string $id): void
    {
        $userId     = (int) $id;
        $utilizador = User::porId($userId);

        if ($utilizador === null) {
            Flash::erro('Utilizador não encontrado.');
            $this->redirecionar('/utilizadores');
        }

        $validador = Validator::para(Request::todosPost())
            ->rotulo('senha', 'palavra-passe')
            ->obrigatorio('senha')
            ->minimo('senha', Auth::MIN_SENHA)
            ->maximo('senha', 200);

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/utilizadores');
        }

        User::atualizarSenha($userId, Auth::hash((string) Request::post('senha')));

        // A palavra-passe nunca entra no registo de auditoria.
        AuditLogger::registar('utilizador', $userId, 'redefinir_senha', [
            'nome' => $utilizador['nome'],
        ]);

        Flash::sucesso('Palavra-passe de «' . $utilizador['nome'] . '» redefinida.');
        $this->redirecionar('/utilizadores');
    }
}
