-- Projetos do departamento (migrações, implementações, manutenções).
-- Alimentam a secção 4 do relatório semanal, "Projetos em Curso".
CREATE TABLE IF NOT EXISTS `projects` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome`           VARCHAR(180) NOT NULL,
    `descricao`      TEXT DEFAULT NULL,
    `responsavel_id` INT UNSIGNED DEFAULT NULL,
    `status`         ENUM('planeado', 'em_curso', 'pausado', 'concluido') NOT NULL DEFAULT 'planeado',
    `progresso_pct`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `prioridade`     ENUM('baixa', 'media', 'alta', 'critica') NOT NULL DEFAULT 'media',
    `data_inicio`    DATE DEFAULT NULL,
    `prazo`          DATE DEFAULT NULL,
    `arquivado`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_projects_responsavel` (`responsavel_id`),
    KEY `idx_projects_status` (`status`, `arquivado`),
    CONSTRAINT `fk_projects_responsavel`
        FOREIGN KEY (`responsavel_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `ck_projects_progresso` CHECK (`progresso_pct` BETWEEN 0 AND 100)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
