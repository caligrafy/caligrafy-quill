#!/bin/sh
# Starts a Caligrafy Quill production container (the Dockerfile's CMD), 2.26.0:
#   1. sizes Apache to the machine: PHP_PROCESSES PHP processes (prefork), by default 4 per CPU the container may
#      use and at least 8. One process per CPU is fastest for pages that only compute, but a request that waits
#      (the database, an AI service) holds its process: 4 per CPU keeps pages answering while some requests wait.
#      An app whose pages call slow services at the same time sets PHP_PROCESSES higher (php caligrafer.php doctor).
#   2. keep-alive off (KEEP_ALIVE=on keeps it): with a fixed number of processes, an idle open connection would hold
#      a process; the proxy in front (Traefik, Caddy, a load balancer) keeps visitors' connections open instead.
#   3. the response cache (docker/production/cache.conf): on unless CACHE=off
#   4. prepares the database (php caligrafer.php deploy), compiles the views (php caligrafer.php optimize), then serves.
# PHP_PROCESSES and KEEP_ALIVE come from the environment or from the project's .env.
set -e
cd /var/www/html

setting() {   # setting NAME: the environment's value, else the last NAME= line of .env (quotes and spaces removed)
  eval "v=\${$1:-}"
  if [ -z "$v" ] && [ -f .env ]; then
    v=$(sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" .env | tail -n 1 | tr -d "\"' \r")
  fi
  printf '%s' "$v"
}

cpus() {      # the CPUs this container may use: its cgroup quota (v2, then v1), else the machine's
  if [ -r /sys/fs/cgroup/cpu.max ]; then
    set -- $(cat /sys/fs/cgroup/cpu.max)
    if [ "$1" != "max" ] && [ "${2:-0}" -gt 0 ]; then echo $(( ($1 + $2 - 1) / $2 )); return; fi
  elif [ -r /sys/fs/cgroup/cpu/cpu.cfs_quota_us ]; then
    q=$(cat /sys/fs/cgroup/cpu/cpu.cfs_quota_us); p=$(cat /sys/fs/cgroup/cpu/cpu.cfs_period_us)
    if [ "$q" -gt 0 ] && [ "$p" -gt 0 ]; then echo $(( (q + p - 1) / p )); return; fi
  fi
  nproc 2>/dev/null || echo 1
}

n=$(setting PHP_PROCESSES)
case "$n" in ''|*[!0-9]*) n=$(( $(cpus) * 4 )); [ "$n" -lt 8 ] && n=8 ;; esac
[ "$n" -lt 1 ] && n=1
keep=$(setting KEEP_ALIVE | tr '[:upper:]' '[:lower:]')
case "$keep" in on|true|yes|1) keep=On ;; *) keep=Off ;; esac

cat > /etc/apache2/conf-enabled/zz-caligrafy-processes.conf <<CONF
# written by docker/production/start.sh at start: PHP_PROCESSES=$n, KEEP_ALIVE=$keep
<IfModule mpm_prefork_module>
    StartServers         $n
    MinSpareServers      $n
    MaxSpareServers      $n
    ServerLimit          $n
    MaxRequestWorkers    $n
    MaxConnectionsPerChild 10000
</IfModule>
KeepAlive $keep
CONF
cache=$(setting CACHE | tr '[:upper:]' '[:lower:]')
case "$cache" in off|false|no|0) a2disconf -q caligrafy-cache >/dev/null 2>&1 || true; cache=off ;; *) a2enconf -q caligrafy-cache >/dev/null 2>&1 || true; cache=on ;; esac
echo "caligrafy: $n PHP processes, keep-alive $(echo $keep | tr '[:upper:]' '[:lower:]'), response cache $cache"

php caligrafer.php deploy --wait "${CALIGRAFY_DEPLOY_WAIT:-60}" || echo 'caligrafer deploy: the database is not prepared (see above)'
# views compiled and the class map built as the web server's user (their folders belong to it): the first page of a
# new container compiles nothing
su -s /bin/sh www-data -c 'php caligrafer.php optimize' || echo 'caligrafer optimize: views will be compiled on first use (see above)'
# ASYNC=on (2.26.0, experimental, needs php caligrafer.php add async): the async server listens on port 80, answers
# the capabilities channel itself from processes that keep the app in memory, and passes every other request to Apache
# on 127.0.0.1:8080
async=$(setting ASYNC | tr '[:upper:]' '[:lower:]')
if [ "$async" = "on" ] || [ "$async" = "true" ]; then
  if [ ! -d vendor/workerman/workerman ]; then
    echo "caligrafy: ASYNC=on but Workerman is not installed (php caligrafer.php add async): Apache serves everything"
  else
    sed -ri 's/^Listen 80$/Listen 127.0.0.1:8080/' /etc/apache2/ports.conf
    sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf
    apache2ctl start
    w=$(setting ASYNC_WORKERS); case "$w" in ''|*[!0-9]*) w=$(cpus) ;; esac
    echo "caligrafy: async server on port 80 ($w processes), everything else to Apache on 127.0.0.1:8080"
    exec php caligrafer.php async start --listen http://0.0.0.0:80 --workers "$w" --backend 127.0.0.1:8080
  fi
fi
exec apache2-foreground
