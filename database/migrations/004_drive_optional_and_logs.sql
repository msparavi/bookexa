-- Bookexa Migration 004: Make items.drive_link optional for direct local page-preparation workflow
ALTER TABLE `items`
    MODIFY COLUMN `drive_link` TEXT DEFAULT NULL;
