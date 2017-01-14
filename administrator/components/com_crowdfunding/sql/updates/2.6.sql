ALTER TABLE `#__crowdf_locations` DROP `state_code`;
ALTER TABLE `#__crowdf_locations` CHANGE `name` `name` VARCHAR(191) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `#__crowdf_locations` ADD INDEX `idx_cflocations_name` (`name`);