<?php

declare(strict_types=1);

/**
 * Tarefas de exemplo espalhadas pelas colunas do quadro, com etiquetas,
 * registos de tempo e histórico de atividade dentro da semana ISO corrente.
 *
 * O objetivo é que, logo após o seed, o gerador de relatórios tenha dados
 * reais para pré-preencher todas as secções.
 */

use App\Core\Database;
use App\Core\Semana;

return static function (): void {
    // Mapas de apoio: nome/email/slug para identificador.
    $mapear = static function (string $sql, string $chave): array {
        $mapa = [];
        foreach (Database::todos($sql) as $linha) {
            $mapa[(string) $linha[$chave]] = (int) $linha['id'];
        }

        return $mapa;
    };

    $utilizadores = $mapear('SELECT id, email FROM users', 'email');
    $colunas      = $mapear('SELECT id, nome FROM board_columns', 'nome');
    $projetos     = $mapear('SELECT id, nome FROM projects', 'nome');
    $etiquetas    = $mapear('SELECT id, slug FROM tags', 'slug');

    $semana = Semana::corrente();
    $inicio = Semana::inicio($semana['ano'], $semana['semana']);
    $hoje   = Semana::agora();

    /**
     * Data do dia N da semana corrente (1 = segunda), nunca no futuro.
     */
    $dia = static function (int $n) use ($inicio, $hoje): string {
        $data = $inicio->modify('+' . ($n - 1) . ' days');

        return ($data > $hoje ? $hoje : $data)->format('Y-m-d');
    };

    $tarefas = [
        [
            'titulo'     => 'Migrar partilhas do departamento financeiro',
            'descricao'  => 'Copiar as partilhas do servidor antigo, validar permissões NTFS e testar o acesso com os utilizadores.',
            'coluna'     => 'Em Curso',
            'projeto'    => 'Migração do servidor de ficheiros',
            'responsavel' => 'rui.marques@ti.local',
            'prioridade' => 'alta',
            'tags'       => ['infraestrutura', 'it'],
            'estimativa' => 480,
            'logs'       => [[1, 180], [2, 240], [3, 120]],
            'dificuldades' => 'Permissões herdadas do servidor antigo obrigaram a rever a árvore de pastas manualmente.',
        ],
        [
            'titulo'     => 'Configurar cópias de segurança do novo servidor',
            'descricao'  => 'Definir a política de retenção e agendar as tarefas de cópia noturna.',
            'coluna'     => 'A Fazer',
            'projeto'    => 'Migração do servidor de ficheiros',
            'responsavel' => 'rui.marques@ti.local',
            'prioridade' => 'alta',
            'tags'       => ['infraestrutura', 'seguranca'],
            'estimativa' => 240,
            'prazo_proxima' => 7,
            'logs'       => [],
        ],
        [
            'titulo'     => 'Substituir pontos de acesso do piso 2',
            'descricao'  => 'Instalar os novos equipamentos e migrar a configuração dos SSID.',
            'coluna'     => 'Em Curso',
            'projeto'    => 'Renovação da rede sem fios',
            'responsavel' => 'nuno.correia@ti.local',
            'prioridade' => 'media',
            'tags'       => ['redes', 'infraestrutura'],
            'estimativa' => 360,
            'logs'       => [[2, 210], [4, 150]],
        ],
        [
            'titulo'     => 'Segmentar rede de visitantes em VLAN dedicada',
            'descricao'  => 'Criar a VLAN, aplicar as regras de firewall e testar o isolamento.',
            'coluna'     => 'A Fazer',
            'projeto'    => 'Renovação da rede sem fios',
            'responsavel' => 'nuno.correia@ti.local',
            'prioridade' => 'alta',
            'tags'       => ['redes', 'seguranca'],
            'estimativa' => 300,
            'prazo_proxima' => 5,
            'logs'       => [],
        ],
        [
            'titulo'     => 'Rever política de palavras-passe do domínio',
            'descricao'  => 'Aumentar o comprimento mínimo e ativar o bloqueio após tentativas falhadas.',
            'coluna'     => 'Em Curso',
            'projeto'    => 'Plano de segurança e cópias de segurança',
            'responsavel' => 'sofia.almeida@ti.local',
            'prioridade' => 'critica',
            'tags'       => ['seguranca', 'it'],
            'estimativa' => 180,
            'logs'       => [[1, 90], [3, 120]],
        ],
        [
            'titulo'     => 'Testar restauro das cópias de segurança',
            'descricao'  => 'Restaurar um conjunto de ficheiros para ambiente isolado e medir o tempo de recuperação.',
            'coluna'     => 'Backlog',
            'projeto'    => 'Plano de segurança e cópias de segurança',
            'responsavel' => 'sofia.almeida@ti.local',
            'prioridade' => 'alta',
            'tags'       => ['seguranca', 'manutencao'],
            'estimativa' => 240,
            'prazo_proxima' => 10,
            'logs'       => [],
        ],
        [
            'titulo'     => 'Impressora do 3.º piso sem ligação à rede',
            'descricao'  => 'Utilizador reportou impossibilidade de imprimir desde segunda-feira de manhã.',
            'coluna'     => 'Concluído',
            'projeto'    => null,
            'responsavel' => 'sofia.almeida@ti.local',
            'prioridade' => 'media',
            'tags'       => ['suporte', 'incidente'],
            'estimativa' => 60,
            'logs'       => [[1, 45]],
            'concluida'  => 1,
            'resolucao'  => 'Endereço IP em conflito. Reservado no servidor DHCP e reposto o serviço.',
        ],
        [
            'titulo'     => 'Correio eletrónico a rejeitar anexos grandes',
            'descricao'  => 'Vários utilizadores reportaram rejeição de anexos acima de 10 MB.',
            'coluna'     => 'Concluído',
            'projeto'    => null,
            'responsavel' => 'sofia.almeida@ti.local',
            'prioridade' => 'alta',
            'tags'       => ['suporte', 'incidente'],
            'estimativa' => 90,
            'logs'       => [[2, 75]],
            'concluida'  => 2,
            'resolucao'  => 'Limite do servidor de correio aumentado para 25 MB e comunicado à equipa.',
        ],
        [
            'titulo'     => 'Portátil de sala de reuniões não arranca',
            'descricao'  => 'Equipamento fica no ecrã do fabricante e não avança.',
            'coluna'     => 'Bloqueado',
            'projeto'    => null,
            'responsavel' => 'rui.marques@ti.local',
            'prioridade' => 'media',
            'tags'       => ['suporte', 'incidente', 'manutencao'],
            'estimativa' => 120,
            'logs'       => [[3, 60]],
            'dificuldades' => 'À espera de disco de substituição; o fornecedor só entrega na próxima semana.',
        ],
        [
            'titulo'     => 'Pedido de acesso à pasta de Recursos Humanos',
            'descricao'  => 'Nova colaboradora precisa de acesso de leitura à pasta partilhada.',
            'coluna'     => 'Concluído',
            'projeto'    => null,
            'responsavel' => 'nuno.correia@ti.local',
            'prioridade' => 'baixa',
            'tags'       => ['suporte'],
            'estimativa' => 30,
            'logs'       => [[2, 20]],
            'concluida'  => 2,
            'resolucao'  => 'Acesso concedido através do grupo do Active Directory, com validação da chefia.',
        ],
        [
            'titulo'     => 'Atualizar firmware dos comutadores de rede',
            'descricao'  => 'Aplicar a versão recomendada pelo fabricante durante a janela de manutenção.',
            'coluna'     => 'A Fazer',
            'projeto'    => 'Renovação da rede sem fios',
            'responsavel' => 'nuno.correia@ti.local',
            'prioridade' => 'media',
            'tags'       => ['redes', 'manutencao'],
            'estimativa' => 180,
            'prazo_proxima' => 6,
            'logs'       => [],
        ],
        [
            'titulo'     => 'Inventariar equipamento em fim de vida',
            'descricao'  => 'Levantamento dos postos com mais de cinco anos para plano de substituição.',
            'coluna'     => 'Backlog',
            'projeto'    => null,
            'responsavel' => 'rui.marques@ti.local',
            'prioridade' => 'baixa',
            'tags'       => ['it', 'manutencao'],
            'estimativa' => 300,
            'logs'       => [],
        ],
        [
            'titulo'     => 'Formação interna sobre phishing',
            'descricao'  => 'Preparar sessão de sensibilização e material de apoio para toda a organização.',
            'coluna'     => 'A Fazer',
            'projeto'    => 'Plano de segurança e cópias de segurança',
            'responsavel' => 'sofia.almeida@ti.local',
            'prioridade' => 'media',
            'tags'       => ['seguranca'],
            'estimativa' => 240,
            'prazo_proxima' => 12,
            'logs'       => [[4, 60]],
        ],
        [
            'titulo'     => 'Monitorização de espaço em disco dos servidores',
            'descricao'  => 'Configurar alertas de ocupação acima de 85 % em todos os volumes.',
            'coluna'     => 'Em Curso',
            'projeto'    => 'Migração do servidor de ficheiros',
            'responsavel' => 'admin@ti.local',
            'prioridade' => 'media',
            'tags'       => ['infraestrutura', 'manutencao'],
            'estimativa' => 150,
            'logs'       => [[3, 90], [4, 45]],
        ],
        [
            'titulo'     => 'Rever contratos de assistência técnica',
            'descricao'  => 'Confirmar prazos de renovação e níveis de serviço com os fornecedores.',
            'coluna'     => 'Backlog',
            'projeto'    => null,
            'responsavel' => 'admin@ti.local',
            'prioridade' => 'baixa',
            'tags'       => ['it'],
            'estimativa' => 120,
            'logs'       => [],
        ],
        [
            'titulo'     => 'Documentar procedimento de reposição de palavra-passe',
            'descricao'  => 'Escrever o guia passo a passo para a equipa de apoio ao utilizador.',
            'coluna'     => 'Concluído',
            'projeto'    => 'Plano de segurança e cópias de segurança',
            'responsavel' => 'admin@ti.local',
            'prioridade' => 'media',
            'tags'       => ['seguranca', 'it'],
            'estimativa' => 120,
            'logs'       => [[1, 60], [2, 60]],
            'concluida'  => 2,
        ],
    ];

    $posicoes = [];
    $criadas  = 0;
    $titulos  = array_map(static fn (array $t): string => $t['titulo'], $tarefas);

    foreach ($tarefas as $tarefa) {
        // O seed é idempotente: uma tarefa com o mesmo título não é recriada.
        $existe = Database::valor(
            'SELECT id FROM tasks WHERE titulo = :titulo LIMIT 1',
            [':titulo' => $tarefa['titulo']]
        );

        if ($existe !== null) {
            continue;
        }

        $colunaId = $colunas[$tarefa['coluna']] ?? null;

        if ($colunaId === null) {
            continue;
        }

        $posicoes[$colunaId] = ($posicoes[$colunaId] ?? 0) + 1;
        $responsavelId       = $utilizadores[$tarefa['responsavel']] ?? null;

        $dataConclusao = isset($tarefa['concluida']) ? $dia((int) $tarefa['concluida']) : null;
        $dataInicio    = $tarefa['logs'] !== [] ? $dia((int) $tarefa['logs'][0][0]) : null;

        $tarefaId = Database::inserir('tasks', [
            'titulo'         => $tarefa['titulo'],
            'descricao'      => $tarefa['descricao'],
            'column_id'      => $colunaId,
            'project_id'     => $tarefa['projeto'] === null ? null : ($projetos[$tarefa['projeto']] ?? null),
            'assignee_id'    => $responsavelId,
            'criado_por'     => $utilizadores['admin@ti.local'] ?? null,
            'prioridade'     => $tarefa['prioridade'],
            'posicao'        => $posicoes[$colunaId],
            'data_inicio'    => $dataInicio,
            'data_conclusao' => $dataConclusao,
            'estimativa_min' => $tarefa['estimativa'],
            'dificuldades'   => $tarefa['dificuldades'] ?? null,
        ]);

        // Etiquetas
        foreach ($tarefa['tags'] as $slug) {
            if (!isset($etiquetas[$slug])) {
                continue;
            }

            Database::executar(
                'INSERT IGNORE INTO task_tag (task_id, tag_id) VALUES (:task_id, :tag_id)',
                [':task_id' => $tarefaId, ':tag_id' => $etiquetas[$slug]]
            );
        }

        // Registos de tempo da semana corrente
        foreach ($tarefa['logs'] as [$diaSemana, $minutos]) {
            Database::inserir('task_time_logs', [
                'task_id' => $tarefaId,
                'user_id' => $responsavelId,
                'data'    => $dia((int) $diaSemana),
                'minutos' => (int) $minutos,
                'nota'    => null,
            ]);
        }

        // Histórico: criação, movimento para a coluna atual e, se for o caso, conclusão.
        Database::inserir('task_activity', [
            'task_id'        => $tarefaId,
            'user_id'        => $utilizadores['admin@ti.local'] ?? null,
            'tipo'           => 'criacao',
            'de_column_id'   => null,
            'para_column_id' => $colunas['Backlog'] ?? null,
            'descricao'      => 'Tarefa criada.',
        ]);

        if ($tarefa['coluna'] !== 'Backlog') {
            Database::executar(
                'INSERT INTO task_activity (task_id, user_id, tipo, de_column_id, para_column_id, descricao, created_at)
                 VALUES (:task_id, :user_id, :tipo, :de, :para, :descricao, :quando)',
                [
                    ':task_id'   => $tarefaId,
                    ':user_id'   => $responsavelId,
                    ':tipo'      => 'movimento',
                    ':de'        => $colunas['Backlog'] ?? null,
                    ':para'      => $colunaId,
                    ':descricao' => 'Movida para ' . $tarefa['coluna'] . '.',
                    ':quando'    => ($dataInicio ?? $dia(1)) . ' 09:30:00',
                ]
            );
        }

        if ($dataConclusao !== null) {
            Database::executar(
                'INSERT INTO task_activity (task_id, user_id, tipo, de_column_id, para_column_id, descricao, created_at)
                 VALUES (:task_id, :user_id, :tipo, NULL, :para, :descricao, :quando)',
                [
                    ':task_id'   => $tarefaId,
                    ':user_id'   => $responsavelId,
                    ':tipo'      => 'conclusao',
                    ':para'      => $colunaId,
                    ':descricao' => $tarefa['resolucao'] ?? 'Tarefa concluída.',
                    ':quando'    => $dataConclusao . ' 17:00:00',
                ]
            );
        }

        $criadas++;
    }

    echo '    · ' . $criadas . ' tarefas criadas na semana ' . Semana::rotuloCurto($semana['ano'], $semana['semana']) . PHP_EOL;

    // Numa reexecução, as tarefas já existem e os registos de tempo ficaram na
    // semana em que o seed correu pela primeira vez. Como o objetivo destes
    // dados é permitir testar o gerador de relatórios de imediato, as datas são
    // deslocadas para a semana corrente. Só as tarefas deste seed são tocadas:
    // o que a equipa tiver criado entretanto fica intacto.
    if ($criadas === 0) {
        $deslocamento = deslocarParaSemanaCorrente($titulos, $inicio);

        if ($deslocamento !== 0) {
            echo '    · datas deslocadas ' . $deslocamento . ' dias para a semana '
                . Semana::rotuloCurto($semana['ano'], $semana['semana']) . PHP_EOL;
        }
    }
};

/**
 * Desloca as datas dos dados de exemplo para a semana corrente.
 *
 * Devolve o número de dias aplicado (0 se já estavam na semana certa).
 *
 * @param list<string> $titulos Títulos das tarefas do seed
 */
function deslocarParaSemanaCorrente(array $titulos, DateTimeImmutable $inicioSemana): int
{
    if ($titulos === []) {
        return 0;
    }

    // Um marcador por título: a lista é fixa, mas os valores vão ligados.
    $marcadores = [];
    $parametros = [];

    foreach (array_values($titulos) as $indice => $titulo) {
        $marcador              = ':t' . $indice;
        $marcadores[]          = $marcador;
        $parametros[$marcador] = $titulo;
    }

    $lista = implode(', ', $marcadores);

    // A segunda-feira da semana onde os dados estão neste momento.
    $primeira = Database::valor(
        'SELECT MIN(l.data) FROM task_time_logs l
         INNER JOIN tasks t ON t.id = l.task_id
         WHERE t.titulo IN (' . $lista . ')',
        $parametros
    );

    if ($primeira === null) {
        return 0;
    }

    $origem = (new DateTimeImmutable((string) $primeira))->setTime(0, 0);
    $origem = $origem->modify('monday this week');
    $dias   = (int) $origem->diff($inicioSemana->setTime(0, 0))->format('%r%a');

    if ($dias === 0) {
        return 0;
    }

    $parametros[':dias'] = $dias;

    Database::executar(
        'UPDATE task_time_logs l
         INNER JOIN tasks t ON t.id = l.task_id
         SET l.data = DATE_ADD(l.data, INTERVAL :dias DAY)
         WHERE t.titulo IN (' . $lista . ')',
        $parametros
    );

    Database::executar(
        'UPDATE task_activity a
         INNER JOIN tasks t ON t.id = a.task_id
         SET a.created_at = DATE_ADD(a.created_at, INTERVAL :dias DAY)
         WHERE t.titulo IN (' . $lista . ')',
        $parametros
    );

    Database::executar(
        'UPDATE tasks
         SET data_inicio = DATE_ADD(data_inicio, INTERVAL :dias DAY)
         WHERE titulo IN (' . $lista . ') AND data_inicio IS NOT NULL',
        $parametros
    );

    Database::executar(
        'UPDATE tasks
         SET data_conclusao = DATE_ADD(data_conclusao, INTERVAL :dias DAY)
         WHERE titulo IN (' . $lista . ') AND data_conclusao IS NOT NULL',
        $parametros
    );

    // O deslocamento pode empurrar registos para depois de hoje, o que não faz
    // sentido: ninguém regista tempo em dias que ainda não aconteceram.
    // Esses ficam no dia de hoje.
    unset($parametros[':dias']);

    Database::executar(
        'UPDATE task_time_logs l
         INNER JOIN tasks t ON t.id = l.task_id
         SET l.data = CURDATE()
         WHERE t.titulo IN (' . $lista . ') AND l.data > CURDATE()',
        $parametros
    );

    Database::executar(
        'UPDATE tasks
         SET data_conclusao = CURDATE()
         WHERE titulo IN (' . $lista . ') AND data_conclusao > CURDATE()',
        $parametros
    );

    Database::executar(
        'UPDATE task_activity a
         INNER JOIN tasks t ON t.id = a.task_id
         SET a.created_at = NOW()
         WHERE t.titulo IN (' . $lista . ') AND a.created_at > NOW()',
        $parametros
    );

    return $dias;
}
