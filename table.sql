CREATE TABLE `sev_user_oborona92`.`sevas_report` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `chatId` VARCHAR(45) NULL,
  `userName` VARCHAR(250) NULL,
  `telephone` VARCHAR(12) NULL,
  `firstName` VARCHAR(250) NULL,
  `location` VARCHAR(400) NULL,
  `description` LONGTEXT NULL,
  `file` VARCHAR(100) NULL,
  `date` DATETIME NULL,
  PRIMARY KEY (`id`));