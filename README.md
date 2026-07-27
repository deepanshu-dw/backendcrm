# mindful_backend


<--Alter tables-->

ALTER TABLE services ADD COLUMN service_thumbnail VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;

ALTER TABLE `services` ADD `service_description_en` VARCHAR(255) NULL DEFAULT NULL AFTER `service_name_pl`, ADD `service_description_es` VARCHAR(255) NULL DEFAULT NULL AFTER `service_description_en`, ADD `service_description_pl` VARCHAR(255) NULL DEFAULT NULL AFTER `service_description_es`;




Only for description
ALTER TABLE `services` ADD `service_description_en` VARCHAR(255) NULL DEFAULT NULL AFTER `service_name_pl`, ADD `service_description_es` VARCHAR(255) NULL DEFAULT NULL AFTER `service_description_en`, ADD `service_description_pl` VARCHAR(255) NULL DEFAULT NULL AFTER `service_description_es`;