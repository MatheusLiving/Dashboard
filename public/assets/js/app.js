/**
 * Comportamentos gerais da interface.
 *
 * Sem dependências: o SortableJS só entra na página do quadro Kanban.
 */
(function () {
    'use strict';

    /**
     * Abre e fecha a barra lateral em ecrãs pequenos.
     */
    function iniciarMenuLateral() {
        var botao = document.querySelector('[data-alternar-menu]');
        var menu = document.querySelector('[data-menu-lateral]');
        var fundo = document.querySelector('[data-fundo-menu]');

        if (!botao || !menu) {
            return;
        }

        function alternar(abrir) {
            menu.classList.toggle('-translate-x-full', !abrir);
            if (fundo) {
                fundo.classList.toggle('hidden', !abrir);
            }
        }

        botao.addEventListener('click', function () {
            alternar(menu.classList.contains('-translate-x-full'));
        });

        if (fundo) {
            fundo.addEventListener('click', function () {
                alternar(false);
            });
        }

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                alternar(false);
            }
        });
    }

    /**
     * Fecho manual das mensagens e desaparecimento automático das de sucesso.
     */
    function iniciarMensagens() {
        document.querySelectorAll('[data-fechar-flash]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var caixa = botao.closest('div[role="status"]');
                if (caixa) {
                    caixa.remove();
                }
            });
        });

        document.querySelectorAll('div[role="status"]').forEach(function (caixa) {
            if (!caixa.className.includes('emerald')) {
                return;
            }

            window.setTimeout(function () {
                caixa.style.transition = 'opacity .4s';
                caixa.style.opacity = '0';
                window.setTimeout(function () {
                    caixa.remove();
                }, 400);
            }, 5000);
        });
    }

    /**
     * Pedido JSON com o token CSRF já incluído.
     * Fica disponível em window.pedirJson para as páginas que usam fetch.
     */
    window.pedirJson = function (url, opcoes) {
        opcoes = opcoes || {};

        var cabecalhos = Object.assign({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        }, opcoes.headers || {});

        return fetch(url, Object.assign({}, opcoes, {
            headers: cabecalhos,
            credentials: 'same-origin'
        })).then(function (resposta) {
            return resposta.json().then(function (dados) {
                if (!resposta.ok) {
                    throw new Error(dados.mensagem || 'Ocorreu um erro no pedido.');
                }

                return dados;
            });
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        iniciarMenuLateral();
        iniciarMensagens();
    });
}());
