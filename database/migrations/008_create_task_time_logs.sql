-- Registo de tempo dedicado a cada tarefa, por dia e por utilizador.
-- É a fonte da coluna "Tempo dedicado" da secção 2 do relatório semanal.
CREATE TABLE IF NOT EXISTS `task_time_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `data`       DATE NOT NULL,
    `minutos`    INT UNSIGNED NOT NULL,
    `nota`       VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_time_logs_task` (`task_id`),
    -- Índice desenhado para a consulta do relatório: utilizador + intervalo de datas.
    KEY `idx_time_logs_user_data` (`user_id`, `data`),
    CONSTRAINT `fk_time_logs_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_time_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
