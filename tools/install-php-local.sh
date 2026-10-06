#!/bin/bash
# 免 root 在本机安装 PHP 8.2（Debian bookworm）：下载官方 deb 并解压到 $HOME/php-local
# 适用于没有 sudo 的实验室/沙箱环境。已安装则直接退出。
set -e

PREFIX="$HOME/php-local"
DEBS_DIR="$PREFIX/debs"
ROOT="$PREFIX/root"
CODENAME="$(. /etc/os-release 2>/dev/null && echo "${VERSION_CODENAME:-bookworm}")"
MIRROR="http://deb.debian.org/debian"
ARCH="amd64"

if [ -x "$ROOT/usr/bin/php8.2" ]; then
  echo "本地 PHP 已存在：$ROOT/usr/bin/php8.2"
  exit 0
fi
command -v dpkg-deb >/dev/null || { echo "需要 dpkg-deb"; exit 1; }
command -v curl >/dev/null || { echo "需要 curl"; exit 1; }

mkdir -p "$DEBS_DIR" "$ROOT" "$PREFIX/bin" "$PREFIX/conf.d"
cd "$PREFIX"

echo "==> [1/4] 下载 $CODENAME 包索引"
curl -fsSL --retry 3 -o Packages.gz "$MIRROR/dists/$CODENAME/main/binary-$ARCH/Packages.gz"
gzip -dfc Packages.gz > Packages

echo "==> [2/4] 解析依赖闭包"
cat > "$PREFIX/resolve-deps.cjs" <<'NODE'
const fs = require('fs');
const path = require('path');
const BASE = process.env.PREFIX_DIR;
const MIRROR = process.env.DEB_MIRROR;
const ROOTS = process.argv.slice(2);
const installed = new Set();
for (const block of fs.readFileSync('/var/lib/dpkg/status', 'utf8').split('\n\n')) {
  const m = block.match(/^Package: (\S+)/m);
  if (m && /^Status: .*installed$/m.test(block)) installed.add(m[1]);
}
const pkgs = new Map();
for (const block of fs.readFileSync(path.join(BASE, 'Packages'), 'utf8').split('\n\n')) {
  const name = block.match(/^Package: (\S+)/m)?.[1];
  if (name && !pkgs.has(name)) pkgs.set(name, {
    depends: block.match(/^Depends: (.+)$/m)?.[1] || '',
    pre: block.match(/^Pre-Depends: (.+)$/m)?.[1] || '',
    filename: block.match(/^Filename: (.+)$/m)?.[1],
  });
}
const parseDeps = (f) => !f ? [] : f.split(',').map(d =>
  d.trim().split('|').map(a => a.match(/^([a-zA-Z0-9][a-zA-Z0-9+.:-]*)/)?.[1]).filter(Boolean));
const need = new Set();
const queue = [...ROOTS];
while (queue.length) {
  const cur = queue.shift();
  if (need.has(cur) || installed.has(cur)) continue;
  const info = pkgs.get(cur);
  if (!info) { console.error('仓库中缺失:', cur); continue; }
  need.add(cur);
  for (const group of [...parseDeps(info.depends), ...parseDeps(info.pre)]) {
    let pick = group.find(a => installed.has(a) || need.has(a)) || group.find(a => pkgs.has(a));
    if (pick && !need.has(pick) && !installed.has(pick)) queue.push(pick);
  }
}
console.error('需下载', need.size, '个包');
fs.writeFileSync(path.join(BASE, 'deb-list.txt'),
  [...need].sort().map(n => pkgs.get(n).filename).join('\n') + '\n');
NODE
PREFIX_DIR="$PREFIX" DEB_MIRROR="$MIRROR" node "$PREFIX/resolve-deps.cjs" \
  php8.2-cli php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip

echo "==> [3/4] 下载并解压 deb 包"
cd "$DEBS_DIR"
while read -r rel; do
  [ -z "$rel" ] && continue
  f="$(basename "$rel")"
  if [ ! -f "$f" ]; then
    echo "  下载 $f"
    curl -fsSL --retry 3 -o "$f" "$MIRROR/$rel"
  fi
done < "$PREFIX/deb-list.txt"
for f in "$DEBS_DIR"/*.deb; do dpkg-deb -x "$f" "$ROOT"; done

echo "==> [4/4] 写入 php.ini 与命令包装"
EXTDIR="$(echo "$ROOT"/usr/lib/php/20*/ | tr ' ' '\n' | head -1)"
EXTDIR="${EXTDIR%/}"
LIBDIR="$ROOT/usr/lib/x86_64-linux-gnu"
cat > "$PREFIX/php.ini" <<EOF
extension_dir=$EXTDIR
extension=phar.so
extension=pdo.so
extension=pdo_sqlite.so
extension=sqlite3.so
extension=mbstring.so
extension=iconv.so
extension=curl.so
extension=fileinfo.so
extension=dom.so
extension=xml.so
extension=simplexml.so
extension=xmlreader.so
extension=xmlwriter.so
extension=zip.so
extension=ctype.so
extension=tokenizer.so
extension=posix.so
phar.readonly=Off
date.timezone=Asia/Shanghai
sys_temp_dir=/tmp
error_reporting=E_ALL
memory_limit=512M
allow_url_fopen=On
EOF

cat > "$PREFIX/bin/php" <<EOF
#!/bin/bash
export LD_LIBRARY_PATH=$LIBDIR:\${LD_LIBRARY_PATH:-}
exec $ROOT/usr/bin/php8.2 -c "$PREFIX/php.ini" "\$@"
EOF
chmod +x "$PREFIX/bin/php"

curl -fsSL --retry 3 -o "$PREFIX/composer.phar" https://getcomposer.org/download/2.8.6/composer.phar
cat > "$PREFIX/bin/composer" <<EOF
#!/bin/bash
export LD_LIBRARY_PATH=$LIBDIR:\${LD_LIBRARY_PATH:-}
exec $ROOT/usr/bin/php8.2 -c "$PREFIX/php.ini" "$PREFIX/composer.phar" "\$@"
EOF
chmod +x "$PREFIX/bin/composer"

echo "完成。请把以下目录加入 PATH（start.sh 会自动使用它）："
echo "  export PATH=\"$PREFIX/bin:\$PATH\""
"$PREFIX/bin/php" -v
"$PREFIX/bin/php" -r 'echo "PDO 驱动: ", implode(",", PDO::getAvailableDrivers()), PHP_EOL;'
