-- Cada linha é um pedido de assistência atribuído a um departamento.
--
-- Não é um inquérito de opinião: quem regista o voto está a dizer «este
-- departamento pediu-nos ajuda mais uma vez». Por isso a mesma pessoa vota
-- as vezes que forem precisas, e a data de cada voto é guardada — é ela que
-- permite ver a contagem por semana, por mês ou desde sempre.
--
-- O voto sobrevive à conta de quem o registou: o que interessa à contagem é
-- o departamento, não o autor.
CREATE TABLE IF NOT EXISTS `department_votes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id` INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `nota`          VARCHAR(200) DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_votes_departamento` (`department_id`, `created_at`),
    KEY `idx_votes_data` (`created_at`),
    CONSTRAINT `fk_votes_departamento`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_votes_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
