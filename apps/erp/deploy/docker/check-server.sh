#!/usr/bin/env bash
# Revo-Com — verify VPS specs before Docker deployment.
# Usage:
#   bash deploy/docker/check-server.sh
#   bash deploy/docker/check-server.sh --with-postgres   # PostgreSQL on same VPS (needs 6 GB RAM)

set -u

WITH_POSTGRES=0
if [[ "${1:-}" == "--with-postgres" ]]; then
  WITH_POSTGRES=1
fi

MIN_CPU=2
MIN_RAM_MB=$(( WITH_POSTGRES == 1 ? 6144 : 4096 ))
MIN_DISK_TOTAL_GB=40
MIN_DISK_FREE_GB=30

PASS=0
FAIL=0
WARN=0

ok()   { echo "  [OK]   $*"; PASS=$((PASS + 1)); }
bad()  { echo "  [FAIL] $*"; FAIL=$((FAIL + 1)); }
warn() { echo "  [WARN] $*"; WARN=$((WARN + 1)); }

mb_from_kb() {
  awk -v kb="$1" 'BEGIN { printf "%d", kb / 1024 }'
}

gb_from_kb() {
  awk -v kb="$1" 'BEGIN { printf "%.1f", kb / 1024 / 1024 }'
}

echo "=============================================="
echo " Revo-Com server requirements check"
echo " $(date -Iseconds 2>/dev/null || date)"
echo " Mode: $([ "$WITH_POSTGRES" -eq 1 ] && echo 'app + PostgreSQL on same VPS' || echo 'app only (DB external)')"
echo "=============================================="
echo

echo "--- Operating system ---"
if [[ -r /etc/os-release ]]; then
  # shellcheck source=/dev/null
  . /etc/os-release
  echo "  $PRETTY_NAME"
  case "${ID:-}_${VERSION_ID:-}" in
    ubuntu_22.04|ubuntu_24.04)
      ok "Ubuntu LTS supported ($VERSION_ID)"
      ;;
    ubuntu_*)
      warn "Ubuntu $VERSION_ID — prefer 22.04 or 24.04 LTS"
      ;;
    *)
      warn "OS is not Ubuntu LTS — guide targets Ubuntu 22.04/24.04"
      ;;
  esac
else
  bad "Cannot read /etc/os-release"
fi

ARCH=$(uname -m)
echo "  Architecture: $ARCH"
case "$ARCH" in
  x86_64|amd64|aarch64|arm64) ok "64-bit architecture ($ARCH)" ;;
  *) bad "Unsupported architecture: $ARCH (need x86_64 or aarch64)" ;;
esac
echo

echo "--- CPU (minimum: ${MIN_CPU} vCPU) ---"
if command -v nproc >/dev/null 2>&1; then
  CPU=$(nproc)
  echo "  CPUs (nproc): $CPU"
  if [[ "$CPU" -ge "$MIN_CPU" ]]; then
    ok "CPU count meets minimum ($CPU >= $MIN_CPU)"
  else
    bad "Need at least $MIN_CPU CPUs, found $CPU"
  fi
else
  bad "nproc not available"
fi
if command -v lscpu >/dev/null 2>&1; then
  lscpu 2>/dev/null | grep -E 'Model name|CPU\(s\):|Thread|MHz' | sed 's/^/  /'
fi
echo

echo "--- RAM (minimum: ${MIN_RAM_MB} MB) ---"
if command -v free >/dev/null 2>&1; then
  free -h | sed 's/^/  /'
  MEM_KB=$(awk '/^Mem:/ {print $2}' /proc/meminfo)
  AVAIL_KB=$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)
  MEM_MB=$(mb_from_kb "$MEM_KB")
  AVAIL_MB=$(mb_from_kb "$AVAIL_KB")
  echo "  Total RAM: ${MEM_MB} MB | Available now: ${AVAIL_MB} MB"
  if [[ "$MEM_MB" -ge "$MIN_RAM_MB" ]]; then
    ok "Total RAM meets minimum (${MEM_MB} >= ${MIN_RAM_MB} MB)"
  else
    bad "Need at least ${MIN_RAM_MB} MB RAM, found ${MEM_MB} MB"
  fi
  if [[ "$AVAIL_MB" -lt 2048 ]]; then
    warn "Less than 2 GB available right now — stop heavy services before deploy"
  fi
else
  bad "free command not available"
fi

if swapon --show 2>/dev/null | grep -q .; then
  swapon --show | sed 's/^/  /'
  ok "Swap is configured"
else
  warn "No swap — on 4 GB RAM consider: sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile"
fi
echo

echo "--- Disk (minimum: ${MIN_DISK_TOTAL_GB} GB total, ${MIN_DISK_FREE_GB} GB free on /) ---"
if command -v df >/dev/null 2>&1; then
  df -h / | sed 's/^/  /'
  ROOT_KB=$(df -k / | awk 'NR==2 {print $2}')
  FREE_KB=$(df -k / | awk 'NR==2 {print $4}')
  ROOT_GB=$(gb_from_kb "$ROOT_KB")
  FREE_GB=$(gb_from_kb "$FREE_KB")
  echo "  Root total: ${ROOT_GB} GB | free: ${FREE_GB} GB"
  awk -v total="$ROOT_GB" -v free="$FREE_GB" -v min_t="$MIN_DISK_TOTAL_GB" -v min_f="$MIN_DISK_FREE_GB" 'BEGIN {
    if (total+0 >= min_t) exit 0; else exit 1
  }' && ok "Root volume size OK (${ROOT_GB} GB >= ${MIN_DISK_TOTAL_GB} GB)" || bad "Root volume too small (need >= ${MIN_DISK_TOTAL_GB} GB)"
  awk -v free="$FREE_GB" -v min_f="$MIN_DISK_FREE_GB" 'BEGIN {
    if (free+0 >= min_f) exit 0; else exit 1
  }' && ok "Free space on / OK (${FREE_GB} GB >= ${MIN_DISK_FREE_GB} GB)" || bad "Not enough free space on / (need >= ${MIN_DISK_FREE_GB} GB free)"
fi
if command -v lsblk >/dev/null 2>&1; then
  echo "  Block devices:"
  lsblk -d -o NAME,ROTA,SIZE,TYPE,MODEL 2>/dev/null | sed 's/^/    /'
  ROTA=$(lsblk -d -o ROTA 2>/dev/null | awk 'NR>1 && $1==0 {c++} END {print c+0}')
  if [[ "${ROTA:-0}" -gt 0 ]]; then
    ok "At least one disk reports ROTA=0 (typically SSD/NVMe)"
  else
    warn "Could not confirm SSD (ROTA=0) — HDD is slower but may work"
  fi
fi
echo

echo "--- Network ---"
if ping -c 1 -W 3 8.8.8.8 >/dev/null 2>&1; then
  ok "Outbound ICMP (internet) works"
else
  warn "ping 8.8.8.8 failed — check firewall; HTTPS may still work"
fi
if command -v curl >/dev/null 2>&1; then
  if curl -4 -s --max-time 10 https://registry-1.docker.io/v2/ >/dev/null 2>&1; then
    ok "HTTPS to Docker Hub reachable"
  else
    warn "Cannot reach Docker Hub over HTTPS — image pull may fail"
  fi
  PUB=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || true)
  [[ -n "$PUB" ]] && echo "  Public IPv4: $PUB" || warn "Could not detect public IPv4"
else
  warn "curl not installed — install with: sudo apt install -y curl"
fi
echo

echo "--- Ports (must be free for deployment) ---"
check_port_free() {
  local port=$1 label=$2
  if command -v ss >/dev/null 2>&1; then
    if ss -tln | awk -v p=":${port}" '$4 ~ p "$" {found=1} END {exit !found}'; then
      bad "Port $port ($label) is already in use"
      ss -tlnp | grep ":${port} " | sed 's/^/    /' || true
    else
      ok "Port $port ($label) is free"
    fi
  else
    warn "ss not found — skip port $port check"
  fi
}
check_port_free 8080 "Revo-Com HTTP (or set HTTP_PORT)"
check_port_free 80   "HTTP / Caddy"
check_port_free 443  "HTTPS / Caddy"
if [[ "$WITH_POSTGRES" -eq 1 ]]; then
  if ss -tln 2>/dev/null | grep -q ':5432 '; then
    warn "Port 5432 in use — OK if PostgreSQL is already installed"
  else
    ok "Port 5432 free (or Postgres not listening yet)"
  fi
fi
echo

echo "--- Software (install before deploy if missing) ---"
if command -v docker >/dev/null 2>&1; then
  docker --version | sed 's/^/  /'
  DOCKER_MAJOR=$(docker version --format '{{.Server.Version}}' 2>/dev/null | cut -d. -f1)
  if [[ "${DOCKER_MAJOR:-0}" -ge 24 ]] 2>/dev/null; then
    ok "Docker Engine >= 24"
  else
    warn "Docker installed but version < 24 or unknown — upgrade recommended"
  fi
else
  warn "Docker not installed yet (required)"
fi

if docker compose version >/dev/null 2>&1; then
  docker compose version | sed 's/^/  /'
  ok "Docker Compose v2 plugin present"
elif command -v docker-compose >/dev/null 2>&1; then
  docker-compose --version | sed 's/^/  /'
  warn "Legacy docker-compose found — prefer Docker Compose v2 plugin"
else
  warn "Docker Compose not installed yet (required)"
fi

if command -v git >/dev/null 2>&1; then
  git --version | sed 's/^/  /'
  ok "Git installed"
else
  warn "Git not installed — sudo apt install -y git"
fi
echo

echo "--- PostgreSQL (if --with-postgres) ---"
if [[ "$WITH_POSTGRES" -eq 1 ]]; then
  if command -v psql >/dev/null 2>&1; then
    psql --version | sed 's/^/  /'
    PG_MAJOR=$(psql --version | grep -oE '[0-9]+' | head -1)
    if [[ "${PG_MAJOR:-0}" -ge 14 ]]; then
      ok "PostgreSQL client >= 14"
    else
      bad "PostgreSQL 14+ required, found version $PG_MAJOR"
    fi
  else
    warn "psql not installed — install PostgreSQL 14+ on this VPS"
  fi
else
  echo "  Skipped (use --with-postgres to validate local DB)"
fi
echo

echo "=============================================="
echo " Summary: ${PASS} passed, ${FAIL} failed, ${WARN} warnings"
echo " Requirements reference:"
echo "   CPU >= ${MIN_CPU} | RAM >= ${MIN_RAM_MB} MB | Disk >= ${MIN_DISK_TOTAL_GB} GB (${MIN_DISK_FREE_GB} GB free)"
echo "=============================================="
if [[ "$FAIL" -gt 0 ]]; then
  echo " Result: NOT READY — fix [FAIL] items before production deploy."
  exit 1
fi
if [[ "$WARN" -gt 0 ]]; then
  echo " Result: ACCEPTABLE with warnings — review [WARN] items."
  exit 0
fi
echo " Result: READY — server meets minimum specs for this mode."
exit 0
