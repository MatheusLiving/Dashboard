-- Utilizadores da aplicação.
-- Não há remoção definitiva: a desativação faz-se com `ativo = 0`, para que
-- as tarefas e relatórios antigos continuem a ter um autor identificável.
CREATE TABLE IF NOT EXISTS `users` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`         VARCHAR(150) NOT NULL,
    `email`        VARCHAR(190) NOT NULL,
    `senha_hash`   VARCHAR(255) NOT NULL,
    `funcao_cargo` VARCHAR(150) DEFAULT NULL,
    `papel`        ENUM('admin', 'membro') NOT NULL DEFAULT 'membro',
    `ativo`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_ativo_nome` (`ativo`, `nome`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
