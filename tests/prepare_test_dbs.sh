#!/usr/bin/env bash
# Prepare the MySQL and PostgreSQL test databases of the Gobl integration suite.
# Run once before `make test` or `make test-integration`.
#
# Each server is reached as its administrator, and there are two shapes of that:
#
#   MYSQL_ADMIN_USER / MYSQL_ADMIN_PASS set   over TCP with those credentials, which is a
#                                             container or a remote server.
#   neither set                               over the local socket through sudo, which is how
#                                             a distribution package authenticates the
#                                             superuser (MySQL's auth_socket, PostgreSQL's peer).
#
# MYSQL_ADMIN_HOST and MYSQL_ADMIN_PORT move the TCP shape off 127.0.0.1:3306.
set -euo pipefail

DB_NAME=gobl_test
DB_USER=gobl_test
DB_PASS='g0bl@Test'

MYSQL_ADMIN_HOST="${MYSQL_ADMIN_HOST:-127.0.0.1}"
MYSQL_ADMIN_PORT="${MYSQL_ADMIN_PORT:-3306}"

# Runs the mysql client as the administrator, over TCP or over the local socket.
mysql_admin() {
	if [ -n "${MYSQL_ADMIN_USER:-}" ] || [ -n "${MYSQL_ADMIN_PASS:-}" ]; then
		# The password goes through the environment, not the command line, so it stays out of
		# the process list and the client stops warning about it over every line of output.
		MYSQL_PWD="${MYSQL_ADMIN_PASS:-}" \
			mysql -h "$MYSQL_ADMIN_HOST" -P "$MYSQL_ADMIN_PORT" \
			-u "${MYSQL_ADMIN_USER:-root}" "$@"
	else
		sudo mysql "$@"
	fi
}

# Runs psql as the administrator over the local socket.
psql_admin() {
	sudo -u postgres psql "$@"
}

# Says which of the two it is, a server that is not there or one that refuses us, because the
# answer is a different command each time and the client's own error says neither.
assert_answers() {
	local name="$1" err="$2" start_hint="$3" creds_hint="$4"

	case "$err" in
		*'(HY000): Can'*'connect'* | *'could not connect'* | *'Connection refused'* | *'No such file or directory'*)
			echo "    ${name} is not answering." >&2
			echo "    Start it, then run this again:" >&2
			echo "        ${start_hint}" >&2
			echo "        docker compose up -d   # or the servers of docker-compose.yml" >&2
			;;

		*)
			echo "    ${name} answered but refused the administrator connection:" >&2
			echo "        ${err}" >&2
			echo "    ${creds_hint}" >&2
			;;
	esac

	exit 1
}

echo "==> MySQL: checking the server answers..."

if ! err="$(mysql_admin -e 'SELECT VERSION()' 2>&1 >/dev/null)"; then
	assert_answers 'MySQL' "$err" \
		'sudo systemctl start mysql' \
		'Set MYSQL_ADMIN_USER and MYSQL_ADMIN_PASS to an account that may create a database.'
fi

echo "==> MySQL: creating database and user..."
mysql_admin <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
echo "    MySQL: '${DB_NAME}' and '${DB_USER}' ready."

echo "==> PostgreSQL: checking the server answers..."

if ! err="$(psql_admin -tAc 'SELECT version()' 2>&1 >/dev/null)"; then
	assert_answers 'PostgreSQL' "$err" \
		'sudo systemctl start postgresql' \
		'Run this as a user that may sudo to postgres.'
fi

echo "==> PostgreSQL: creating role and database..."
psql_admin -v ON_ERROR_STOP=0 postgres <<SQL
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
