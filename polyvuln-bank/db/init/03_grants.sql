-- Application account: data-only privileges (no FILE, no DDL)
CREATE USER IF NOT EXISTS 'pvb_app'@'%' IDENTIFIED WITH mysql_native_password BY 'Pvb_App_2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON polybank.* TO 'pvb_app'@'%';
FLUSH PRIVILEGES;
