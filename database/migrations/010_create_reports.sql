-- Relatório semanal: um por colaborador e por semana ISO.
-- A chave única (user_id, ano, numero_semana) garante essa regra ao nível
-- da base de dados, e não apenas na aplicação.
CREATE TABLE IF NOT EXISTS `reports` (
    `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`                INT UNSIGNED NOT NULL,
    `ano`                    SMALLINT UNSIGNED NOT NULL,
    `numero_semana`          TINYINT UNSIGNED NOT NULL,
    `semana_inicio`          DATE NOT NULL,
    `semana_fim`             DATE NOT NULL,
    `data_entrega`           DATE DEFAULT NULL,
    `resumo_executivo`       TEXT DEFAULT NULL,
    `bloqueios_riscos`       TEXT DEFAULT NULL,
    `dificuldades`           TEXT DEFAULT NULL,
    `tem_sugestao`           TINYINT(1) NOT NULL DEFAULT 0,
    `sugestao_texto`         TEXT DEFAULT NULL,
    `observacoes_adicionais` TEXT DEFAULT NULL,
    `status`                 ENUM('rascunho', 'entregue') NOT NULL DEFAULT 'rascunho',
    `created_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reports_user_semana` (`user_id`, `ano`, `numero_semana`),
    KEY `idx_reports_semana` (`ano`, `numero_semana`),
    KEY `idx_reports_status` (`status`),
    CONSTRAINT `fk_reports_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `ck_reports_semana` CHECK (`numero_semana` BETWEEN 1 AND 53)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
