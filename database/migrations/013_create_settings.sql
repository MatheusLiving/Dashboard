-- Configurações editáveis pelo administrador na interface.
-- Distinguem-se do .env: aqui ficam as opções de negócio, não as credenciais.
CREATE TABLE IF NOT EXISTS `settings` (
    `chave`     VARCHAR(100) NOT NULL,
    `valor`     TEXT DEFAULT NULL,
    `descricao` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`chave`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
