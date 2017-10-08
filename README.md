# Gobl
Gobl allows you to use a Database Abstraction Layer (DBAL) and Object-Relational Mapping (ORM) to query your database in PHP.

## Dependencies
 - [otpl](https://github.com/silassare/otpl/)

## Gobl\DBAL\Types Directives
 - Only basic types defined in SQL should be supported
 - Enum, Set, and customs types of MySQL and Co should not be supported
 - Before adding new types we should be sure that:
   - the type is defined and supported by SQL Server
   - the type is well supported by all/majors RDBMS
   - we could change RDBMS without pains

> Coming Soon: Documentation and test project.
