#!/bin/bash
# 运行后端自动化测试（PHPUnit，内存 SQLite）
set -e
ROOT="$(cd "$(dirname "$0")" && pwd)"
PHP_PREFIX="${PHP_PREFIX:-$HOME/php-local}"
export LD_LIBRARY_PATH="$PHP_PREFIX/root/usr/lib/x86_64-linux-gnu:${LD_LIBRARY_PATH:-}"
PHP="$PHP_PREFIX/root/usr/bin/php8.2"
PHP_INI="$PHP_PREFIX/php.ini"

cd "$ROOT/backend"
exec "$PHP" -c "$PHP_INI" vendor/bin/phpunit "$@"
