-- Tarefas do quadro Kanban. São a matéria-prima do relatório semanal.
-- `posicao` guarda a ordem do cartão dentro da coluna; `dificuldades` recolhe,
-- ao nível da tarefa, o que depois alimenta a secção de dificuldades do relatório.
CREATE TABLE IF NOT EXISTS `tasks` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo`         VARCHAR(200) NOT NULL,
    `descricao`      TEXT DEFAULT NULL,
    `column_id`      INT UNSIGNED NOT NULL,
    `project_id`     INT UNSIGNED DEFAULT NULL,
    `assignee_id`    INT UNSIGNED DEFAULT NULL,
    `criado_por`     INT UNSIGNED DEFAULT NULL,
    `prioridade`     ENUM('baixa', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
    `posicao`        INT NOT NULL DEFAULT 0,
    `data_inicio`    DATE DEFAULT NULL,
    `data_conclusao` DATE DEFAULT NULL,
    `estimativa_min` INT UNSIGNED DEFAULT NULL,
    `dificuldades`   TEXT DEFAULT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_coluna_posicao` (`column_id`, `posicao`),
    KEY `idx_tasks_assignee` (`assignee_id`),
    KEY `idx_tasks_projeto` (`project_id`),
    KEY `idx_tasks_conclusao` (`data_conclusao`),
    CONSTRAINT `fk_tasks_column`
        FOREIGN KEY (`column_id`) REFERENCES `board_columns` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_assignee`
        FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_criador`
        FOREIGN KEY (`criado_por`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
