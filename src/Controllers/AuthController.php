<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;
use App\Models\User;

/**
 * Início e fim de sessão, e gestão do perfil do próprio utilizador.
 */
final class AuthController extends Controller
{
    /**
     * Formulário de início de sessão.
     */
    public function formulario(): void
    {
        $this->ver('auth/login', [
            'antigos' => Flash::antigos(),
        ], 'layout/publico');
    }

    /**
     * Valida as credenciais e inicia a sessão.
     */
    public function autenticar(): void
    {
        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'email' => 'endereço de correio',
                'senha' => 'palavra-passe',
            ])
            ->obrigatorio('email')
            ->email('email')
            ->obrigatorio('senha');

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/login');
        }

        $email = (string) Request::post('email');
        $senha = (string) Request::post('senha');

        if (!Auth::tentar($email, $senha)) {
            // A mensagem é deliberadamente vaga: não revela se o endereço
            // existe, nem se a conta está desativada.
            Flash::guardarAntigos(['email' => $email]);
            Flash::erro('Credenciais inválidas ou conta inativa.');

            $this->redirecionar('/login');
        }

        Flash::sucesso('Sessão iniciada. Bem-vindo(a) de volta.');

        $this->redirecionar(Auth::destinoPretendido('/'));
    }

    /**
     * Termina a sessão e destrói o respetivo registo.
     */
    public function terminar(): void
    {
        Auth::terminarSessao();

        // A sessão foi destruída, por isso a mensagem tem de ser criada de novo.
        \App\Core\Session::iniciar();
        Flash::info('Sessão terminada.');

        $this->redirecionar('/login');
    }

    /**
     * Página de perfil do utilizador autenticado.
     */
    public function perfil(): void
    {
        $this->ver('auth/perfil', [
            'utilizador' => Auth::utilizador(),
            'antigos'    => Flash::antigos(),
        ]);
    }

    /**
     * Guarda o nome, o endereço e a função do próprio utilizador.
     */
    public function guardarPerfil(): void
    {
        $id = (int) Auth::id();

        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'nome'         => 'nome',
                'email'        => 'endereço de correio',
                'funcao_cargo' => 'função / cargo',
            ])
            ->obrigatorio('nome')
            ->maximo('nome', 150)
            ->obrigatorio('email')
            ->email('email')
            ->maximo('email', 190)
            ->maximo('funcao_cargo', 150);

        $email = (string) Request::post('email', '');

        $validador->regra(
            'email',
            !User::emailExiste($email, $id),
            'Já existe uma conta com esse endereço de correio.'
        );

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/perfil');
        }

        User::atualizar($id, [
            'nome'         => (string) Request::post('nome'),
            'email'        => $email,
            'funcao_cargo' => Request::post('funcao_cargo') ?: null,
        ]);

        Flash::sucesso('Perfil atualizado.');

        $this->redirecionar('/perfil');
    }

    /**
     * Altera a palavra-passe do próprio utilizador.
     */
    public function alterarSenha(): void
    {
        $id = (int) Auth::id();

        $validador = Validator::para(Request::todosPost())
            ->rotulos([
                'senha_atual'       => 'palavra-passe atual',
                'senha'             => 'nova palavra-passe',
                'senha_confirmacao' => 'confirmação da palavra-passe',
            ])
            ->obrigatorio('senha_atual')
            ->obrigatorio('senha')
            ->minimo('senha', Auth::MIN_SENHA)
            ->maximo('senha', 200)
            ->confirmado('senha', 'senha_confirmacao');

        $hashAtual = User::hashAtual($id);

        $validador->regra(
            'senha_atual',
            $hashAtual !== null && password_verify((string) Request::post('senha_atual'), $hashAtual),
            'A palavra-passe atual não está correta.'
        );

        if ($validador->falhou()) {
            $this->voltarComErros($validador->erros(), '/perfil');
        }

        User::atualizarSenha($id, Auth::hash((string) Request::post('senha')));

        Flash::sucesso('Palavra-passe alterada.');

        $this->redirecionar('/perfil');
    }
}
