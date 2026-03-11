# What is Gobl?

Gobl is a **PHP 8.1+ Database Abstraction Layer (DBAL) and Object-Relational Mapper (ORM)** that
lets you define your database schema in plain PHP arrays and then query any supported RDBMS through
a consistent API.

## Core ideas

| Concept                     | What it means                                                                                                               |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **Schema-first**            | Tables, columns, constraints, and relations are defined in PHP arrays - not in annotations scattered across entity classes. |
| **Multi-driver**            | One codebase runs on MySQL/MariaDB, PostgreSQL, and SQLite.                                                                 |
| **Code-generated entities** | Run one command and get strongly-typed PHP entity and controller classes. Regenerate after every schema change.             |
| **Event-driven CRUD**       | Every write operation fires authorisation events. Business rules live in listeners, not in controllers.                     |
| **No magic**                | Every generated SQL statement can be printed and inspected before it runs.                                                  |

## How it fits into a larger framework

```
your-app/
├── config/schema.php    <- one schema file per domain
├── src/
│   ├── Db/              <- Gobl-generated entity classes (committed)
│   └── Services/        <- your business logic uses ORM controllers
└── migrations/          <- Gobl migration runner tracks applied migrations
```

## Packages in the ecosystem

> This documentation covers the core `silassare/gobl` package.
> Other packages in the framework build on top of it.

- [`silassare/gobl`](https://github.com/silassare/gobl) - core DBAL + ORM _(you are here)_

## Supported PHP & databases

|                 | Version      |
| --------------- | ------------ |
| PHP             | >= 8.1        |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PostgreSQL      | 12+          |
| SQLite          | 3.35+        |
