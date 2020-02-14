-- Siehe Werte in db-create.php
CREATE DATABASE IF NOT EXISTS `@@DBNAME@@`;
CREATE USER '@@DBUSER@@'@'localhost' IDENTIFIED BY '@@DBPASS@@';
GRANT ALL PRIVILEGES ON `@@DBNAME@@`.* TO '@@DBUSER@@'@'localhost';