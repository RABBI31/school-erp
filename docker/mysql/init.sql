-- Runs once when the MySQL volume is first created.
-- school_erp is created by MYSQL_DATABASE; this adds the database the test suite uses.
CREATE DATABASE IF NOT EXISTS school_erp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON school_erp_test.* TO 'school'@'%';
