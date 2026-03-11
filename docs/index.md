---
layout: home

hero:
    name: Gobl
    text: PHP Database Abstraction Layer & ORM
    tagline: >
        Define your schema once. Query any RDBMS. Get fully-typed PHP entity
        classes generated automatically.
    image:
        src: /logo.svg
        alt: Gobl logo
    actions:
        - theme: brand
          text: Get Started
          link: /guide/
        - theme: alt
          text: View on GitHub
          link: https://github.com/silassare/gobl

features:
    - icon: 🗄️
      title: Multi-driver DBAL
      details: >
          First-class support for <strong>MySQL/MariaDB</strong>,
          <strong>PostgreSQL</strong>, and <strong>SQLite</strong> through a
          unified query-builder API. Switch databases without rewriting queries.

    - icon: ⚡
      title: Fluent Query Builder
      details: >
          Build <code>SELECT</code>, <code>INSERT</code>, <code>UPDATE</code>, and
          <code>DELETE</code> statements with a type-safe, composable PHP API.
          Full support for JOINs, subqueries, filters, GROUP BY, and LIMIT.

    - icon: 🏗️
      title: Schema-driven ORM
      details: >
          Declare tables as plain PHP arrays. Gobl validates column types,
          generates migration SQL, and produces typed entity + controller classes
          ready to use in your application.

    - icon: 🔒
      title: CRUD Event System
      details: >
          Every create, read, update, and delete operation fires authorisation and
          lifecycle events. Attach listeners to enforce business rules without
          touching the generated code.

    - icon: 🔄
      title: Code Generation
      details: >
          Generate PHP, TypeScript, and Dart client classes from a single schema
          definition. Keep your frontend and backend in perfect sync.

    - icon: 🧪
      title: Thoroughly Tested
      details: >
          Snapshot and live-database integration tests run against all three
          drivers in CI, ensuring dialect correctness and regression safety.
---
