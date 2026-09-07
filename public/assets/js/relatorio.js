/**
 * Formulário do relatório semanal.
 *
 * Trata das linhas dinâmicas das tabelas, do campo condicional de sugestão,
 * da mudança de semana e da reposição das propostas vindas do quadro.
 */
(function () {
    'use strict';

    var formulario = document.querySelector('[data-form-relatorio]');

    if (!formulario) {
        return;
    }

    // -----------------------------------------------------------------------
    // Linhas das tabelas
    // -----------------------------------------------------------------------

    /**
     * Atualiza o contador e o estado vazio de uma tabela.
     */
    function atualizarTabela(tabela) {
        var corpo = document.querySelector('[data-corpo-tabela="' + tabela + '"]');

        if (!corpo) {
            return;
        }

        var total = corpo.querySelectorAll('[data-linha]').length;
        var contador = document.querySelector('[data-contador-linhas="' + tabela + '"]');
        var vazio = document.querySelector('[data-tabela-vazia="' + tabela + '"]');

        if (contador) {
            contador.textContent = total + ' linha' + (total === 1 ? '' : 's');
        }

        if (vazio) {
            vazio.classList.toggle('hidden', total > 0);
        }
    }

    /**
     * Acrescenta uma linha vazia ao fim de uma tabela.
     */
    function acrescentarLinha(tabela) {
        var modelo = document.querySelector('[data-modelo-linha="' + tabela + '"]');
        var corpo = document.querySelector('[data-corpo-tabela="' + tabela + '"]');

        if (!modelo || !corpo) {
            return null;
        }

        var linha = modelo.content.firstElementChild.cloneNode(true);
        corpo.appendChild(linha);
        atualizarTabela(tabela);

        return linha;
    }

    /**
     * Substitui todas as linhas de uma tabela pelos dados recebidos.
     */
    function preencherTabela(tabela, dados) {
        var corpo = document.querySelector('[data-corpo-tabela="' + tabela + '"]');

        if (!corpo) {
            return;
        }

        corpo.innerHTML = '';

        dados.forEach(function (registo) {
            var linha = acrescentarLinha(tabela);

            if (!linha) {
                return;
            }

            Object.keys(registo).forEach(function (campo) {
                var campoHtml = linha.querySelector('[name="' + tabela + '_' + campo + '[]"]');

                if (campoHtml) {
                    campoHtml.value = registo[campo] === null ? '' : registo[campo];
                }
            });
        });

        atualizarTabela(tabela);
    }

    // Acrescentar linha.
    document.querySelectorAll('[data-adicionar-linha]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var tabela = botao.getAttribute('data-adicionar-linha');
            var linha = acrescentarLinha(tabela);

            if (linha) {
                var primeiro = linha.querySelector('input:not([type="hidden"]), textarea');

                if (primeiro) {
                    primeiro.focus();
                }
            }
        });
    });

    // Remover linha.
    formulario.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-remover-linha]');

        if (!botao) {
            return;
        }

        var linha = botao.closest('[data-linha]');
        var corpo = linha ? linha.closest('[data-corpo-tabela]') : null;

        if (!linha || !corpo) {
            return;
        }

        var tabela = corpo.getAttribute('data-corpo-tabela');
        linha.remove();
        atualizarTabela(tabela);
    });

    // -----------------------------------------------------------------------
    // Repor a proposta vinda do quadro
    // -----------------------------------------------------------------------

    document.querySelectorAll('[data-repor-seccao]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var tabela = botao.getAttribute('data-repor-seccao');
            var corpo = document.querySelector('[data-corpo-tabela="' + tabela + '"]');
            var existentes = corpo ? corpo.querySelectorAll('[data-linha]').length : 0;

            if (existentes > 0 && !window.confirm('Isto substitui as linhas desta secção pela proposta do quadro. Continuar?')) {
                return;
            }

            var ano = formulario.elements.ano.value;
            var semana = formulario.elements.numero_semana.value;

            botao.disabled = true;
            botao.textContent = 'A repor…';

            window.pedirJson('/api/relatorios/pre-preencher?ano=' + ano + '&semana=' + semana, { method: 'GET' })
                .then(function (resposta) {
                    preencherTabela(tabela, resposta.linhas[tabela] || []);
                })
                .catch(function (erro) {
                    window.alert(erro.message);
                })
                .finally(function () {
                    botao.disabled = false;
                    botao.textContent = 'Repor a partir do quadro';
                });
        });
    });

    // -----------------------------------------------------------------------
    // Sugestões: o campo só aparece quando a resposta é «Sim»
    // -----------------------------------------------------------------------

    var caixaSugestao = document.querySelector('[data-caixa-sugestao]');
    var avisoSemSugestao = document.querySelector('[data-aviso-sem-sugestao]');

    document.querySelectorAll('[data-radio-sugestao]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var sim = radio.value === '1' && radio.checked;

            if (caixaSugestao) {
                caixaSugestao.classList.toggle('hidden', !sim);
            }

            if (avisoSemSugestao) {
                avisoSemSugestao.classList.toggle('hidden', sim);
            }

            if (sim) {
                var campo = caixaSugestao ? caixaSugestao.querySelector('textarea') : null;

                if (campo) {
                    campo.focus();
                }
            }
        });
    });

    // -----------------------------------------------------------------------
    // Mudança de semana
    // -----------------------------------------------------------------------

    var seletorSemana = document.querySelector('[data-seletor-semana]');

    if (seletorSemana) {
        seletorSemana.addEventListener('change', function () {
            var partes = seletorSemana.value.split('-');

            if (partes.length !== 2) {
                return;
            }

            if (!window.confirm('Mudar de semana. As alterações não guardadas perdem-se. Continuar?')) {
                // Repõe a semana anterior no seletor.
                seletorSemana.value = formulario.elements.ano.value + '-' + formulario.elements.numero_semana.value;

                return;
            }

            window.location.href = '/relatorios/nova?ano=' + partes[0] + '&semana=' + partes[1];
        });
    }

    // -----------------------------------------------------------------------
    // Submissão
    // -----------------------------------------------------------------------

    var campoAccao = document.querySelector('[data-campo-accao]');

    document.querySelectorAll('[data-guardar]').forEach(function (botao) {
        botao.addEventListener('click', function (evento) {
            var accao = botao.getAttribute('data-guardar');

            if (accao === 'entregar') {
                var resumo = formulario.elements.resumo_executivo.value.trim();

                if (resumo.length < 20) {
                    evento.preventDefault();
                    window.alert('O resumo executivo é obrigatório para entregar e deve ter pelo menos 20 caracteres.');
                    formulario.elements.resumo_executivo.focus();

                    return;
                }

                var sim = document.querySelector('[data-radio-sugestao][value="1"]');

                if (sim && sim.checked && formulario.elements.sugestao_texto.value.trim() === '') {
                    evento.preventDefault();
                    window.alert('Indicou que tem uma sugestão de melhoria: escreva-a antes de entregar.');
                    formulario.elements.sugestao_texto.focus();

                    return;
                }

                if (!window.confirm('Entregar o relatório desta semana? O conteúdo fica congelado e deixa de poder ser editado.')) {
                    evento.preventDefault();

                    return;
                }
            }

            if (campoAccao) {
                campoAccao.value = accao;
            }
        });
    });

    // Eliminar rascunho.
    var botaoEliminar = document.querySelector('[data-eliminar-rascunho]');
    var formEliminar = document.querySelector('[data-form-eliminar]');

    if (botaoEliminar && formEliminar) {
        botaoEliminar.addEventListener('click', function () {
            if (window.confirm('Eliminar este rascunho? O trabalho escrito perde-se.')) {
                formEliminar.submit();
            }
        });
    }

    // Avisa antes de sair com alterações por guardar.
    var alterado = false;

    formulario.addEventListener('input', function () {
        alterado = true;
    });

    formulario.addEventListener('submit', function () {
        alterado = false;
    });

    window.addEventListener('beforeunload', function (evento) {
        if (alterado) {
            evento.preventDefault();
            evento.returnValue = '';
        }
    });
}());
