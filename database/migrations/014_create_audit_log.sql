-- Registo de auditoria: quem alterou o quê e quando.
-- `dados_json` guarda o estado anterior e o posterior, para que seja possível
-- reconstituir qualquer alteração.
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `entidade`    VARCHAR(60) NOT NULL,
    `entidade_id` INT UNSIGNED DEFAULT NULL,
    `acao`        VARCHAR(40) NOT NULL,
    `dados_json`  JSON DEFAULT NULL,
    `ip`          VARCHAR(45) DEFAULT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_entidade` (`entidade`, `entidade_id`),
    KEY `idx_audit_user_data` (`user_id`, `created_at`),
    KEY `idx_audit_data` (`created_at`),
    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
