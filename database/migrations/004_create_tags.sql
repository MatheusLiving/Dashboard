-- Etiquetas aplicáveis às tarefas.
-- Uma etiqueta desativada deixa de aparecer nos seletores, mas continua
-- visível nas tarefas antigas que já a usavam.
CREATE TABLE IF NOT EXISTS `tags` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`       VARCHAR(60) NOT NULL,
    `slug`       VARCHAR(80) NOT NULL,
    `cor_hex`    VARCHAR(7) NOT NULL DEFAULT '#1F3864',
    `ativa`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_slug` (`slug`),
    KEY `idx_tags_ativa` (`ativa`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
