/** Uninstall and purge all data and configurations **/
DROP TABLE IF EXISTS signifyd_connect_case;
DROP TABLE IF EXISTS signifyd_connect_retries;
ALTER TABLE sales_flat_order DROP COLUMN origin_store_code;
DELETE FROM core_resource WHERE code='signifyd_connect_setup';
DELETE FROM core_config_data WHERE path LIKE 'signifyd_connect%';
