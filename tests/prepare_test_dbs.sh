#!/usr/bin/env bash
# Prepare MySQL and PostgreSQL test databases for the Gobl integration test suite.
# Run once before `make test` or `make test-integration`.
#
# MySQL admin credentials default to root with no password (you will be prompted).
# Override via env vars:  MYSQL_ADMIN_USER=root MYSQL_ADMIN_PASS=secret
set -euo pipefail

DB_NAME=gobl_test
DB_USER=gobl_test
DB_PASS='g0bl@Test'

MYSQL_ADMIN_USER="${MYSQL_ADMIN_USER:-root}"
MYSQL_ADMIN_PASS="${MYSQL_ADMIN_PASS:-}"

echo "==> MySQL: creating database and user..."
mysql -h 127.0.0.1 -u "$MYSQL_ADMIN_USER" -p"$MYSQL_ADMIN_PASS" << SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
echo "    MySQL: '${DB_NAME}' and '${DB_USER}' ready."

echo "==> PostgreSQL: creating role and database..."
PGHOST=127.0.0.1 PGUSER=postgres sudo -u postgres psql -v ON_ERROR_STOP=0 postgres << SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
    CREATE ROLE ${DB_USER} WITH LOGIN PASSWORD '${DB_PASS}';
  ELSE
    ALTER ROLE ${DB_USER} WITH PASSWORD '${DB_PASS}';
  END IF;
END
\$\$;
SELECT 'CREATE DATABASE ${DB_NAME} OWNER ${DB_USER}'
 WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${DB_NAME}') \gexec
GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};
SQL
echo "    PostgreSQL: '${DB_NAME}' and '${DB_USER}' ready."
