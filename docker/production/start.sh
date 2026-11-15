#!/bin/sh
# Starts a Caligrafy Quill production container (the Dockerfile's CMD), 2.26.0:
#   1. sizes Apache to the machine: PHP_PROCESSES PHP processes (prefork). By default it follows the app: 2 per CPU
#      the container may use (at least 4), the fastest for pages and the database; 6 per CPU (at least 16) when the
#      app calls an AI service (an AI key is set), because a request waiting on its answer holds its process and
#      four of them at once would leave none for other pages. PHP_PROCESSES=<n> always wins.
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
    # the value without an inline comment (NAME= # note, NAME=value # note), quotes and spaces, as Dotenv reads it
    v=$(sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" .env | tail -n 1 | sed -e 's/^#.*//' -e 's/[[:space:]][[:space:]]*#.*$//' | tr -d "\"' \r")
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

# an app that calls an AI service: one of these keys has a value (in the environment or .env)
ai=""
for key in OPEN_AI_KEY GROQ_API_KEY ANTHROPIC_API_KEY AGENT_API_KEY TOGETHER_API_KEY FIREWORKS_API_KEY OPENROUTER_API_KEY WATSON_API_KEY; do
  [ -n "$(setting $key)" ] && ai="$key" && break
done
n=$(setting PHP_PROCESSES)
case "$n" in
  ''|*[!0-9]*)
    if [ -n "$ai" ]; then n=$(( $(cpus) * 6 )); [ "$n" -lt 16 ] && n=16
    else n=$(( $(cpus) * 2 )); [ "$n" -lt 4 ] && n=4; fi ;;
esac
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
preload=$(setting PRELOAD | tr '[:upper:]' '[:lower:]')
case "$preload" in off|false|no|0) sed -i 's|^opcache.preload|;opcache.preload|' /usr/local/etc/php/conf.d/zz-caligrafy.ini ;; esac
cache=$(setting CACHE | tr '[:upper:]' '[:lower:]')
case "$cache" in off|false|no|0) a2disconf -q caligrafy-cache >/dev/null 2>&1 || true; cache=off ;; *) a2enconf -q caligrafy-cache >/dev/null 2>&1 || true; cache=on ;; esac
echo "caligrafy: $n PHP processes$([ -n "$ai" ] && echo " ($ai is set: an AI app)"), keep-alive $(echo $keep | tr '[:upper:]' '[:lower:]'), response cache $cache"

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
