#!/usr/bin/env bash
# 一条本地命令启动培养皿污染隔离与观察系统：
#   ./start.sh
# 后端：FrankenPHP(内嵌 PHP) 跑 Laravel + SQLite，监听 127.0.0.1:8000
# 前端：Vite + React，监听 0.0.0.0:5173（/api、/storage 自动代理到 8000）
set -euo pipefail

export PATH="$HOME/.local/bin:$PATH"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"

# --- 1. 定位 PHP：优先系统原生 php；否则用静态 FrankenPHP（内嵌 PHP）---
WRAPPER_PHP="$HOME/.local/bin/php"
FRANKEN="$HOME/.local/opt/frankenphp"
PHP_BIN=""
USE_FRANKEN=1

CAND="$(command -v php 2>/dev/null || true)"
if [[ -n "$CAND" ]]; then
  REAL_CAND="$(readlink -f "$CAND" 2>/dev/null || echo "$CAND")"
  REAL_WRAP="$(readlink -f "$WRAPPER_PHP" 2>/dev/null || echo "$WRAPPER_PHP")"
  if [[ "$REAL_CAND" != "$REAL_WRAP" ]]; then
    PHP_BIN="$CAND"   # 系统里另有原生 php
    USE_FRANKEN=0
  fi
fi

if [[ "$USE_FRANKEN" == "1" ]]; then
  PHP_BIN="$WRAPPER_PHP"
  if [[ ! -x "$FRANKEN" ]]; then
    echo ">> 未找到 frankenphp，开始下载静态二进制（仅首次）..."
    mkdir -p "$HOME/.local/opt" "$HOME/.local/bin" "$HOME/.local/etc/php-conf.d"
    curl -fSL --retry 3 -o "$FRANKEN" \
      "https://github.com/dunglas/frankenphp/releases/download/v1.13.0/frankenphp-linux-x86_64"
    chmod +x "$FRANKEN"
  fi
fi

# frankenphp php-cli 的 argv 偏移修正（系统 php 不需要）
if [[ "$USE_FRANKEN" == "1" ]]; then
  export PHP_INI_SCAN_DIR="$HOME/.local/etc/php-conf.d"
  mkdir -p "$HOME/.local/etc/php-conf.d" "$HOME/.local/etc"
  cat > "$HOME/.local/etc/fix-argv.php" <<'PHP'
<?php
if (PHP_SAPI === 'cli' && isset($_SERVER['argv']) && is_array($_SERVER['argv']) && count($_SERVER['argv']) > 0) {
    $fixed = array_values($_SERVER['argv']);
    array_shift($fixed);
    $_SERVER['argv'] = $fixed;
    $GLOBALS['argv'] = $fixed;
    $GLOBALS['argc'] = count($fixed);
}
PHP
  echo "auto_prepend_file=$HOME/.local/etc/fix-argv.php" > "$HOME/.local/etc/php-conf.d/zz-fix.ini"
  cat > "$PHP_BIN" <<'SH'
#!/bin/sh
export PHP_INI_SCAN_DIR="$HOME/.local/etc/php-conf.d"
exec "$HOME/.local/opt/frankenphp" php-cli "$@"
SH
  chmod +x "$PHP_BIN"
fi

# --- 2. Composer（仅首次） ---
if [[ ! -f "$HOME/.local/opt/composer.phar" ]]; then
  echo ">> 安装 composer..."
  curl -fSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  "$PHP_BIN" /tmp/composer-setup.php --install-dir="$HOME/.local/opt" --filename=composer.phar
fi
composer_cmd=("$PHP_BIN" "$HOME/.local/opt/composer.phar")
if command -v composer >/dev/null 2>&1; then composer_cmd=(composer); fi

# --- 3. 后端依赖、SQLite、迁移与演示数据 ---
if [[ ! -f "$BACKEND/vendor/autoload.php" ]]; then
  echo ">> 安装后端 Composer 依赖..."
  "${composer_cmd[@]}" install --working-dir="$BACKEND" --no-interaction --no-scripts
  "$PHP_BIN" "$BACKEND/artisan" package:discover
fi
[[ -f "$BACKEND/.env" ]] || cp "$BACKEND/.env.example" "$BACKEND/.env"
# 强制使用 SQLite
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' "$BACKEND/.env"
touch "$BACKEND/database/database.sqlite"

APP_KEY="$($PHP_BIN -r 'require "'$BACKEND'/vendor/autoload.php"; $e=parse_ini_file("'$BACKEND'/.env"); echo $e["APP_KEY"] ?? "";')"
if [[ -z "$APP_KEY" ]]; then
  "$PHP_BIN" "$BACKEND/artisan" key:generate
fi
"$PHP_BIN" "$BACKEND/artisan" migrate --force
"$PHP_BIN" "$BACKEND/artisan" storage:link >/dev/null 2>&1 || true
# 仅在空库时播种演示数据
if [[ "$($PHP_BIN -r '$db=new PDO("sqlite:'$BACKEND'/database/database.sqlite"); echo (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();')" == "0" ]]; then
  echo ">> 播种演示数据（实验员/负责人、培养箱、P-001~P-005 与一条隔离登记）..."
  "$PHP_BIN" "$BACKEND/artisan" db:seed --force
fi

# --- 4. 前端依赖 ---
if [[ ! -d "$FRONTEND/node_modules" ]]; then
  echo ">> 安装前端 npm 依赖..."
  (cd "$FRONTEND" && npm install --no-audit --no-fund)
fi

# --- 5. 启动 ---
cleanup() {
  echo ""; echo ">> 正在停止服务..."
  [[ -n "${BACK_PID:-}" ]] && kill "$BACK_PID" 2>/dev/null || true
  [[ -n "${FRONT_PID:-}" ]] && kill "$FRONT_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo ">> 启动后端 http://127.0.0.1:8000"
if [[ "$USE_FRANKEN" == "0" ]]; then
  (cd "$BACKEND" && "$PHP_BIN" -S 127.0.0.1:8000 server_dev.php >/tmp/lab-backend.log 2>&1) &
else
  "$FRANKEN" php-server --listen 127.0.0.1:8000 --root "$BACKEND/public" >/tmp/lab-backend.log 2>&1 &
fi
BACK_PID=$!

# 等待后端就绪
for i in $(seq 1 30); do
  if curl -sf http://127.0.0.1:8000/up >/dev/null 2>&1; then break; fi
  sleep 0.5
done

echo ">> 启动前端 http://localhost:5173"
(cd "$FRONTEND" && npm run dev >/tmp/lab-frontend.log 2>&1) &
FRONT_PID=$!

cat <<'TIP'

╔══════════════════════════════════════════════════════════════╗
║  培养皿污染隔离与观察系统已启动：                              ║
║    前端页面  http://localhost:5173                            ║
║    后端 API  http://127.0.0.1:8000/api                        ║
║    复检提醒  php artisan lab:retest-remind （在 backend 目录） ║
║  右上角可切换「实验员 / 负责人」身份。Ctrl+C 停止全部服务。    ║
╚══════════════════════════════════════════════════════════════╝
TIP

wait
