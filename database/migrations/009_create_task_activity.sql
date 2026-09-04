-- Histórico de atividade das tarefas: criação, movimentos entre colunas,
-- edições, comentários e conclusão.
-- O ReportBuilder usa esta tabela para descobrir em que tarefas cada
-- colaborador trabalhou durante a semana.
CREATE TABLE IF NOT EXISTS `task_activity` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`         INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED DEFAULT NULL,
    `tipo`            ENUM('criacao', 'movimento', 'comentario', 'edicao', 'conclusao') NOT NULL,
    `de_column_id`    INT UNSIGNED DEFAULT NULL,
    `para_column_id`  INT UNSIGNED DEFAULT NULL,
    `descricao`       VARCHAR(500) DEFAULT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_task` (`task_id`, `created_at`),
    -- Índice desenhado para a consulta do relatório: utilizador + semana.
    KEY `idx_activity_user_data` (`user_id`, `created_at`),
    CONSTRAINT `fk_activity_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_activity_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_activity_de_coluna`
        FOREIGN KEY (`de_column_id`) REFERENCES `board_columns` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_activity_para_coluna`
        FOREIGN KEY (`para_column_id`) REFERENCES `board_columns` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
