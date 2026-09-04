-- Linhas das tabelas do relatório semanal (secções 2, 3, 4 e 7).
--
-- Nota importante sobre integridade: as descrições aqui guardadas são cópias,
-- não referências. A ligação a `tasks` e `projects` existe apenas para
-- rastreabilidade e é apagada com ON DELETE SET NULL. Um relatório entregue
-- nunca muda de conteúdo porque um projeto foi renomeado ou uma tarefa
-- apagada depois — o texto fica congelado no momento da entrega.

-- Secção 2 — Atividades Realizadas
CREATE TABLE IF NOT EXISTS `report_activities` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`          INT UNSIGNED NOT NULL,
    `task_id`            INT UNSIGNED DEFAULT NULL,
    `data`               DATE DEFAULT NULL,
    `descricao`          VARCHAR(500) NOT NULL,
    `projeto_area`       VARCHAR(200) DEFAULT NULL,
    `estado`             VARCHAR(80) DEFAULT NULL,
    `tempo_dedicado_min` INT UNSIGNED DEFAULT NULL,
    `ordem`              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_report_activities_report` (`report_id`, `ordem`),
    CONSTRAINT `fk_report_activities_report`
        FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_report_activities_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Secção 3 — Incidentes e Pedidos de Suporte
CREATE TABLE IF NOT EXISTS `report_incidents` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`       INT UNSIGNED NOT NULL,
    `task_id`         INT UNSIGNED DEFAULT NULL,
    `descricao`       VARCHAR(500) NOT NULL,
    `prioridade`      VARCHAR(40) DEFAULT NULL,
    `estado`          VARCHAR(80) DEFAULT NULL,
    `resolucao_notas` TEXT DEFAULT NULL,
    `ordem`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_report_incidents_report` (`report_id`, `ordem`),
    CONSTRAINT `fk_report_incidents_report`
        FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_report_incidents_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Secção 4 — Projetos em Curso
-- `nome_snapshot` guarda o nome do projeto tal como estava na data da entrega.
CREATE TABLE IF NOT EXISTS `report_projects` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`       INT UNSIGNED NOT NULL,
    `project_id`      INT UNSIGNED DEFAULT NULL,
    `nome_snapshot`   VARCHAR(200) NOT NULL,
    `progresso`       VARCHAR(40) DEFAULT NULL,
    `proximos_passos` TEXT DEFAULT NULL,
    `observacoes`     TEXT DEFAULT NULL,
    `ordem`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_report_projects_report` (`report_id`, `ordem`),
    CONSTRAINT `fk_report_projects_report`
        FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_report_projects_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Secção 7 — Planeamento para a Próxima Semana
CREATE TABLE IF NOT EXISTS `report_next_week` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_id`  INT UNSIGNED NOT NULL,
    `task_id`    INT UNSIGNED DEFAULT NULL,
    `tarefa`     VARCHAR(500) NOT NULL,
    `prioridade` VARCHAR(40) DEFAULT NULL,
    `prazo`      DATE DEFAULT NULL,
    `ordem`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_report_next_week_report` (`report_id`, `ordem`),
    CONSTRAINT `fk_report_next_week_report`
        FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_report_next_week_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
