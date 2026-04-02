#!/usr/bin/env bash
# creates the gobl_test DB + user for MySQL and PostgreSQL.
set -euo pipefail

DB_NAME=gobl_test
DB_USER=gobl_test
DB_PASS=g0bl@test

MYSQL_QUERY=<<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
SQL

echo '= Creating MySQL test database and user...'
mysql -h 127.0.0.1 -u root -p -e "$MYSQL_QUERY"


PGSQL_QUERY=<<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='$DB_USER') THEN
        CREATE ROLE $DB_USER WITH LOGIN PASSWORD '$DB_PASS';
    ELSE
        ALTER ROLE $DB_USER WITH PASSWORD '$DB_PASS';
    END IF;
END
\$\$;
SELECT 'CREATE DATABASE $DB_NAME OWNER $DB_USER' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname='$DB_NAME') \gexec
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
SQL

echo '= Creating PostgreSQL test database and user...'

PGHOST=127.0.0.1 PGUSER=postgres sudo -u postgres psql -v ON_ERROR_STOP=0 -c "$PGSQL_QUERY" postgres
