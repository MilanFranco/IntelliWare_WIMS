-- Add audit trail columns to transactions for inventory movement history.
ALTER TABLE `transactions`
    ADD COLUMN IF NOT EXISTS `previous_stock` INT(11) DEFAULT NULL AFTER `quantity`,
    ADD COLUMN IF NOT EXISTS `new_stock` INT(11) DEFAULT NULL AFTER `previous_stock`,
    ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(50) DEFAULT NULL AFTER `new_stock`;

-- Index for date filtering (ignore error if index already exists on re-run).
CREATE INDEX IF NOT EXISTS `idx_transactions_date` ON `transactions` (`transaction_date`);
