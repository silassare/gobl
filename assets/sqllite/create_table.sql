--
-- Table structure for table `<%$.table_name%>`
--
DROP TABLE IF EXISTS `<%$.table_name%>`;
CREATE TABLE `<%$.table_name%>` (
<%$.table_body%>
);
<%$.alter_table%>