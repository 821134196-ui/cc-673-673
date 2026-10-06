#!/bin/bash
# 培养皿污染隔离与观察系统 —— 一条命令启动（后端 Laravel + 前端 React/Vite）
set -e

ROOT="$(cd "$(dirname "$0")" && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"
PHP_PREFIX="${PHP_PREFIX:-$HOME/php-local}"
export LD_LIBRARY_PATH="$PHP_PREFIX/root/usr/lib/x86_64-linux-gnu:${LD_LIBRARY_PATH:-}"
PHP="$PHP_PREFIX/root/usr/bin/php8.2"
PHP_INI="$PHP_PREFIX/php.ini"

BACKEND_PORT="${BACKEND_PORT:-8000}"
FRONTEND_PORT="${FRONTEND_PORT:-5173}"

if [ ! -x "$PHP" ]; then
  if [ -x "$ROOT/tools/install-php-local.sh" ]; then
    echo "==> 未找到本地 PHP，运行免 root 安装脚本"
    "$ROOT/tools/install-php-local.sh"
  else
    echo "未找到本地 PHP: $PHP（可运行 tools/install-php-local.sh 安装）"; exit 1
  fi
fi
if [ ! -f "$BACKEND/.env" ]; then
  if [ -f "$BACKEND/.env.example" ]; then
    cp "$BACKEND/.env.example" "$BACKEND/.env"
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' "$BACKEND/.env"
    grep -q '^DB_DATABASE=' "$BACKEND/.env" \
      && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$BACKEND/database/database.sqlite|" "$BACKEND/.env" \
      || echo "DB_DATABASE=$BACKEND/database/database.sqlite" >> "$BACKEND/.env"
    (cd "$BACKEND" && "$PHP" -c "$PHP_INI" artisan key:generate --ansi)
  else
    echo "缺少 $BACKEND/.env 且无 .env.example 模板"; exit 1
  fi
fi

# 首次运行：安装前端依赖
if [ ! -d "$FRONTEND/node_modules" ]; then
  echo "==> 安装前端依赖（首次运行）"
  (cd "$FRONTEND" && npm install)
fi

# 确保数据库已迁移
if [ ! -s "$BACKEND/database/database.sqlite" ]; then
  touch "$BACKEND/database/database.sqlite"
fi
echo "==> 执行数据库迁移"
(cd "$BACKEND" && "$PHP" -c "$PHP_INI" artisan migrate --force)

# 首次使用（无账号时）填充演示数据
USER_COUNT=$(cd "$BACKEND" && "$PHP" -c "$PHP_INI" artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -1 | tr -d -c 0-9)
if [ "$USER_COUNT" = "0" ]; then
  echo "==> 首次运行：填充演示账号与数据"
  (cd "$BACKEND" && "$PHP" -c "$PHP_INI" artisan db:seed --force)
fi
# 公开磁盘（照片访问）软链，幂等创建
if [ ! -e "$BACKEND/public/storage" ]; then
  (cd "$BACKEND" && "$PHP" -c "$PHP_INI" artisan storage:link)
fi

echo "==> 启动后端 Laravel  : http://127.0.0.1:$BACKEND_PORT"
"$PHP" -c "$PHP_INI" -S 127.0.0.1:"$BACKEND_PORT" "$BACKEND/server.php" -t "$BACKEND/public" &
BACKEND_PID=$!

echo "==> 启动前端 React/Vite: http://127.0.0.1:$FRONTEND_PORT"
(cd "$FRONTEND" && npm run dev -- --host 127.0.0.1 --port "$FRONTEND_PORT") &
FRONTEND_PID=$!

cleanup() {
  echo ""
  echo "==> 正在停止服务..."
  kill "$BACKEND_PID" "$FRONTEND_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo ""
echo "================================================================"
echo " 培养皿污染隔离与观察系统已启动"
echo " 前端页面: http://127.0.0.1:$FRONTEND_PORT"
echo " 后端 API: http://127.0.0.1:$BACKEND_PORT/api"
echo " 实验员账号: tech@lab.local / password"
echo " 负责人账号: lead@lab.local / password"
echo " 按 Ctrl+C 停止全部服务"
echo "================================================================"

wait -n "$BACKEND_PID" "$FRONTEND_PID"
