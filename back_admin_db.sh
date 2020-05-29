#!/bin/bash
set -e
source .env

tables="admin_menu admin_permissions admin_role_menu admin_role_permissions admin_role_users admin_roles admin_user_permissions admin_users"

set -x
mysqldump -h ${DB_HOST} -u ${DB_USERNAME} -p${DB_PASSWORD} -t --single-transaction ${DB_DATABASE} ${tables} > database/admin.sql
