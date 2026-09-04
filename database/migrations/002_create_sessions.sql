-- Sessões guardadas em base de dados, geridas por App\Core\SessionHandler.
-- Manter as sessões aqui permite invalidá-las à distância e saber de que IP
-- e agente foi iniciada cada uma.
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(128) NOT NULL,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `payload`       TEXT NOT NULL,
    `last_activity` INT UNSIGNED NOT NULL,
    `ip`            VARCHAR(45) DEFAULT NULL,
    `user_agent`    VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user` (`user_id`),
    KEY `idx_sessions_atividade` (`last_activity`),
    CONSTRAINT `fk_sessions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
