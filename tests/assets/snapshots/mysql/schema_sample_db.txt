--
-- Auto generated file, please don't edit.
-- With: gobl [GOBL_VERSION]
-- Time: [GENERATED_AT]
--

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `gObL_users`
--
DROP TABLE IF EXISTS `gObL_users`;
CREATE TABLE `gObL_users` (
`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`name` text NOT NULL,
`deleted` tinyint(1) NOT NULL DEFAULT '0',
`deleted_at` bigint(20) NULL DEFAULT NULL,

--
-- Primary key constraints definition for table `gObL_users`
--
CONSTRAINT pk_gObL_users PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Table structure for table `gObL_roles`
--
DROP TABLE IF EXISTS `gObL_roles`;
CREATE TABLE `gObL_roles` (
`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`title` text NOT NULL,
`user_id` bigint(20) unsigned NOT NULL,

--
-- Primary key constraints definition for table `gObL_roles`
--
CONSTRAINT pk_gObL_roles PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Table structure for table `gObL_tags`
--
DROP TABLE IF EXISTS `gObL_tags`;
CREATE TABLE `gObL_tags` (
`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`label` text NOT NULL,

--
-- Primary key constraints definition for table `gObL_tags`
--
CONSTRAINT pk_gObL_tags PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Table structure for table `gObL_taggables`
--
DROP TABLE IF EXISTS `gObL_taggables`;
CREATE TABLE `gObL_taggables` (
`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`tag_id` bigint(20) unsigned NOT NULL,
`created_at` bigint(20) NOT NULL,
`updated_at` bigint(20) NOT NULL,
`taggable_id` bigint(20) unsigned NOT NULL,
`taggable_type` varchar(64) NOT NULL,

--
-- Primary key constraints definition for table `gObL_taggables`
--
CONSTRAINT pk_gObL_taggables PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Table structure for table `gObL_articles`
--
DROP TABLE IF EXISTS `gObL_articles`;
CREATE TABLE `gObL_articles` (
`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`title` text NOT NULL,
`user_id` bigint(20) unsigned NOT NULL,
`deleted` tinyint(1) NOT NULL DEFAULT '0',
`deleted_at` bigint(20) NULL DEFAULT NULL,

--
-- Primary key constraints definition for table `gObL_articles`
--
CONSTRAINT pk_gObL_articles PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Foreign keys constraints definition for table `gObL_roles`
--
ALTER TABLE `gObL_roles` ADD CONSTRAINT fk_roles_users FOREIGN KEY (`user_id`) REFERENCES `gObL_users` (`id`) ON UPDATE NO ACTION ON DELETE NO ACTION;


--
-- Foreign keys constraints definition for table `gObL_taggables`
--
ALTER TABLE `gObL_taggables` ADD CONSTRAINT fk_taggables_tags FOREIGN KEY (`tag_id`) REFERENCES `gObL_tags` (`id`) ON UPDATE NO ACTION ON DELETE NO ACTION;


--
-- Foreign keys constraints definition for table `gObL_articles`
--
ALTER TABLE `gObL_articles` ADD CONSTRAINT fk_articles_users FOREIGN KEY (`user_id`) REFERENCES `gObL_users` (`id`) ON UPDATE NO ACTION ON DELETE NO ACTION;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
