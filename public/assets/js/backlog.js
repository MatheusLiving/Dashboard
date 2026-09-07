/**
 * Visão de backlog: arrastar tarefas para as colunas ativas do quadro.
 *
 * Cada lista de projeto e cada caixa de coluna são zonas SortableJS do mesmo
 * grupo. Ao largar uma tarefa numa coluna, o movimento é gravado pelo mesmo
 * ponto de entrada que o quadro usa e a tarefa desaparece do backlog.
 */
(function () {
    'use strict';

    if (typeof window.Sortable === 'undefined') {
        return;
    }

    /**
     * Mostra uma mensagem temporária no canto do ecrã.
     */
    function avisar(texto, erro) {
        var caixa = document.createElement('div');
        caixa.className = 'fixed bottom-4 right-4 z-50 rounded-lg px-4 py-2.5 text-sm shadow-lg ' +
            (erro ? 'bg-rose-600 text-white' : 'bg-slate-900 text-white');
        caixa.textContent = texto;
        document.body.appendChild(caixa);

        window.setTimeout(function () {
            caixa.style.transition = 'opacity .4s';
            caixa.style.opacity = '0';
            window.setTimeout(function () {
                caixa.remove();
            }, 400);
        }, 2600);
    }

    /**
     * Atualiza os contadores dos grupos e mostra o estado vazio quando é caso.
     */
    function atualizarGrupos() {
        document.querySelectorAll('[data-lista-backlog]').forEach(function (lista) {
            var seccao = lista.closest('section');
            var total = lista.querySelectorAll('[data-tarefa-backlog]').length;
            var contador = seccao ? seccao.querySelector('header span:last-child') : null;

            if (contador) {
                contador.textContent = total;
            }

            // Um projeto sem tarefas no backlog deixa de fazer sentido na página.
            if (total === 0 && seccao) {
                seccao.remove();
            }
        });

        var restantes = document.querySelectorAll('[data-tarefa-backlog]').length;
        var contadorTopo = document.querySelector('form span.ml-auto');

        if (contadorTopo) {
            contadorTopo.innerHTML = restantes + ' tarefa' + (restantes === 1 ? '' : 's') + ' em ' +
                contadorTopo.innerHTML.split(' em ')[1];
        }
    }

    /**
     * Move a tarefa para a coluna escolhida.
     */
    function mover(taskId, colunaId, elemento, nomeColuna) {
        window.pedirJson('/api/tarefas/' + taskId + '/mover', {
            method: 'POST',
            body: JSON.stringify({ column_id: colunaId, ordem: [] })
        }).then(function () {
            // A tarefa saiu do backlog: já não pertence a esta página.
            elemento.remove();
            atualizarGrupos();
            avisar('Tarefa movida para ' + nomeColuna + '.', false);
        }).catch(function (erro) {
            avisar(erro.message, true);
            window.location.reload();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Listas de origem: as tarefas de cada projeto.
        document.querySelectorAll('[data-lista-backlog]').forEach(function (lista) {
            window.Sortable.create(lista, {
                group: { name: 'backlog', pull: true, put: true },
                animation: 150,
                draggable: '[data-tarefa-backlog]',
                ghostClass: 'opacity-40'
            });
        });

        // Caixas de destino: as colunas ativas do quadro.
        document.querySelectorAll('[data-destino-coluna]').forEach(function (caixa) {
            var colunaId = parseInt(caixa.getAttribute('data-destino-coluna'), 10);
            var nome = caixa.querySelector('span.text-sm');
            var nomeColuna = nome ? nome.textContent.trim() : 'coluna';

            window.Sortable.create(caixa, {
                group: { name: 'backlog', pull: false, put: true },
                animation: 150,
                sort: false,
                draggable: '[data-tarefa-backlog]',

                onAdd: function (evento) {
                    var elemento = evento.item;
                    var taskId = parseInt(elemento.getAttribute('data-id'), 10);

                    // O cartão fica escondido enquanto o servidor confirma.
                    elemento.style.display = 'none';
                    mover(taskId, colunaId, elemento, nomeColuna);
                },

                // Realce visível enquanto se paira sobre a caixa.
                onMove: function () {
                    return true;
                }
            });

            caixa.addEventListener('dragenter', function () {
                caixa.classList.add('border-marinho-600', 'bg-marinho-50');
            });

            caixa.addEventListener('dragleave', function () {
                caixa.classList.remove('border-marinho-600', 'bg-marinho-50');
            });

            caixa.addEventListener('drop', function () {
                caixa.classList.remove('border-marinho-600', 'bg-marinho-50');
            });
        });
    });
}());
