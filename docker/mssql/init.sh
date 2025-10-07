#!/bin/bash
set -e

/opt/mssql/bin/sqlservr &
SQLSERVER_PID=$!

until /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'YourStrong!Passw0rd' -C -Q "SELECT 1 FROM tempdb.sys.tables" -h -1 >/dev/null 2>&1
do
    sleep 1
done

/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'YourStrong!Passw0rd' -C -Q "
IF NOT EXISTS (SELECT * FROM sys.sql_logins WHERE name = N'yii')
  CREATE LOGIN yii WITH PASSWORD = 'q1w2e3r4!';
"

/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'YourStrong!Passw0rd' -C -Q "
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = N'ar-test')
  CREATE DATABASE [ar-test];
"

/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P 'YourStrong!Passw0rd' -C -d ar-test -Q "
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = N'yii')
  CREATE USER yii FOR LOGIN yii;
ALTER ROLE db_owner ADD MEMBER yii;
"

wait $SQLSERVER_PID
