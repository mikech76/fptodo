-- --------------------------------------------------------
-- Хост:                         127.0.0.1
-- Версия сервера:               5.5.23 - MySQL Community Server (GPL)
-- Операционная система:         Win32
-- HeidiSQL Версия:              9.4.0.5125
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Дамп структуры базы данных todo
DROP DATABASE IF EXISTS `todo`;
CREATE DATABASE IF NOT EXISTS `todo` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `todo`;

-- Дамп структуры для таблица todo.memcache
DROP TABLE IF EXISTS `memcache`;
CREATE TABLE IF NOT EXISTS `memcache` (
  `idkey` varchar(128) NOT NULL,
  `val` mediumtext,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idkey`),
  KEY `updated_idx` (`updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы todo.memcache: ~0 rows (приблизительно)
/*!40000 ALTER TABLE `memcache` DISABLE KEYS */;
/*!40000 ALTER TABLE `memcache` ENABLE KEYS */;

-- Дамп структуры для таблица todo.share
DROP TABLE IF EXISTS `share`;
CREATE TABLE IF NOT EXISTS `share` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `todolist_id` int(10) unsigned NOT NULL,
  `mode` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-deleted,1-owner,2-edit,3-see',
  `updated` double unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_todolist_idx` (`user_id`,`todolist_id`),
  KEY `share_todolist_fk` (`todolist_id`,`updated`),
  CONSTRAINT `share_todolist_fk` FOREIGN KEY (`todolist_id`) REFERENCES `todolist` (`id`) ON DELETE CASCADE,
  CONSTRAINT `share_user_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы todo.share: ~0 rows (приблизительно)
/*!40000 ALTER TABLE `share` DISABLE KEYS */;
/*!40000 ALTER TABLE `share` ENABLE KEYS */;

-- Дамп структуры для таблица todo.todolist
DROP TABLE IF EXISTS `todolist`;
CREATE TABLE IF NOT EXISTS `todolist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '0',
  `updated` double unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;

-- Дамп данных таблицы todo.todolist: ~0 rows (приблизительно)
/*!40000 ALTER TABLE `todolist` DISABLE KEYS */;
/*!40000 ALTER TABLE `todolist` ENABLE KEYS */;

-- Дамп структуры для таблица todo.todotask
DROP TABLE IF EXISTS `todotask`;
CREATE TABLE IF NOT EXISTS `todotask` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `todolist_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0-deleted,1-open,2-close',
  `updated` double unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `todotask_todolist_fk` (`todolist_id`,`updated`),
  CONSTRAINT `todotask_todolist_fk` FOREIGN KEY (`todolist_id`) REFERENCES `todolist` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Дамп данных таблицы todo.todotask: ~0 rows (приблизительно)
/*!40000 ALTER TABLE `todotask` DISABLE KEYS */;
/*!40000 ALTER TABLE `todotask` ENABLE KEYS */;

-- Дамп структуры для таблица todo.user
DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(32) NOT NULL DEFAULT '0',
  `password` char(32) NOT NULL DEFAULT '0',
  `salt` char(32) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login_idx` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
