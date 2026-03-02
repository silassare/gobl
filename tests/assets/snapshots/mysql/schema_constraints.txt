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
-- Table structure for table `gObL_authors`
--
DROP TABLE IF EXISTS `gObL_authors`;
CREATE TABLE `gObL_authors` (
`author_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`author_email` varchar(255) NOT NULL,
`author_name` varchar(100) NOT NULL,

--
-- Primary key constraints definition for table `gObL_authors`
--
CONSTRAINT pk_gObL_authors PRIMARY KEY (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Table structure for table `gObL_posts`
--
DROP TABLE IF EXISTS `gObL_posts`;
CREATE TABLE `gObL_posts` (
`post_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`post_author_id` bigint(20) unsigned NOT NULL,
`post_title` varchar(200) NOT NULL,
`post_slug` varchar(200) NOT NULL,
`post_published` tinyint(1) NOT NULL DEFAULT '0',

--
-- Primary key constraints definition for table `gObL_posts`
--
CONSTRAINT pk_gObL_posts PRIMARY KEY (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




--
-- Unique constraints definition for table `gObL_authors`
--
ALTER TABLE `gObL_authors` ADD CONSTRAINT uc_gObL_authors_0c83f57c786a0b4a39efab23731c7ebc UNIQUE (`author_email`);


--
-- Foreign keys constraints definition for table `gObL_posts`
--
ALTER TABLE `gObL_posts` ADD CONSTRAINT fk_posts_authors FOREIGN KEY (`post_author_id`) REFERENCES `gObL_authors` (`author_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;


--
-- Unique constraints definition for table `gObL_posts`
--
ALTER TABLE `gObL_posts` ADD CONSTRAINT uc_gObL_posts_2dbcba41b9ac4c5d22886ba672463cb4 UNIQUE (`post_slug`);

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
