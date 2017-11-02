# Gobl
Gobl allows you to use a Database Abstraction Layer (DBAL) and Object-Relational Mapping (ORM) to query your database in PHP.

## Dependencies
 - [otpl](https://github.com/silassare/otpl/)

## Directives
[`Types`](./src/DBAL/Types)
 - Only basic types defined in SQL should be supported
 - Enum, Set, and customs types of MySQL and Co should not be supported
 - Before adding a column type we should be sure that:
   - The column type is defined and supported by SQL Server;
   - The column type is well supported by all/majors RDBMS;
   - We could change RDBMS without pains

> Coming Soon: Documentation and test project.
