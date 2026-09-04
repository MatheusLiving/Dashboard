# Relatório Semanal de Atividade — Departamento de TI

Aplicação web interna onde a equipa de TI trabalha num quadro Kanban durante a semana e,
no final, gera o **Relatório Semanal em `.docx`** já preenchido, a partir do template oficial
`Relatorio_Semanal_TI.docx`.

Um relatório por colaborador, por semana ISO.

---

## Estado do desenvolvimento

O trabalho está organizado em seis fases. Cada fase é validada antes de se avançar para a seguinte.

| Fase | Âmbito | Estado |
|------|--------|--------|
| 1 | Estrutura, Core, migrações, seeds, autenticação | **Concluída** |
| 2 | Quadro Kanban: tarefas, etiquetas, arrastar e largar, registo de tempo | **Concluída** |
| 3 | Projetos e backlog | Por fazer |
| 4 | Relatório: formulário e pré-preenchimento automático | Por fazer |
| 5 | Geração do `.docx` a partir do template e descarregamento | Por fazer |
| 6 | Configurações, auditoria, gestão de utilizadores, acabamentos | Por fazer |

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

### 5. Arrancar a aplicação

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

---

## Comandos

| Comando | O que faz |
|---------|-----------|
| `php database/migrate.php` | Aplica as migrações pendentes |
| `php database/migrate.php --estado` | Mostra o que está por aplicar, sem alterar nada |
| `php database/migrate.php --forcar` | Reexecuta todas as migrações (apenas em desenvolvimento) |
| `php database/seed.php` | Executa todos os seeds |
| `php database/seed.php 03_tags` | Executa apenas o seed indicado |
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
  /templates        Template .docx com os marcadores de substituição
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

## Decisões de arquitetura

**Semanas em ISO-8601.** A semana vai de segunda a domingo e a semana 1 é a que contém a
primeira quinta-feira do ano. Todos os cálculos passam por `App\Core\Semana`, que usa
`DateTimeImmutable::setISODate()`. O ano ISO pode não coincidir com o ano civil no início e
no fim do ano — por isso a coluna `ano` da tabela `reports` guarda o **ano ISO**, e não o civil.

**Congelamento dos relatórios.** As tabelas `report_*` guardam cópias do texto, não referências.
Quando um relatório é entregue, o seu conteúdo deixa de poder mudar por efeito colateral:
renomear um projeto ou apagar uma tarefa não altera relatórios já entregues.

**Sessões em base de dados.** A tabela `sessions` permite listar e invalidar sessões e saber
de que endereço foi iniciada cada uma — coisas que os ficheiros de sessão do PHP não dão.

**Utilizadores nunca são apagados.** A desativação faz-se com `ativo = 0`, para que o histórico
de tarefas e relatórios continue a ter autor identificável.

**Marcadores nomeados usados uma só vez.** Com `ATTR_EMULATE_PREPARES = false` o MySQL prepara
as consultas de verdade, e cada marcador nomeado só pode aparecer uma vez no SQL. Uma condição
que compare o mesmo valor em duas colunas precisa de dois marcadores distintos — ver o filtro
de pesquisa em `Task::paraQuadro()`.

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

*Documentado em detalhe na fase 5.* Resumo do que está previsto:

O template `storage/templates/Relatorio_Semanal_TI_template.docx` é uma cópia fiel do
`Relatorio_Semanal_TI.docx` original — mesma formatação, mesmo cabeçalho, mesmo rodapé
"Página X de Y" e as mesmas 8 secções — com marcadores de substituição inseridos nos
sítios certos.

Campos simples: `${colaborador}`, `${semana_periodo}`, `${funcao_cargo}`, `${data_entrega}`,
`${resumo_executivo}`, `${bloqueios}`, `${dificuldades}`, `${sugestao}`, `${observacoes}`.

Linhas de tabela, clonadas com `cloneRow()` do PhpWord conforme o número de registos:
`${ativ_*}` (secção 2), `${inc_*}` (secção 3), `${proj_*}` (secção 4) e `${prox_*}` (secção 7).

Cada geração produz um ficheiro novo em `storage/reports/{ano}/{semana}/`, registado em
`report_exports` com o respetivo resumo SHA-256. Nunca há substituição de ficheiros: o
histórico de versões geradas mantém-se intacto.

---

## Idioma

Toda a interface está em **português europeu**, seguindo a terminologia do template
("Colaborador", "Função / Cargo", "Semana / Período", "Bloqueios, Riscos e Dependências").
Os comentários do código seguem a mesma norma. O fuso horário é `Europe/Lisbon`.
