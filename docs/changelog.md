---
title: Changelog
---

# Changelog

All notable changes to Gobl are documented here.

## v2.0.0 - Current

- Complete rewrite with PHP 8.x type system
- New fluent query builder (`QBSelect`, `QBInsert`, `QBUpdate`, `QBDelete`)
- Schema-driven ORM with code generation (PHP, TypeScript, Dart)
- CRUD event system (`BeforeCreate`, `AfterEntityCreation`, ...)
- Multi-driver support: MySQL 8, PostgreSQL 13+, SQLite 3.35+
- `ORMController` with full CRUD helpers
- Migration runner with rollback support
- Snapshot-based query testing

---

## v1.5.0 - 2021-03-26

- Dart class generator added
- TypeScript and ORM class generators added
- Generator class is now abstract

## v1.4.1 - 2020-01-09

- Fix foreign key table alter bug in MySQL generator
- All ALTER statements now run after all table creations
- `src/ORM/Sample` directory moved to root

## v1.4.0 - 2020-08-20

- TypeScript `EntityBase` class added
- TypeScript uses directory structure for entity classes
- Bug fixes and code optimisation

## v1.3.1 - 2020-03-29

- Using phpcs for linting

## v1.3.0 - 2020-03-27

- `ORMFilters` added
- `DbConfig` added
- Interface suffix consistency
- Code cleanup

## v1.2.0 - 2020-03-20

- `ORMRequestBase` bug fix
- `ORMController` DB write/delete are now in a transaction
- OZone service class bug fix

## v1.1.0 - 2019-12-24

- ORM optimised, bug fix

## v1.0.9 - 2019-09-19

- Start using gobl-utils-ts

## v1.0.8 - 2019-08-26

- Class names consistency
- Code reorganised

## v1.0.7 - 2019-03-15

- ORM optimised to reduce generated code size

## v1.0.3 - 2018-12-18

- Transaction implemented

## v1.0.2 - 2018-10-15

- Some optimisations
