# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Idioma

Toda a interface, mensagens de erro, textos de ajuda **e comentários do código** estão em
**português europeu (pt-PT)**, seguindo a terminologia do template do relatório
("Colaborador", "Função / Cargo", "Semana / Período", "Bloqueios, Riscos e Dependências").
Nomes de classes, métodos, variáveis e colunas da base de dados também são em português
(`Semana::corrente()`, `Database::todos()`, `data_conclusao`). Mantenha essa norma em qualquer
código novo.

O `README.md` documenta o produto em detalhe (funcionalidades, permissões, decisões).
Este ficheiro cobre só o que é preciso para trabalhar no código.

## Comandos

```bash
composer install                          # dependências
php database/migrate.php                  # aplica migrações pendentes (idempotente)
php database/migrate.php --estado         # mostra pendentes, sem alterar nada
php database/migrate.php --forcar         # reexecuta tudo (só em desenvolvimento)
php database/seed.php                     # todos os seeds (idempotentes)
php database/seed.php 03_tags             # apenas um seed
php bin/preparar-template.php             # constrói o template .docx e verifica os marcadores
php bin/preparar-template.php --verificar # só confere os 24 marcadores

php -S 127.0.0.1:8000 -t public public/index.php   # servidor de desenvolvimento
docker compose up -d                                # app :8080, phpMyAdmin :8081, Mailpit :8025
docker compose up -d mysql                          # apenas a base de dados
```

**Não existe suite de testes automatizados nem linter configurado.** A verificação é feita a
correr a aplicação e a exercitar as rotas. Ao verificar por HTTP, **confirme sempre o código de
estado**: um 500 pode passar despercebido se só se contar elementos no HTML devolvido.

Credenciais do seed: `admin@ti.local` / `admin1234` (administrador),
`rui.marques@ti.local` / `membro1234` (membro).

## Arquitetura

PHP 8.2 puro, sem framework. Autoload PSR-4: `App\` → `src/`.

**Front-controller.** Tudo entra por `public/index.php`, que carrega a configuração, envia os
cabeçalhos de segurança, arranca a sessão, partilha dados globais com as vistas e regista as
rotas. `/public` é o único diretório exposto — código, template e relatórios gerados ficam fora
do alcance do servidor web.

**Camadas.**
- `src/Core` — infraestrutura: `Config`, `Database`, `Router`, `Request`, `Response`, `Session`,
  `SessionHandler`, `Auth`, `Csrf`, `Flash`, `Validator`, `View`, `Semana`.
- `src/Models` — uma classe por entidade, com métodos estáticos que devolvem arrays. Não há ORM;
  as classes escrevem SQL e chamam `Database`.
- `src/Controllers` — um por área funcional, todos a estender `Controller` (`ver()`,
  `redirecionar()`, `voltarComErros()`, `json()`).
- `src/Services` — lógica que não é de uma entidade só: `ReportBuilder` (pré-preenchimento),
  `TemplateBuilder` (constrói o template .docx), `DocxGenerator` (gera o relatório),
  `Mailer` (envio por SMTP), `AuditLogger`.
- `views/` — templates PHP simples, renderizados por `View::render($vista, $dados, $layout)`.

**Rotas.** Registadas em `public/index.php` com `$router->get/post($padrao, [Controlador::class,
'metodo'], ['auth'|'admin'])`. Segmentos dinâmicos são `{id}`. A ordem importa: **rotas literais
têm de vir antes das dinâmicas** — `/relatorios/nova` e `/relatorios/download` estão registadas
antes de `/relatorios/{id}`, senão «nova» seria lido como identificador.

**Sessões em base de dados** (tabela `sessions`), via `SessionHandler` que implementa
`SessionHandlerInterface`, `SessionIdInterface` e `SessionUpdateTimestampHandlerInterface`.

## Invariantes e armadilhas

Estas são as coisas que já partiram o projeto uma vez. Valem mais do que a leitura de qualquer
ficheiro isolado.

**Cada marcador nomeado só pode aparecer uma vez por consulta.** `Database` liga com
`ATTR_EMULATE_PREPARES = false`, por isso o MySQL prepara as consultas de verdade. Comparar o
mesmo valor em duas colunas exige dois marcadores distintos — ver o filtro de pesquisa em
`Task::paraQuadro()`. Reutilizar um marcador dá `SQLSTATE[HY093]: Invalid parameter number`.

**Prepared statements em 100% das consultas.** Nenhum valor é interpolado em SQL. Quando um
nome de tabela tem de entrar na consulta (`Report::linhas()`), vem sempre de uma constante
(`Report::TABELAS_LINHAS`), nunca do pedido.

**Ficheiros estáticos com `php -S`.** O servidor embutido do PHP encaminha *tudo* para
`index.php`, incluindo `/assets/js/*.js` e `/assets/css/*.css`, que chegariam ao navegador como
HTML e seriam recusados — **nenhum JavaScript correria**. A guarda no topo de `public/index.php`
(`PHP_SAPI === 'cli-server'`, com contenção por `realpath`) devolve `false` para ficheiros reais
dentro de `/public`. Não a remova. Com Apache é o `.htaccess` que trata disto. Testes só por
`curl` a HTML e JSON não apanham este tipo de falha; use um navegador real quando mexer em JS.

**Congelamento dos relatórios.** As tabelas `report_*` guardam **cópias do texto**
(`nome_snapshot`, descrições copiadas), não referências. Renomear um projeto ou apagar uma
tarefa não pode alterar um relatório já entregue. As ligações a `tasks`/`projects` existem só
para rastreabilidade e são anuladas com `ON DELETE SET NULL`. Nunca substitua estes campos por
`JOIN`s às tabelas de origem.

O congelamento trava efeitos colaterais, não o autor: `ReportController::reabrir()` devolve um
relatório entregue ao estado de rascunho, para correção. Os ficheiros gerados e os registos de
envio **mantêm-se** na reabertura — são a prova do que chegou à chefia, e a nova entrega gera
outra versão ao lado da anterior. Editar, reabrir e eliminar continuam a ser só do autor, mesmo
para o administrador.

**Eliminar um relatório apaga ficheiros do disco.** `report_exports` e `report_emails` saem em
cascata com a linha de `reports`, mas os `.docx` não: `eliminarFicheirosGerados()` trata deles,
com contenção por `realpath()` dentro da pasta de relatórios — um caminho gravado que aponte
para fora é ignorado. A ordem importa: base de dados primeiro, ficheiros depois. Pela ordem
inversa, uma falha na eliminação deixaria registos a apontar para ficheiros já apagados.

**Semanas ISO-8601.** Segunda a domingo; todos os cálculos passam por `App\Core\Semana`
(`DateTimeImmutable::setISODate()`). A coluna `ano` de `reports` guarda o **ano ISO**, que pode
não coincidir com o ano civil na viragem do ano. Um relatório por colaborador e por semana é
garantido pela chave única `(user_id, ano, numero_semana)`, não apenas pela aplicação.

**Título das páginas.** Definir `$titulo` dentro de uma vista não chega ao layout, porque o
layout é renderizado à parte. Use `View::titulo('...')`.

**Codificação.** `Request::normalizar()` converte entrada que não seja UTF-8 válido, aplicada em
`query()`, `post()`, `postArray()` e `todosPost()`. Do lado da linha de comandos: a aplicação
liga sempre em `utf8mb4`, mas o cliente `mysql` não — um `UPDATE` com acentos escrito na consola
sem `--default-character-set=utf8mb4` grava texto em dupla codificação (`Migração` a virar
`MigraÃ§Ã£o`). Prefira fazer a alteração pela aplicação. Deteção:

```sql
SELECT id, nome FROM projects WHERE nome <> CONVERT(CONVERT(nome USING latin1) USING utf8mb4);
```

**Auditoria nunca faz falhar a operação.** Se o registo em `audit_log` falhar, o erro vai para o
log e o trabalho do utilizador segue. O `AuditLogger` guarda só os campos que mudaram e **nunca**
palavras-passe.

**Efeitos externos já concretizados não podem ser reportados como falha.** Em
`ReportController::enviarEmail()`, a mensagem já partiu quando se regista o envio: uma falha na
gravação produz um aviso «Não reenvie», não um erro 500 — senão o utilizador reenviaria e a
chefia receberia tudo em duplicado. Mesma lógica na geração do .docx: se falhar, a entrega
mantém-se.

**Seeds idempotentes com datas móveis.** `06_tasks.php` não recria tarefas já existentes, mas
desloca as datas dos registos de tempo e do histórico para a **semana ISO corrente**, para que os
dados de exemplo continuem a servir para testar o relatório. Só toca nas tarefas do seed.

## Geração do .docx

`Relatorio_Semanal_TI.docx` (raiz) é o original e **nunca é alterado**.
`bin/preparar-template.php` copia-o e injeta 24 marcadores em `word/document.xml` por manipulação
da árvore DOM (`TemplateBuilder`) — `styles.xml`, `theme1.xml`, `settings.xml` e `fontTable.xml`
ficam byte a byte iguais. O resultado é `storage/templates/Relatorio_Semanal_TI_template.docx`.

Pontos que condicionam qualquer alteração:

- **Cada marcador é escrito num único `<w:r>`.** O Word parte texto escrito à mão por vários
  runs, e um `${marcador}` partido passa despercebido ao PhpWord e sai impresso no documento
  entregue. O script termina sempre com verificação e falha se faltar algum dos 24.
- **`TemplateBuilder::aplicarMarcadores()` conta com 9 tabelas** e localiza as secções pela
  posição, não pelo texto (exceção: a §5, que serve de modelo à secção «Dificuldades
  Encontradas», inserida sem número entre a §5 e a §6 para preservar a numeração 1–8). Editar
  textos fixos no original é seguro; acrescentar ou remover uma tabela obriga a rever este método.
- **Tabelas vazias geram sempre uma linha**, preenchida com `—` (`max(1, count())` em
  `DocxGenerator::preencherTabela()`). Deixar a linha-modelo intacta faria sair `${ativ_tarefa}`
  no documento final.
- **O escape é feito no `DocxGenerator`**, não pelo PhpWord, porque as quebras de linha têm de
  sair como `<w:br/>` — que não pode ser escapado.
- **Zebra.** `cloneRow()` copia o sombreado da linha-modelo, deixando tudo branco; o sombreado
  alternado é reposto por pós-processamento (`reporZebra()`), e só nas tabelas cujo cabeçalho tem
  a cor do template — caixas de texto livre e tabela de identificação ficam intactas.

Ficheiros gerados: `storage/reports/{ano}/{semana}/RelatorioSemanal_{ano}-S{semana}_{slug}.docx`,
**nunca substituídos** (`_v2`, `_v3`, …), cada geração com uma linha em `report_exports`. Chegam
ao utilizador só por `/relatorios/download?id=X`, que verifica permissões e confirma por
`realpath()` que o caminho continua dentro da pasta de relatórios.

## Segurança

Ao acrescentar rotas ou formulários, mantenha o que já está em vigor: token CSRF obrigatório em
todos os `POST`/`PUT`/`PATCH`/`DELETE` (campo de formulário ou cabeçalho `X-CSRF-Token`); escape
de toda a saída com `View::e()`; validação no servidor com `App\Core\Validator` (o JavaScript é
só comodidade); `ARGON2ID` com mínimo de 8 caracteres; cabeçalhos de segurança em
`Response::cabecalhosSeguranca()` — se acrescentar um CDN, é aí que a CSP tem de ser atualizada.

Permissões relevantes: administrador vê todos os relatórios, membro vê os seus; **editar e
eliminar um relatório é só do próprio autor, mesmo para o administrador**. Utilizadores nunca são
apagados (`ativo = 0`), e há três salvaguardas contra fechar a administração a si própria em
`UserController`.

## Correio

`Mailer` usa PHPMailer sobre SMTP, com as variáveis `MAIL_*` do `.env`. Sem `MAIL_USERNAME` não
há autenticação e `SMTPAutoTLS` é desligado — é o que o Mailpit local exige. `MAIL_ENABLED=false`
faz o botão desaparecer da interface. Máximo de 10 destinatários por envio; cada envio fica em
`report_emails`. Em desenvolvimento, `docker compose up -d mailpit` apanha tudo em
<http://localhost:8025> e nada sai da máquina.

## Git

Commits em português, no imperativo, a descrever o âmbito. Convenção do histórico:
`Fase N - <âmbito>` para as fases do plano, ou `feat: ...` / `fix: ...` para trabalho avulso.
