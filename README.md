# Relatório Semanal de Atividade — Departamento de TI

Aplicação web interna onde a equipa de TI trabalha num quadro Kanban durante a semana e,
no final, gera o **Relatório Semanal em `.docx`** já preenchido, a partir do template oficial
`Relatorio_Semanal_TI.docx`.

Um relatório por colaborador, por semana ISO.

---

## Estado do desenvolvimento

As seis fases estão concluídas. A aplicação está funcional de ponta a ponta: a equipa trabalha
no quadro durante a semana e, no fim, cada colaborador gera o relatório em `.docx` já
preenchido a partir do que ficou registado.

| Fase | Âmbito | Estado |
|------|--------|--------|
| 1 | Estrutura, Core, migrações, seeds, autenticação | **Concluída** |
| 2 | Quadro Kanban: tarefas, etiquetas, arrastar e largar, registo de tempo | **Concluída** |
| 3 | Projetos e backlog | **Concluída** |
| 4 | Relatório: formulário e pré-preenchimento automático | **Concluída** |
| 5 | Geração do `.docx` a partir do template e descarregamento | **Concluída** |
| 6 | Configurações, auditoria, gestão de utilizadores, acabamentos | **Concluída** |

---

## Requisitos

- **PHP 8.2 ou superior**, com as extensões `pdo_mysql`, `mbstring`, `zip`, `dom`, `fileinfo` e `gd`
- **MySQL 8.0**
- **Composer 2**
- Servidor web com reescrita de URL (Apache com `mod_rewrite`), ou o servidor embutido do PHP em desenvolvimento

Não é usado nenhum framework MVC. O único código de terceiros são as duas dependências do Composer:
`phpoffice/phpword` (geração do `.docx`) e `vlucas/phpdotenv` (leitura do `.env`).

---

## Instalação

### 1. Dependências

```bash
composer install
```

### 2. Configuração

```bash
cp .env.example .env
```

Edite o `.env` e ajuste, no mínimo, as credenciais da base de dados.
As variáveis estão todas documentadas dentro do próprio ficheiro.

### 3. Base de dados

Com o Docker (recomendado, dispensa instalar o MySQL na máquina):

```bash
docker compose up -d mysql
```

Sem Docker, crie manualmente a base de dados e o utilizador indicados no `.env`.
O script de migrações cria a base de dados se o utilizador configurado tiver permissão para tal.

### 4. Migrações e dados iniciais

```bash
php database/migrate.php
php database/seed.php
```

Ambos os scripts podem ser executados as vezes que forem precisas: as migrações já aplicadas
são ignoradas e os seeds não duplicam registos.

### 5. Template do relatório

```bash
php bin/preparar-template.php
```

Constrói os dois templates e confirma que os marcadores ficaram todos colocados:

- `storage/templates/Relatorio_Semanal_TI_template.docx`, a partir do `Relatorio_Semanal_TI.docx`
  da raiz — 24 marcadores;
- `storage/templates/Relatorio_Alteracao_Software_template.docx`, a partir do
  `storage/templates/Relatorio_Alteracao_Software.docx` — 28 marcadores.

Nenhum dos originais é alterado. Sem este passo, a geração do .docx falha com uma mensagem
a lembrá-lo.

### 6. Arrancar a aplicação

Com o servidor embutido do PHP:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Com Docker (Apache + MySQL + phpMyAdmin):

```bash
docker compose up -d
```

| Serviço | Endereço |
|---------|----------|
| Aplicação | <http://localhost:8080> |
| phpMyAdmin | <http://localhost:8081> |

---

## Credenciais de teste

Criadas pelo seed `01_users`. **Altere-as antes de qualquer utilização real.**

| Papel | Endereço | Palavra-passe |
|-------|----------|---------------|
| Administrador | `admin@ti.local` | `admin1234` |
| Membro | `rui.marques@ti.local` | `membro1234` |
| Membro | `sofia.almeida@ti.local` | `membro1234` |
| Membro | `nuno.correia@ti.local` | `membro1234` |

O seed cria ainda 5 colunas de quadro, 7 etiquetas, 3 projetos e 16 tarefas com registos de
tempo dentro da **semana ISO corrente**, para que o gerador de relatórios possa ser testado
sem qualquer preparação adicional.

Numa reexecução, as tarefas já existem e não são recriadas — mas as datas dos registos de
tempo e do histórico são **deslocadas para a semana corrente**, para que os dados de exemplo
continuem a servir para testar o relatório mesmo semanas depois da primeira instalação.
Só as tarefas do seed são tocadas; o que a equipa tiver criado entretanto fica intacto.
Registos que o deslocamento empurraria para depois de hoje ficam no dia de hoje.

---

## Comandos

| Comando | O que faz |
|---------|-----------|
| `php database/migrate.php` | Aplica as migrações pendentes |
| `php database/migrate.php --estado` | Mostra o que está por aplicar, sem alterar nada |
| `php database/migrate.php --forcar` | Reexecuta todas as migrações (apenas em desenvolvimento) |
| `php database/seed.php` | Executa todos os seeds |
| `php database/seed.php 03_tags` | Executa apenas o seed indicado |
| `php database/seed.php 07_departments` | Cria a lista inicial de departamentos |
| `php bin/preparar-template.php` | Constrói os dois templates .docx com os marcadores |
| `php bin/preparar-template.php --verificar` | Confere os marcadores dos templates |
| `php bin/preparar-template.php --semanal` | Só o template do relatório semanal |
| `php bin/preparar-template.php --alteracao` | Só o template do relatório de alteração |
| `composer install` | Instala as dependências |

---

## Estrutura do projeto

```
/config             Carregamento da configuração a partir do .env
/database
  /migrations       Migrações .sql numeradas
  /seeds            Dados iniciais
  migrate.php       Executor das migrações
  seed.php          Executor dos seeds
/docker             Dockerfile do serviço PHP
/public             Único diretório exposto ao navegador
  index.php         Front-controller: todos os pedidos passam por aqui
  .htaccess         Reescrita de URL
  /assets           CSS e JavaScript
/src
  /Core             Config, Database, Router, Auth, Csrf, Session, Validator, View, Semana
  /Controllers      Um controlador por área funcional
  /Models           Uma classe por entidade
  /Services         ReportBuilder, DocxGenerator, AuditLogger (fases 4 a 6)
/storage
  /templates        Documento original e template do relatório de alteração,
                    e template com marcadores do relatório semanal
  /reports          Ficheiros gerados — fora de /public, servidos por script com verificação de permissões
  /logs             Registo de erros
/views              Templates PHP: layouts, parciais e páginas
```

Só `/public` é acessível a partir do navegador. Tudo o resto — código, template, relatórios
gerados — fica fora do alcance direto do servidor web.

---

## Quadro Kanban

O quadro está em `/kanban`. As colunas vêm da tabela `board_columns` e são configuráveis;
a coluna marcada com `is_concluida = 1` é terminal.

**Arrastar e largar.** Os cartões movem-se entre colunas e reordenam-se dentro da coluna
com SortableJS. Ao largar, o navegador envia a sequência completa de identificadores da
coluna de destino; o servidor só aplica essa ordem às tarefas que lá estão de facto, para
que uma sequência forjada não consiga reordenar tarefas de outras colunas.

**Data de conclusão automática.** Ao entrar numa coluna terminal, a tarefa recebe a data de
hoje em `data_conclusao`. Ao sair dela para uma coluna normal, a data é limpa — uma tarefa
reaberta deixa de contar como concluída no relatório.

**Modal da tarefa.** Um clique (ou Enter) num cartão abre a descrição, etiquetas, projeto,
responsável, prioridade, datas, o campo «Dificuldades encontradas», o registo de tempo e o
histórico completo de movimentos.

**Registo de tempo.** O campo de duração aceita as formas usadas na prática: `90`, `1h30`,
`1h 30m`, `2h`, `45m`, `1:30`. Cada utilizador regista apenas o seu próprio tempo — o autor
vem sempre da sessão, nunca do pedido.

**Etiquetas sem sair do modal.** O campo de etiquetas sugere as existentes à medida que se
escreve e oferece **«+ Criar etiqueta «xyz»»** quando não há correspondência. Escrever o nome
de uma etiqueta desativada reativa-a, que é o que o utilizador está a pedir ao escrevê-lo.

**Filtros.** Responsável, etiqueta, projeto, prioridade e pesquisa livre, aplicados no
servidor e refletidos na query string — o estado do quadro é partilhável por URL. Valores
fora das listas conhecidas são simplesmente ignorados.

**Permissões.** Qualquer utilizador autenticado cria, edita e move tarefas: o quadro é da
equipa. Eliminar uma tarefa é reservado ao administrador e a quem a criou; eliminar um
registo de tempo, ao administrador e ao autor do registo. A página de gestão de etiquetas
(`/tags`) é só para administradores, mas a criação rápida a partir do modal está aberta a
todos, porque é aí que as etiquetas nascem no dia a dia.

**Auditoria.** Criações, edições, movimentos e eliminações de tarefas, etiquetas e registos
de tempo ficam em `audit_log` com o estado antes e depois. A auditoria nunca faz falhar a
operação: se o registo falhar, o erro vai para o log e o trabalho do utilizador segue.

---

## Projetos e backlog

### Projetos (`/projetos`)

Cada projeto tem responsável, estado, prioridade, datas e um **progresso declarado** (cursor
de 0 a 100). Ao lado dele é sempre apresentado o **progresso calculado** — tarefas em coluna
terminal a dividir pelo total — e o **desvio** entre os dois, em pontos percentuais. Um desvio
de 20 pontos ou mais fica assinalado a âmbar.

Os dois números respondem a perguntas diferentes e por isso coexistem: o declarado é o
julgamento do responsável, que pode incluir trabalho não refletido em tarefas; o calculado é
o que o quadro consegue provar. Quando divergem muito, é sinal de que uma das duas coisas
precisa de atenção.

A página de detalhe (`/projetos/{id}`) mostra as tarefas associadas agrupadas pela coluna do
quadro, com responsável e tempo dedicado, e permite editar o projeto no mesmo ecrã.

**Arquivar em vez de apagar.** Um projeto arquivado sai da listagem e dos seletores de tarefa,
mas as tarefas que já lhe estavam associadas continuam ligadas — o histórico não se perde e os
relatórios antigos continuam a fazer sentido. Arquivar é reservado ao administrador e ao
responsável do projeto; criar e editar está aberto a toda a equipa.

O prazo anterior à data de início é rejeitado: seria um erro de digitação a passar em silêncio.

### Backlog (`/backlog`)

Reúne as tarefas da primeira coluna do quadro — por convenção, a coluna de backlog —
agrupadas por projeto, com as tarefas sem projeto no fim.

À direita ficam as colunas ativas do quadro como zonas de largada: arrastar uma tarefa para
uma delas grava o movimento pelo mesmo ponto de entrada que o quadro usa, incluindo o
preenchimento automático da data de conclusão se a coluna for terminal. A tarefa desaparece
então do backlog, e um grupo de projeto que fique sem tarefas sai da página.

---

## Assistências por departamento

Em `/departamentos`, um quadro informativo da equipa: quem é que nos pede mais assistência
técnica. **Nada daqui entra no relatório semanal** — é uma leitura interna, para sabermos de
onde vem o trabalho.

### O que é um voto

Cada voto é **um pedido de assistência atribuído a um departamento**, registado por quem o
atendeu. Não é um inquérito de opinião: se o Financeiro ligar três vezes na mesma manhã,
registam-se três. Pode juntar-se uma nota curta («impressora da contabilidade sem rede»), que
serve para reconhecer o registo mais tarde e não entra em conta nenhuma.

Há duas formas de registar, e ambas dão no mesmo:

- o **formulário** no topo, com seletor de departamento e nota;
- o botão **+1** ao lado de cada departamento da classificação, para o caso corrente.

Registar está aberto a toda a equipa, porque é toda a equipa que atende os pedidos. O autor vem
sempre da sessão, nunca do pedido.

### A classificação

A tabela mostra a contagem do período escolhido — **esta semana** (semana ISO), **este mês**,
**este ano** ou **desde sempre** —, a percentagem do total e uma barra proporcional ao primeiro
classificado. É relativa ao primeiro e não ao total de propósito: com oito ou dez departamentos,
barras calculadas sobre o total ficariam todas rasteiras e o quadro deixaria de se ler.

Os departamentos sem votos no período aparecem na mesma, com zero — saber quem não pede ajuda é
tão informativo como saber quem pede.

Ao lado ficam a evolução das últimas 8 semanas, quem registou quantas assistências no período e
os últimos registos, com a data, o autor e a nota.

### Enganos

Qualquer registo pode ser anulado a partir da lista dos últimos registos, por quem o fez ou por
um administrador. Uma contagem só é útil enquanto for verdadeira.

### Gerir a lista

A secção de gestão, no fim da página, é só para administradores: criar departamentos, renomeá-los,
mudar a cor, desativar e eliminar.

**Desativar** tira o departamento do quadro de registo — deixa de aparecer no seletor e de ter
botão +1 — mas os votos que já tinha continuam a contar, e o nome continua a aparecer nos
registos antigos. **Eliminar** só é possível enquanto o departamento não tiver assistências
registadas; a partir daí, o caminho é desativar, para não apagar a contagem.

---

## Relatório semanal

Um relatório por colaborador e por semana ISO — regra garantida pela chave única
`(user_id, ano, numero_semana)` da tabela `reports`, e não apenas pela aplicação.

### Pré-preenchimento

Ao abrir `/relatorios/nova`, o `ReportBuilder` reúne do quadro o que o colaborador teria de
escrever à mão:

| Secção | O que traz |
|---|---|
| §2 Atividades | tarefas com registo de tempo ou movimento na semana, com a data do trabalho, a coluna atual e a soma dos minutos |
| §3 Incidentes | tarefas da semana com as etiquetas configuradas como incidente (`suporte`, `incidente`), com a nota de resolução tirada do histórico de conclusão |
| §4 Projetos | projetos ativos de que é responsável ou onde trabalhou, com o progresso e os próximos passos deduzidos das tarefas por fechar |
| §7 Próxima semana | tarefas suas em colunas ativas, ordenadas por prioridade |
| Dificuldades | o campo «Dificuldades» das tarefas em que trabalhou, reunido num texto |

Tudo o que sai daqui é **proposta**: cada linha é editável, removível, e podem acrescentar-se
linhas manuais sem recarregar a página. O botão «Repor a partir do quadro» reconstrói uma
secção para quem apagou linhas a mais.

Quando uma tarefa não tem projeto, a coluna «Projeto / Área» é preenchida com as etiquetas —
que é o que identifica a área nesse caso.

### Sugestões (§6)

A resposta por omissão é **«Não»**. O campo de texto só aparece ao escolher «Sim», e aí passa a
ser obrigatório. Com «Não», grava-se `tem_sugestao = 0` e `sugestao_texto = NULL`, e o relatório
apresenta «Não há sugestões nesta semana.»

### Rascunho e entrega

**Guardar rascunho** deixa tudo editável e não exige o resumo executivo — um relatório
escreve-se ao longo da semana.

**Entregar** exige resumo executivo com pelo menos 20 caracteres e **congela** o conteúdo:
a partir daí abrir a semana redireciona para a vista em modo de leitura, e nada mais escreve
no relatório.

O congelamento é real, não apenas um estado. As linhas em `report_*` são cópias do texto:
renomear um projeto ou apagar uma tarefa depois da entrega não altera uma vírgula do que lá
está escrito. As ligações a `tasks` e `projects` existem só para rastreabilidade e são
anuladas com `ON DELETE SET NULL`.

### Corrigir depois de entregar

Um relatório entregue não fica intocável — fica protegido de mudanças acidentais. Quem o
escreveu pode **reabri-lo** a partir da sua página: volta ao estado de rascunho, é editado no
formulário normal e entregue de novo.

O que a reabertura **não** desfaz: os ficheiros `.docx` já gerados e o registo dos envios por
email mantêm-se, porque são a prova do que chegou à chefia. A nova entrega gera outra versão
do ficheiro, ao lado da anterior. Se o relatório já tinha sido enviado, a confirmação diz
quantas vezes — quem o recebeu ficou com a versão antiga e pode ser preciso reenviar.

A reabertura fica registada na auditoria, com data e autor.

### Eliminar

Rascunhos e relatórios entregues podem ser eliminados pelo autor, a partir da listagem ou da
página do relatório. Desaparecem o conteúdo, as linhas das secções, os registos de exportação
e de envio (em cascata) **e os ficheiros `.docx` gerados**, apagados do disco.

A confirmação diz o que vai desaparecer — quantos ficheiros, e se houve envios — porque não há
como voltar atrás. Um caminho gravado que aponte para fora da pasta de relatórios é ignorado:
a eliminação nunca toca em nada fora dela.

### Permissões

Ver: o administrador vê todos os relatórios, um membro vê os seus. Editar, reabrir e eliminar:
**apenas o próprio autor**, mesmo para o administrador — um relatório é o testemunho de quem o
escreveu.

---

## Relatório de Alteração de Software

O segundo documento da aplicação, em `/alteracoes`: o **Relatório de Pedido e Acompanhamento de
Alteração de Software**, gerado a partir do template `storage/templates/Relatorio_Alteracao_Software.docx`
com a mesma fidelidade do relatório semanal.

Acompanha uma unidade de trabalho de desenvolvimento do princípio ao fim — do pedido à entrega
ao cliente — e, ao contrário do relatório semanal, **não congela**: segue para aprovação, pode
voltar com alterações pedidas e ser corrigido as vezes que forem precisas.

### Quando é aberto

| Aconteceu | O que a aplicação faz |
|---|---|
| Foi criado um **projeto** | Abre um relatório, com o nome, a descrição, o prazo e a prioridade do projeto |
| Foi criada uma **tarefa** com etiqueta de desenvolvimento ou ajuda técnica | Abre um relatório, com o título, a descrição, o responsável e o tipo deduzido das etiquetas |
| Qualquer outro caso | Nada — mas a página `/alteracoes` tem um botão para abrir um a pedido |

As etiquetas que servem de gatilho são configuráveis (`tags_desenvolvimento`, por omissão
`desenvolvimento, suporte, incidente, melhoria`), e a abertura automática pode ser desligada por
inteiro (`alteracao_abertura_automatica`). Sem isto, um quadro que também serve para reuniões e
tarefas administrativas encheria-se de relatórios vazios.

O que a aplicação preenche é **proposta**: todos os campos continuam editáveis. Cada relatório
recebe uma referência própria — `ALT-2026-0007` — que o identifica no email e no nome do ficheiro.

**A abertura nunca faz falhar o que a originou.** Se correr mal, o projeto ou a tarefa ficam
criados na mesma, com um aviso, e o relatório pode ser aberto a partir da listagem.

### Ciclo de aprovação

```
rascunho ──enviar──▶ em aprovação ──aprovar──▶ aprovado ──reabrir──▶ rascunho
                            │                                            ▲
                            └────── pedir alterações ────────────────────┘
```

- **Enviar para aprovação** exige a secção 1 preenchida (1.1, 1.2 e 1.3): sem o pedido escrito,
  não há nada para aprovar. O `.docx` é gerado nesse momento — quem aprova tem de poder ler
  exatamente a versão que lhe foi enviada.
- **Aprovar** e **pedir alterações** são do administrador. Pedir alterações **exige** dizer o
  quê: devolver um documento sem explicação deixaria o autor a adivinhar.
- **Aprovado não se edita.** Para corrigir, reabre-se — e a reabertura fica no histórico.

### Histórico

Cada versão guarda o relatório **por inteiro**, incluindo as linhas de acompanhamento. Não é uma
lista de diferenças: é o documento como estava naquele momento, legível sozinho em
`/alteracoes/versoes/{id}`.

Ficam versões na abertura, em cada envio para aprovação, em cada decisão, em cada reabertura e
em cada gravação que **mude alguma coisa** — uma gravação que não muda nada não gera versão,
para que o histórico continue a mostrar só o que interessa.

É isto que responde à pergunta que um documento de aprovação tem de saber responder: *o que
estava escrito quando isto foi aprovado?* Corrigir o relatório depois não toca em nenhuma versão
já gravada.

### O documento

O `.docx` sai fiel ao original, incluindo as linhas de caixas de opção: em «Tipo de Alteração» e
«Prioridade» as caixas mantêm-se todas e só muda a que fica assinalada (`☒`), em vez de a linha
ser substituída por um valor solto.

Os ficheiros ficam em `storage/reports/alteracoes/{ano}/`, com o nome
`ALT-2026-0007_v3.docx` — a versão faz parte do nome, para que dois ficheiros do mesmo processo
se distingam à vista. Nunca há substituição, e chegam ao utilizador apenas por
`/alteracoes/download?id=`, com a mesma verificação de caminho do relatório semanal.

### Envio e permissões

O envio por email usa a mesma configuração `MAIL_*` e regista **a versão enviada**, para que mais
tarde se saiba o que é que quem aprovou recebeu.

Ver está aberto a toda a equipa — é trabalho comum. Alterar, enviar para aprovação, reabrir e
eliminar são do autor ou de um administrador. Decidir é só do administrador.

---

## Administração

Três páginas reservadas a administradores, ligadas no fim da barra lateral.

### Utilizadores (`/utilizadores`)

Criar contas, editar nome, endereço, função e papel, e definir uma palavra-passe nova quando
alguém a perde. O nome e a função preenchem os campos «Colaborador» e «Função / Cargo» do
relatório, por isso vale a pena mantê-los certos.

**As contas nunca são eliminadas** — desativam-se, para que o histórico de tarefas e
relatórios continue a ter autor identificável. Uma conta desativada deixa de conseguir iniciar
sessão de imediato, mesmo que tivesse sessão aberta.

Três salvaguardas impedem que a administração se feche a si própria:

- não é possível desativar a própria conta;
- não é possível desativar o último administrador ativo;
- não é possível despromover o último administrador ativo.

A palavra-passe redefinida **nunca entra no registo de auditoria** — fica registado que houve
uma reposição e para quem, nada mais.

### Configurações (`/configuracoes`)

As opções de negócio do departamento, distintas do `.env`, que guarda credenciais e
infraestrutura. Cada uma tem efeito real e verificável:

| Configuração | O que muda |
|---|---|
| Nome do departamento | cabeçalho da aplicação e do relatório (o `.env` é o recurso enquanto não houver configuração) |
| Dia de fecho da semana | dia em que se espera o relatório preparado |
| Prazo de entrega (dias) | data de entrega proposta no formulário, contada a partir do fim da semana |
| Caminho do template | informativo; o caminho usado na geração vem do `.env` |
| Etiquetas de incidente | que tarefas entram na secção 3 do relatório |

Os slugs das etiquetas de incidente são validados contra as etiquetas existentes: um slug
errado deixaria a secção 3 silenciosamente vazia, e é melhor recusar do que produzir relatórios
incompletos sem ninguém dar por isso.

A página mostra ainda o estado do sistema — se o template existe, onde ficam os relatórios
gerados, fuso horário, ambiente e a semana ISO corrente.

### Auditoria (`/auditoria`)

Quem alterou o quê e quando, com o estado anterior e o posterior lado a lado. Filtros por
entidade, ação, utilizador e período, com paginação de 50 registos.

O `AuditLogger` regista criações, alterações, movimentos, eliminações, entregas e gerações de
tarefas, projetos, etiquetas, contas, registos de tempo e relatórios. Guarda apenas os campos
que **realmente mudaram**, e nunca palavras-passe. A auditoria não faz falhar a operação: se o
registo falhar, o erro vai para o log e o trabalho do utilizador segue.

---

## Decisões de arquitetura

**Semanas em ISO-8601.** A semana vai de segunda a domingo e a semana 1 é a que contém a
primeira quinta-feira do ano. Todos os cálculos passam por `App\Core\Semana`, que usa
`DateTimeImmutable::setISODate()`. O ano ISO pode não coincidir com o ano civil no início e
no fim do ano — por isso a coluna `ano` da tabela `reports` guarda o **ano ISO**, e não o civil.

**Congelamento dos relatórios.** As tabelas `report_*` guardam cópias do texto, não referências.
Quando um relatório é entregue, o seu conteúdo deixa de poder mudar por efeito colateral:
renomear um projeto ou apagar uma tarefa não altera relatórios já entregues.

O congelamento trava os efeitos colaterais, não o autor. Corrigir um relatório entregue é
possível, mas obriga a reabri-lo — um passo explícito, com confirmação e registo na auditoria.
A distinção que interessa é essa: o conteúdo nunca muda sozinho.

**Sessões em base de dados.** A tabela `sessions` permite listar e invalidar sessões e saber
de que endereço foi iniciada cada uma — coisas que os ficheiros de sessão do PHP não dão.

**Utilizadores nunca são apagados.** A desativação faz-se com `ativo = 0`, para que o histórico
de tarefas e relatórios continue a ter autor identificável.

**Marcadores nomeados usados uma só vez.** Com `ATTR_EMULATE_PREPARES = false` o MySQL prepara
as consultas de verdade, e cada marcador nomeado só pode aparecer uma vez no SQL. Uma condição
que compare o mesmo valor em duas colunas precisa de dois marcadores distintos — ver o filtro
de pesquisa em `Task::paraQuadro()`.

**Cuidado ao mexer na base de dados pela linha de comandos.** A aplicação liga-se sempre em
`utf8mb4`, mas o cliente `mysql` não o faz por omissão: um `UPDATE` com acentos escrito
diretamente na consola pode gravar texto em dupla codificação (`Migração` a virar
`MigraÃ§Ã£o`). Use `mysql --default-character-set=utf8mb4`, ou faça a alteração pela aplicação.

Para detetar um caso destes:

```sql
SELECT id, nome FROM projects
WHERE nome <> CONVERT(CONVERT(nome USING latin1) USING utf8mb4) COLLATE utf8mb4_unicode_ci;
```

O `COLLATE` no fim não é decoração: sem ele, o MySQL 8 compara `utf8mb4_unicode_ci` com o
`utf8mb4_0900_ai_ci` que o `CONVERT` produz e recusa a consulta com
«Illegal mix of collations».

---

## Segurança

- Palavras-passe com `password_hash()` e **ARGON2ID**, com mínimo de 8 caracteres e reforço
  automático do hash quando os parâmetros do algoritmo mudam
- Identificador de sessão regenerado no início de sessão; o registo é destruído no fim
- **Token CSRF obrigatório** em todos os pedidos `POST`, `PUT`, `PATCH` e `DELETE`, aceite em
  campo de formulário ou no cabeçalho `X-CSRF-Token`
- **Prepared statements em 100% das consultas** — nenhum valor é interpolado dentro de SQL
- Escape de toda a saída HTML com `View::e()`
- Validação no servidor em `App\Core\Validator`; a validação em JavaScript é apenas comodidade
- Cabeçalhos de segurança em todas as respostas: `Content-Security-Policy`,
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`
- Cookie de sessão com `HttpOnly` e `SameSite=Lax`; ative `SESSION_SECURE=true` em produção

---

## Frontend

Em desenvolvimento o **Tailwind CSS** vem do CDN, o que dispensa qualquer passo de compilação.
Para produção, compile as classes efetivamente usadas:

```bash
npx tailwindcss -i public/assets/css/app.css -o public/assets/css/build.css --minify
```

Depois substitua, nos layouts `views/layout/base.php` e `views/layout/publico.php`,
o `<script src="https://cdn.tailwindcss.com">` pela folha de estilo compilada, e aperte a
diretiva `script-src` da política de conteúdos em `App\Core\Response::cabecalhosSeguranca()`.

O JavaScript é vanilla. O arrastar e largar do quadro Kanban usará **SortableJS** via CDN (fase 2).

---

## Geração do `.docx`

### O template

O template vive em `storage/templates/Relatorio_Semanal_TI_template.docx` e é **construído por
script** a partir do `Relatorio_Semanal_TI.docx` que está na raiz do projeto:

```bash
php bin/preparar-template.php             # constrói e verifica
php bin/preparar-template.php --verificar # só confere os marcadores
```

O original nunca é alterado. O script copia-o e mexe apenas em `word/document.xml`, sempre por
manipulação da árvore DOM — `styles.xml`, `theme1.xml`, `settings.xml` e `fontTable.xml` ficam
byte a byte iguais, tal como o cabeçalho e o rodapé «Página X de Y».

Os marcadores são escritos **num único run** cada. Isto não é um pormenor: o Word costuma
partir texto escrito à mão por vários runs, e um marcador partido passa despercebido ao
PhpWord e acaba impresso no documento entregue. Por isso o script termina sempre com uma
verificação — se algum dos 24 marcadores faltar, falha em vez de produzir um template partido.

As tabelas de dados são reduzidas a cabeçalho + uma linha-modelo; as linhas em branco do
original são removidas, porque o número de linhas passa a ser o número de registos.

**A secção «Dificuldades Encontradas»** é inserida logo a seguir à §5, criada por clonagem do
título, do subtítulo e da caixa da §5 — o que garante formatação idêntica sem escrever estilos
à mão. Fica **sem número**, para que a numeração 1–8 do documento original se mantenha.

| Marcadores | Onde |
|---|---|
| `${colaborador}` `${semana_periodo}` `${funcao_cargo}` `${data_entrega}` | cabeçalho |
| `${resumo_executivo}` `${bloqueios}` `${dificuldades}` `${sugestao}` `${observacoes}` | secções de texto livre |
| `${ativ_data}` `${ativ_tarefa}` `${ativ_projeto}` `${ativ_estado}` `${ativ_tempo}` | §2, linha clonada |
| `${inc_descricao}` `${inc_prioridade}` `${inc_estado}` `${inc_resolucao}` | §3, linha clonada |
| `${proj_nome}` `${proj_progresso}` `${proj_passos}` `${proj_obs}` | §4, linha clonada |
| `${prox_tarefa}` `${prox_prazo}` | §7, linha clonada |

### A geração

Entregar um relatório gera o ficheiro automaticamente; o botão **«Gerar nova versão»** na
página do relatório produz outro sempre que for preciso. Se a geração falhar, a entrega
mantém-se — o relatório está guardado e pode ser gerado mais tarde.

Detalhes que valem a pena conhecer:

- **Tabelas vazias.** Uma secção sem registos gera à mesma uma linha, preenchida com `—`.
  Deixar a linha-modelo intacta faria aparecer `${ativ_tarefa}` no documento entregue.
- **§6 sem sugestão.** Imprime «Não há sugestões nesta semana.»
- **Escape e quebras de linha.** Os valores são escapados aqui e não pelo PhpWord, porque as
  quebras de linha têm de sair como `<w:br/>` — que não pode ser escapado. `&` e `<` chegam ao
  Word como texto, não como marcação.
- **Zebra.** O `cloneRow()` copia o sombreado da linha-modelo, pelo que todas as linhas sairiam
  brancas. O sombreado alternado do original é reposto por pós-processamento, e só nas tabelas
  cujo cabeçalho tem a cor do template — as caixas de texto livre e a tabela de identificação
  ficam intactas.

### Os ficheiros

Nome: `RelatorioSemanal_{ano}-S{semana}_{slug-do-nome}.docx`, em
`storage/reports/{ano}/{semana}/`.

**Nunca há substituição.** Uma segunda geração da mesma semana produz `…_v2.docx`, depois
`…_v3.docx`, e assim por diante. Cada uma acrescenta uma linha em `report_exports` com o
caminho e o resumo SHA-256.

Os ficheiros vivem **fora de `/public`** e chegam ao utilizador apenas por
`/relatorios/download?id=X`, que verifica as permissões (o dono ou um administrador) e confirma
que o caminho gravado continua dentro da pasta de relatórios antes de servir seja o que for.

### Ajustar o template

Para mudar o aspeto do relatório, edite o `Relatorio_Semanal_TI.docx` original no Word e volte
a correr `php bin/preparar-template.php`. O script conta com a estrutura de 9 tabelas do
documento original e falha com uma mensagem clara se ela mudar — acrescentar ou remover uma
tabela obriga a rever `TemplateBuilder::aplicarMarcadores()`.

Para mudar apenas textos fixos (títulos, frases de ajuda), basta editar o original: o script
localiza as secções pela posição, não pelo texto, com a única exceção da §5, que serve de
modelo à secção de dificuldades.

---

## Idioma

Toda a interface está em **português europeu**, seguindo a terminologia do template
("Colaborador", "Função / Cargo", "Semana / Período", "Bloqueios, Riscos e Dependências").
Os comentários do código seguem a mesma norma. O fuso horário é `Europe/Lisbon`.

---

## Mapa da aplicação

| Rota | O que faz | Quem acede |
|---|---|---|
| `/login` | Início de sessão | todos |
| `/` | Painel com o resumo da semana | autenticados |
| `/perfil` | Dados próprios e palavra-passe | autenticados |
| `/kanban` | Quadro com arrastar e largar | autenticados |
| `/backlog` | Tarefas por planear, por projeto | autenticados |
| `/projetos`, `/projetos/{id}` | Projetos e detalhe | autenticados |
| `/departamentos` | Quadro de assistências por departamento | autenticados |
| `/relatorios` | Listagem (membro vê os seus) | autenticados |
| `/relatorios/nova` | Formulário pré-preenchido | autenticados |
| `/relatorios/{id}` | Vista do relatório e ficheiros | dono ou administrador |
| `/relatorios/{id}/editar` | Abre o relatório no formulário | autor |
| `/relatorios/download?id=` | Descarregar um `.docx` | dono ou administrador |
| `/alteracoes` | Relatórios de alteração de software | autenticados |
| `/alteracoes/{id}` | Vista, aprovação e histórico | autenticados |
| `/alteracoes/{id}/editar` | Formulário do relatório | autor ou administrador |
| `/alteracoes/versoes/{id}` | Uma versão do histórico | autenticados |
| `/tags` | Gestão de etiquetas | administrador |
| `/utilizadores` | Gestão de contas | administrador |
| `/configuracoes` | Opções do departamento | administrador |
| `/auditoria` | Registo de alterações | administrador |

Pontos de entrada JSON, usados pelo quadro e pelo formulário do relatório:
`/api/tarefas/{id}` (detalhe, criar, atualizar, mover, eliminar, tempo), `/api/tags` (listar e
criar) e `/api/relatorios/pre-preencher`.

---

## Utilização típica de uma semana

1. Durante a semana, a equipa trabalha no **quadro**: cria tarefas, arrasta-as entre colunas,
   marca-as com etiquetas e regista o tempo dedicado no modal de cada uma.
2. O que travou o trabalho vai para o campo **«Dificuldades encontradas»** da tarefa.
3. Na sexta-feira, cada colaborador abre **`/relatorios/nova`**. O formulário chega
   pré-preenchido com as atividades da semana, os incidentes, os projetos e o planeamento.
4. Revê, corrige, acrescenta o que faltar e escreve o resumo executivo.
5. **Entrega.** O conteúdo congela e o `.docx` é gerado de imediato, pronto a descarregar
   ou a enviar por email.
6. Se depois aparecer um erro, **reabre o relatório**, corrige e entrega de novo — a nova versão
   do ficheiro fica ao lado da anterior.
