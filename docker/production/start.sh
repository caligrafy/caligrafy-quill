#!/bin/sh
# Starts a Caligrafy Quill production container (the Dockerfile's CMD), 2.26.0:
#   1. sizes Apache to the machine: PHP_PROCESSES PHP processes (prefork), by default 4 per CPU the container may
#      use and at least 8. One process per CPU is fastest for pages that only compute, but a request that waits
#      (the database, an AI service) holds its process: 4 per CPU keeps pages answering while some requests wait.
#      An app whose pages call slow services at the same time sets PHP_PROCESSES higher (php caligrafer.php doctor).
#   2. keep-alive off (KEEP_ALIVE=on keeps it): with a fixed number of processes, an idle open connection would hold
#      a process; the proxy in front (Traefik, Caddy, a load balancer) keeps visitors' connections open instead.
#   3. prepares the database (php caligrafer.php deploy), then serves.
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
echo "caligrafy: $n PHP processes, keep-alive $(echo $keep | tr '[:upper:]' '[:lower:]')"

php caligrafer.php deploy --wait "${CALIGRAFY_DEPLOY_WAIT:-60}" || echo 'caligrafer deploy: the database is not prepared (see above)'
exec apache2-foreground
