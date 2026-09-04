/**
 * Quadro Kanban: arrastar e largar, modal de tarefa, etiquetas e registo de tempo.
 *
 * Depende de SortableJS (carregado por CDN na vista) e de window.pedirJson,
 * definido em app.js, que já envia o token CSRF.
 */
(function () {
    'use strict';

    var config = window.KANBAN || { cores: {}, etiquetas: [], colunasConcluidas: [] };

    var modal = document.querySelector('[data-modal-tarefa]');
    var formulario = document.querySelector('[data-form-tarefa]');
    var formularioTempo = document.querySelector('[data-form-tempo]');

    if (!modal || !formulario) {
        return;
    }

    /** Estado do modal: tarefa aberta e etiquetas selecionadas. */
    var estado = {
        id: null,
        colunaId: null,
        tags: [],
        disponiveis: (config.etiquetas || []).slice()
    };

    // -----------------------------------------------------------------------
    // Utilitários
    // -----------------------------------------------------------------------

    function el(seletor, raiz) {
        return (raiz || document).querySelector(seletor);
    }

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto === null || texto === undefined ? '' : String(texto);

        return div.innerHTML;
    }

    function iniciais(nome) {
        var partes = String(nome || '').trim().split(/\s+/).filter(Boolean);

        if (partes.length === 0) {
            return '?';
        }

        if (partes.length === 1) {
            return partes[0].substring(0, 2).toUpperCase();
        }

        return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
    }

    function duracaoTexto(minutos) {
        minutos = parseInt(minutos, 10) || 0;

        if (minutos <= 0) {
            return '';
        }

        var horas = Math.floor(minutos / 60);
        var resto = minutos % 60;

        if (horas === 0) {
            return resto + 'm';
        }

        if (resto === 0) {
            return horas + 'h';
        }

        return horas + 'h ' + (resto < 10 ? '0' : '') + resto + 'm';
    }

    function hoje() {
        var d = new Date();
        var mes = String(d.getMonth() + 1).padStart(2, '0');
        var dia = String(d.getDate()).padStart(2, '0');

        return d.getFullYear() + '-' + mes + '-' + dia;
    }

    function mostrarErro(mensagem) {
        var caixa = el('[data-modal-erro]');
        caixa.textContent = mensagem;
        caixa.classList.remove('hidden');
    }

    function limparErro() {
        var caixa = el('[data-modal-erro]');
        caixa.textContent = '';
        caixa.classList.add('hidden');
    }

    // -----------------------------------------------------------------------
    // Cartões
    // -----------------------------------------------------------------------

    /**
     * Constrói o HTML de um cartão. Reproduz views/partials/cartao.php.
     */
    function cartaoHtml(tarefa) {
        var cor = config.cores[tarefa.prioridade] || '#94A3B8';
        var partes = [];

        partes.push(
            '<div class="flex items-start gap-2">' +
            '<span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background-color: ' + escapar(cor) + '"' +
            ' title="Prioridade: ' + escapar(tarefa.prioridade) + '"></span>' +
            '<p class="min-w-0 flex-1 text-sm font-medium leading-snug text-slate-800">' + escapar(tarefa.titulo) + '</p>' +
            '</div>'
        );

        if (tarefa.tags && tarefa.tags.length) {
            var etiquetas = tarefa.tags.map(function (t) {
                var opacidade = Number(t.ativa) === 0 ? ' opacity-50' : '';

                return '<span class="rounded px-1.5 py-0.5 text-[10px] font-semibold leading-tight text-white' + opacidade + '"' +
                    ' style="background-color: ' + escapar(t.cor_hex) + '">' + escapar(t.nome) + '</span>';
            }).join('');

            partes.push('<div class="mt-2 flex flex-wrap gap-1">' + etiquetas + '</div>');
        }

        if (tarefa.projeto_nome) {
            partes.push('<p class="mt-2 truncate text-xs text-slate-500">' + escapar(tarefa.projeto_nome) + '</p>');
        }

        var avatar = tarefa.responsavel_nome
            ? '<span class="flex h-6 w-6 items-center justify-center rounded-full bg-marinho-100 text-[10px] font-semibold text-marinho-800"' +
              ' title="' + escapar(tarefa.responsavel_nome) + '">' + escapar(iniciais(tarefa.responsavel_nome)) + '</span>'
            : '<span class="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-slate-300 text-[10px] text-slate-400" title="Sem responsável">—</span>';

        var tempo = duracaoTexto(tarefa.total_minutos);
        var tempoHtml = tempo ? '<span class="text-[11px] text-slate-400">' + escapar(tempo) + '</span>' : '';

        partes.push('<div class="mt-3 flex items-center justify-between gap-2">' + avatar + tempoHtml + '</div>');

        return partes.join('');
    }

    /**
     * Cria o elemento de um cartão pronto a inserir numa coluna.
     */
    function criarCartao(tarefa) {
        var artigo = document.createElement('article');
        artigo.className = 'grupo-cartao cursor-pointer rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:border-marinho-300 hover:shadow';
        artigo.setAttribute('data-cartao', '');
        artigo.setAttribute('data-id', tarefa.id);
        artigo.setAttribute('tabindex', '0');
        artigo.setAttribute('role', 'button');
        artigo.innerHTML = cartaoHtml(tarefa);

        return artigo;
    }

    /**
     * Substitui um cartão existente, ou insere-o se ainda não estiver no quadro.
     */
    function colocarCartao(tarefa) {
        var existente = document.querySelector('[data-cartao][data-id="' + tarefa.id + '"]');
        var novo = criarCartao(tarefa);
        var destino = document.querySelector('[data-coluna="' + tarefa.column_id + '"]');

        if (existente) {
            if (existente.parentElement === destino) {
                existente.replaceWith(novo);
                atualizarContadores();

                return;
            }

            existente.remove();
        }

        if (destino) {
            destino.appendChild(novo);
        }

        atualizarContadores();
    }

    /**
     * Recalcula os contadores das colunas e mostra o estado vazio quando é caso.
     */
    function atualizarContadores() {
        document.querySelectorAll('[data-coluna]').forEach(function (coluna) {
            var id = coluna.getAttribute('data-coluna');
            var total = coluna.querySelectorAll('[data-cartao]').length;
            var contador = document.querySelector('[data-contador-coluna="' + id + '"]');
            var vazio = coluna.querySelector('[data-coluna-vazia]');

            if (contador) {
                contador.textContent = total;
            }

            if (total === 0 && !vazio) {
                var aviso = document.createElement('p');
                aviso.className = 'px-2 py-6 text-center text-xs text-slate-400';
                aviso.setAttribute('data-coluna-vazia', '');
                aviso.textContent = 'Sem tarefas nesta coluna.';
                coluna.appendChild(aviso);
            }

            if (total > 0 && vazio) {
                vazio.remove();
            }
        });
    }

    // -----------------------------------------------------------------------
    // Arrastar e largar
    // -----------------------------------------------------------------------

    function iniciarArrastar() {
        if (typeof window.Sortable === 'undefined') {
            return;
        }

        document.querySelectorAll('[data-coluna]').forEach(function (coluna) {
            window.Sortable.create(coluna, {
                group: 'quadro',
                animation: 150,
                draggable: '[data-cartao]',
                ghostClass: 'opacity-40',
                chosenClass: 'ring-2',
                forceFallback: false,
                onEnd: function (evento) {
                    var cartao = evento.item;
                    var destino = evento.to;
                    var colunaId = parseInt(destino.getAttribute('data-coluna'), 10);
                    var taskId = parseInt(cartao.getAttribute('data-id'), 10);

                    // Ordem completa da coluna de destino, tal como ficou no ecrã.
                    var ordem = Array.prototype.map.call(
                        destino.querySelectorAll('[data-cartao]'),
                        function (n) {
                            return parseInt(n.getAttribute('data-id'), 10);
                        }
                    );

                    atualizarContadores();

                    window.pedirJson('/api/tarefas/' + taskId + '/mover', {
                        method: 'POST',
                        body: JSON.stringify({ column_id: colunaId, ordem: ordem })
                    }).then(function (resposta) {
                        if (resposta.tarefa) {
                            // Redesenha o cartão: a data de conclusão pode ter mudado.
                            var atualizado = criarCartao(resposta.tarefa);
                            cartao.replaceWith(atualizado);
                        }
                    }).catch(function (erro) {
                        // O servidor recusou: recarrega para voltar ao estado real.
                        window.alert(erro.message);
                        window.location.reload();
                    });
                }
            });
        });
    }

    // -----------------------------------------------------------------------
    // Etiquetas dentro do modal
    // -----------------------------------------------------------------------

    function desenharTagsEscolhidas() {
        var caixa = el('[data-tags-escolhidas]');

        if (estado.tags.length === 0) {
            caixa.innerHTML = '<span class="text-xs text-slate-400">Sem etiquetas.</span>';

            return;
        }

        caixa.innerHTML = estado.tags.map(function (t) {
            return '<span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold text-white"' +
                ' style="background-color: ' + escapar(t.cor_hex) + '">' +
                escapar(t.nome) +
                '<button type="button" data-remover-tag="' + t.id + '" class="opacity-70 hover:opacity-100" aria-label="Remover">&times;</button>' +
                '</span>';
        }).join('');
    }

    function temTag(id) {
        return estado.tags.some(function (t) {
            return Number(t.id) === Number(id);
        });
    }

    function adicionarTag(etiqueta) {
        if (!temTag(etiqueta.id)) {
            estado.tags.push(etiqueta);
            desenharTagsEscolhidas();
        }

        el('[data-tag-procura]').value = '';
        esconderSugestoes();
    }

    function esconderSugestoes() {
        var lista = el('[data-tag-sugestoes]');
        lista.classList.add('hidden');
        lista.innerHTML = '';
    }

    /**
     * Mostra as etiquetas que correspondem ao texto escrito e, quando não
     * existe nenhuma com esse nome exato, a opção de criar uma nova.
     */
    function desenharSugestoes(termo) {
        var lista = el('[data-tag-sugestoes]');
        var procura = termo.trim().toLowerCase();

        if (procura === '') {
            esconderSugestoes();

            return;
        }

        var correspondencias = estado.disponiveis.filter(function (t) {
            return t.nome.toLowerCase().indexOf(procura) !== -1 && !temTag(t.id);
        });

        var exata = estado.disponiveis.some(function (t) {
            return t.nome.toLowerCase() === procura;
        });

        var itens = correspondencias.map(function (t) {
            return '<li><button type="button" data-escolher-tag="' + t.id + '"' +
                ' class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-slate-50">' +
                '<span class="h-2.5 w-2.5 rounded-full" style="background-color: ' + escapar(t.cor_hex) + '"></span>' +
                escapar(t.nome) + '</button></li>';
        });

        if (!exata) {
            itens.push(
                '<li class="border-t border-slate-100"><button type="button" data-criar-tag' +
                ' class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm font-medium text-marinho-800 hover:bg-marinho-50">' +
                '+ Criar etiqueta «' + escapar(termo.trim()) + '»</button></li>'
            );
        }

        if (itens.length === 0) {
            esconderSugestoes();

            return;
        }

        lista.innerHTML = itens.join('');
        lista.classList.remove('hidden');
    }

    function criarTagInline(nome) {
        return window.pedirJson('/api/tags', {
            method: 'POST',
            body: JSON.stringify({ nome: nome, cor_hex: '#1F3864' })
        }).then(function (resposta) {
            var etiqueta = resposta.etiqueta;

            var jaConhecida = estado.disponiveis.some(function (t) {
                return Number(t.id) === Number(etiqueta.id);
            });

            if (!jaConhecida) {
                estado.disponiveis.push(etiqueta);
            }

            adicionarTag(etiqueta);
        }).catch(function (erro) {
            mostrarErro(erro.message);
        });
    }

    // -----------------------------------------------------------------------
    // Modal
    // -----------------------------------------------------------------------

    function abrirModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function fecharModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        limparErro();
        esconderSugestoes();
    }

    function limparFormulario() {
        formulario.reset();
        formulario.elements.id.value = '';
        estado.id = null;
        estado.tags = [];
        desenharTagsEscolhidas();

        el('[data-seccao-existente]').classList.add('hidden');
        el('[data-eliminar-tarefa]').classList.add('hidden');
        el('[data-info-estado]').classList.add('hidden');
    }

    /**
     * Abre o modal em modo de criação, na coluna indicada.
     */
    function novaTarefa(colunaId) {
        limparFormulario();
        estado.colunaId = colunaId;
        formulario.elements.column_id.value = colunaId;
        el('[data-modal-cabecalho]').textContent = 'Nova tarefa';
        abrirModal();
        formulario.elements.titulo.focus();
    }

    /**
     * Abre o modal com os dados de uma tarefa existente.
     */
    function abrirTarefa(id) {
        limparFormulario();
        limparErro();

        window.pedirJson('/api/tarefas/' + id, { method: 'GET' }).then(function (resposta) {
            var t = resposta.tarefa;

            estado.id = Number(t.id);
            estado.colunaId = Number(t.column_id);
            estado.tags = resposta.tags || [];

            formulario.elements.id.value = t.id;
            formulario.elements.column_id.value = t.column_id;
            formulario.elements.titulo.value = t.titulo || '';
            formulario.elements.descricao.value = t.descricao || '';
            formulario.elements.assignee_id.value = t.assignee_id || '';
            formulario.elements.project_id.value = t.project_id || '';
            formulario.elements.prioridade.value = t.prioridade || 'media';
            formulario.elements.data_inicio.value = t.data_inicio || '';
            formulario.elements.estimativa_min.value = t.estimativa_min || '';
            formulario.elements.dificuldades.value = t.dificuldades || '';

            el('[data-modal-cabecalho]').textContent = t.titulo;
            desenharTagsEscolhidas();

            el('[data-info-estado]').classList.remove('hidden');
            el('[data-info-coluna]').textContent = t.coluna_nome || '—';
            el('[data-info-conclusao]').textContent = t.data_conclusao
                ? new Date(t.data_conclusao + 'T00:00:00').toLocaleDateString('pt-PT')
                : '—';
            el('[data-info-criador]').textContent = t.criador_nome || '—';

            el('[data-seccao-existente]').classList.remove('hidden');
            el('[data-eliminar-tarefa]').classList.toggle('hidden', !resposta.podeEliminar);

            desenharTempo(resposta.tempo, resposta.total_minutos);
            desenharHistorico(resposta.historico);

            formularioTempo.elements.data.value = hoje();

            abrirModal();
        }).catch(function (erro) {
            window.alert(erro.message);
        });
    }

    function desenharTempo(registos, total) {
        var lista = el('[data-lista-tempo]');
        el('[data-tempo-total]').textContent = total > 0 ? 'Total: ' + duracaoTexto(total) : '';

        if (!registos || registos.length === 0) {
            lista.innerHTML = '<li class="py-2 text-xs text-slate-400">Ainda não há tempo registado.</li>';

            return;
        }

        lista.innerHTML = registos.map(function (r) {
            var data = new Date(r.data + 'T00:00:00').toLocaleDateString('pt-PT');
            var nota = r.nota ? ' · ' + escapar(r.nota) : '';

            return '<li class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-slate-50">' +
                '<span class="min-w-0 truncate text-xs text-slate-600">' +
                '<strong class="text-slate-800">' + escapar(r.duracao) + '</strong> · ' + escapar(data) +
                ' · ' + escapar(r.utilizador || '—') + nota +
                '</span>' +
                '<button type="button" data-eliminar-tempo="' + r.id + '"' +
                ' class="shrink-0 text-xs text-slate-300 hover:text-rose-600" aria-label="Eliminar registo">&times;</button>' +
                '</li>';
        }).join('');
    }

    function desenharHistorico(entradas) {
        var lista = el('[data-lista-historico]');

        if (!entradas || entradas.length === 0) {
            lista.innerHTML = '<li class="text-xs text-slate-400">Sem histórico.</li>';

            return;
        }

        var rotulos = {
            criacao: 'Criação',
            movimento: 'Movimento',
            comentario: 'Comentário',
            edicao: 'Edição',
            conclusao: 'Conclusão'
        };

        lista.innerHTML = entradas.map(function (a) {
            var quando = new Date(a.created_at.replace(' ', 'T')).toLocaleString('pt-PT', {
                day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
            });

            return '<li class="flex items-start gap-2">' +
                '<span class="mt-0.5 shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">' +
                escapar(rotulos[a.tipo] || a.tipo) + '</span>' +
                '<span class="min-w-0 flex-1">' +
                '<span class="block text-xs text-slate-700">' + escapar(a.descricao || '—') + '</span>' +
                '<span class="block text-[11px] text-slate-400">' + escapar(a.utilizador || '—') + ' · ' + escapar(quando) + '</span>' +
                '</span></li>';
        }).join('');
    }

    /**
     * Guarda a tarefa: cria ou atualiza, conforme haja identificador.
     */
    function guardar() {
        limparErro();

        var botao = el('[data-guardar-tarefa]');
        var dados = {
            titulo: formulario.elements.titulo.value.trim(),
            descricao: formulario.elements.descricao.value,
            column_id: parseInt(formulario.elements.column_id.value, 10),
            assignee_id: formulario.elements.assignee_id.value || null,
            project_id: formulario.elements.project_id.value || null,
            prioridade: formulario.elements.prioridade.value,
            data_inicio: formulario.elements.data_inicio.value || null,
            estimativa_min: formulario.elements.estimativa_min.value || null,
            dificuldades: formulario.elements.dificuldades.value,
            tags: estado.tags.map(function (t) {
                return t.id;
            })
        };

        if (dados.titulo === '') {
            mostrarErro('Indique o título da tarefa.');

            return;
        }

        var url = estado.id ? '/api/tarefas/' + estado.id : '/api/tarefas';

        botao.disabled = true;

        window.pedirJson(url, {
            method: 'POST',
            body: JSON.stringify(dados)
        }).then(function (resposta) {
            colocarCartao(resposta.tarefa);
            fecharModal();
        }).catch(function (erro) {
            mostrarErro(erro.message);
        }).finally(function () {
            botao.disabled = false;
        });
    }

    function eliminarTarefa() {
        if (!estado.id) {
            return;
        }

        if (!window.confirm('Eliminar esta tarefa? A ação não pode ser anulada.')) {
            return;
        }

        window.pedirJson('/api/tarefas/' + estado.id + '/eliminar', { method: 'POST' })
            .then(function () {
                var cartao = document.querySelector('[data-cartao][data-id="' + estado.id + '"]');

                if (cartao) {
                    cartao.remove();
                }

                atualizarContadores();
                fecharModal();
            })
            .catch(function (erro) {
                mostrarErro(erro.message);
            });
    }

    // -----------------------------------------------------------------------
    // Ligação dos eventos
    // -----------------------------------------------------------------------

    function ligarEventos() {
        // Abrir cartão existente (rato e teclado).
        document.addEventListener('click', function (evento) {
            var cartao = evento.target.closest('[data-cartao]');

            if (cartao) {
                abrirTarefa(cartao.getAttribute('data-id'));
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter' && evento.key !== ' ') {
                return;
            }

            var cartao = evento.target.closest('[data-cartao]');

            if (cartao) {
                evento.preventDefault();
                abrirTarefa(cartao.getAttribute('data-id'));
            }
        });

        // Nova tarefa a partir do rodapé de cada coluna.
        document.querySelectorAll('[data-nova-tarefa]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                novaTarefa(parseInt(botao.getAttribute('data-nova-tarefa'), 10));
            });
        });

        // Fechar o modal.
        modal.querySelectorAll('[data-fechar-modal]').forEach(function (botao) {
            botao.addEventListener('click', fecharModal);
        });

        modal.addEventListener('click', function (evento) {
            if (evento.target === modal) {
                fecharModal();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !modal.classList.contains('hidden')) {
                fecharModal();
            }
        });

        el('[data-guardar-tarefa]').addEventListener('click', guardar);
        el('[data-eliminar-tarefa]').addEventListener('click', eliminarTarefa);

        formulario.addEventListener('submit', function (evento) {
            evento.preventDefault();
            guardar();
        });

        // Campo de etiquetas com sugestões.
        var campoTag = el('[data-tag-procura]');

        campoTag.addEventListener('input', function () {
            desenharSugestoes(campoTag.value);
        });

        campoTag.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter') {
                return;
            }

            evento.preventDefault();
            var termo = campoTag.value.trim();

            if (termo === '') {
                return;
            }

            var existente = estado.disponiveis.find(function (t) {
                return t.nome.toLowerCase() === termo.toLowerCase();
            });

            if (existente) {
                adicionarTag(existente);
            } else {
                criarTagInline(termo);
            }
        });

        // Cliques nas sugestões e nas etiquetas já escolhidas.
        modal.addEventListener('click', function (evento) {
            var escolher = evento.target.closest('[data-escolher-tag]');

            if (escolher) {
                var id = Number(escolher.getAttribute('data-escolher-tag'));
                var etiqueta = estado.disponiveis.find(function (t) {
                    return Number(t.id) === id;
                });

                if (etiqueta) {
                    adicionarTag(etiqueta);
                }

                return;
            }

            if (evento.target.closest('[data-criar-tag]')) {
                criarTagInline(campoTag.value.trim());

                return;
            }

            var remover = evento.target.closest('[data-remover-tag]');

            if (remover) {
                var alvo = Number(remover.getAttribute('data-remover-tag'));
                estado.tags = estado.tags.filter(function (t) {
                    return Number(t.id) !== alvo;
                });
                desenharTagsEscolhidas();

                return;
            }

            var eliminarTempo = evento.target.closest('[data-eliminar-tempo]');

            if (eliminarTempo) {
                window.pedirJson('/api/tempo/' + eliminarTempo.getAttribute('data-eliminar-tempo') + '/eliminar', {
                    method: 'POST'
                }).then(function (resposta) {
                    desenharTempo(resposta.tempo, resposta.total_minutos);
                    if (estado.id) {
                        atualizarCartaoTempo(estado.id, resposta.total_minutos);
                    }
                }).catch(function (erro) {
                    mostrarErro(erro.message);
                });
            }
        });

        document.addEventListener('click', function (evento) {
            if (!evento.target.closest('[data-tag-procura]') && !evento.target.closest('[data-tag-sugestoes]')) {
                esconderSugestoes();
            }
        });

        // Registo de tempo.
        formularioTempo.addEventListener('submit', function (evento) {
            evento.preventDefault();
            limparErro();

            if (!estado.id) {
                return;
            }

            window.pedirJson('/api/tarefas/' + estado.id + '/tempo', {
                method: 'POST',
                body: JSON.stringify({
                    duracao: formularioTempo.elements.duracao.value,
                    data: formularioTempo.elements.data.value,
                    nota: formularioTempo.elements.nota.value
                })
            }).then(function (resposta) {
                desenharTempo(resposta.tempo, resposta.total_minutos);
                atualizarCartaoTempo(estado.id, resposta.total_minutos);
                formularioTempo.elements.duracao.value = '';
                formularioTempo.elements.nota.value = '';
            }).catch(function (erro) {
                mostrarErro(erro.message);
            });
        });
    }

    /**
     * Atualiza o tempo apresentado no cartão sem recarregar o quadro.
     */
    function atualizarCartaoTempo(taskId, totalMinutos) {
        var cartao = document.querySelector('[data-cartao][data-id="' + taskId + '"]');

        if (!cartao) {
            return;
        }

        var rodape = cartao.lastElementChild;

        if (!rodape) {
            return;
        }

        var etiquetaTempo = rodape.querySelector('span:last-child');
        var texto = duracaoTexto(totalMinutos);

        if (etiquetaTempo && etiquetaTempo.classList.contains('text-[11px]')) {
            if (texto) {
                etiquetaTempo.textContent = texto;
            } else {
                etiquetaTempo.remove();
            }

            return;
        }

        if (texto) {
            var novo = document.createElement('span');
            novo.className = 'text-[11px] text-slate-400';
            novo.textContent = texto;
            rodape.appendChild(novo);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        iniciarArrastar();
        ligarEventos();
        desenharTagsEscolhidas();
    });
}());
