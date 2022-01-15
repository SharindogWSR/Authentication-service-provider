CREATE TABLE `authorization`(
    `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `google_ldap_email` VARCHAR(255) NULL,
    `system_role` INT NOT NULL,
    `password_hash` TEXT NOT NULL,
    `id_data` INT NOT NULL
);
ALTER TABLE
    `authorization` ADD UNIQUE `authorization_uuid_unique`(`uuid`);
ALTER TABLE
    `authorization` ADD UNIQUE `authorization_email_unique`(`email`);
ALTER TABLE
    `authorization` ADD UNIQUE `authorization_google_ldap_email_unique`(`google_ldap_email`);
CREATE TABLE `users_data`(
    `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `lastname` TEXT NOT NULL,
    `firstname` TEXT NOT NULL,
    `patronymic` TEXT NULL,
    `group` TEXT NOT NULL,
    `payload` JSON NULL
);
CREATE TABLE `refresh_tokens`(
    `id_user` INT NOT NULL,
    `tokens_hash` VARCHAR(255) NOT NULL,
    `timestamp` TIMESTAMP NOT NULL,
    `user_agent` TEXT NOT NULL
);
ALTER TABLE
    `refresh_tokens` ADD UNIQUE `refresh_tokens_tokens_hash_unique`(`tokens_hash`);
CREATE TABLE `log_of_authorization`(
    `id_user` INT NOT NULL,
    `user_agent` TEXT NOT NULL,
    `ip_address` INT NOT NULL,
    `timestamp` TIMESTAMP NOT NULL
);
ALTER TABLE
    `refresh_tokens` ADD CONSTRAINT `refresh_tokens_id_user_foreign` FOREIGN KEY(`id_user`) REFERENCES `authorization`(`id`);
ALTER TABLE
    `log_of_authorization` ADD CONSTRAINT `log_of_authorization_id_user_foreign` FOREIGN KEY(`id_user`) REFERENCES `authorization`(`id`);
ALTER TABLE
    `authorization` ADD CONSTRAINT `authorization_id_data_foreign` FOREIGN KEY(`id_data`) REFERENCES `users_data`(`id`);