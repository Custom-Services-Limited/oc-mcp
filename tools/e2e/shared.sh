#!/usr/bin/env bash

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

OC_MAJOR=""
OC_VERSION=""
OC_DOWNLOAD_URL=""
ARTIFACT_DIR=""
WORK_DIR=""
WEBROOT=""
RESP_DIR=""
LOG_DIR=""
RUN_ID=""
NETWORK=""
DB_CONTAINER=""
WEB_CONTAINER=""
PHP_IMAGE=""
BASE_URL=""
ENDPOINT_URL=""
HEALTH_URL=""
TOKEN=""
DB_ROOT_PASSWORD="opencart"
DB_USERNAME="opencart"
DB_PASSWORD="opencart"
DB_NAME="opencart"
DB_PREFIX="oc_"
CURRENT_PHASE="bootstrap"
CURRENT_COMMAND=""
FAIL_REPORTED="0"

log() {
  printf '[oc-mcp-e2e] %s\n' "$*"
}

json_quote() {
  if command -v php >/dev/null 2>&1; then
    printf '%s' "$1" | php -r 'echo json_encode(stream_get_contents(STDIN), JSON_UNESCAPED_SLASHES);' 2>/dev/null && return
  fi
  printf '"%s"' "$(printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g')"
}

append_summary() {
  printf '%s\n' "$*" >>"$ARTIFACT_DIR/summary.md"
}

init_paths() {
  local default_version="$1"
  OC_VERSION="${OC_VERSION:-$default_version}"
  OC_DOWNLOAD_URL="${OC_DOWNLOAD_URL:-https://github.com/opencart/opencart/releases/download/${OC_VERSION}/opencart-${OC_VERSION}.zip}"
  local artifact_base="${E2E_ARTIFACT_DIR:-$ROOT_DIR/artifacts/e2e}"
  ARTIFACT_DIR="$artifact_base/opencart${OC_MAJOR}-${OC_VERSION}"
  RUN_ID="oc${OC_MAJOR}-${OC_VERSION//[^A-Za-z0-9]/-}-$$-$(date +%s)"
  WORK_DIR="${E2E_WORK_DIR:-${TMPDIR:-/tmp}/oc-mcp-e2e-${RUN_ID}}"
  WEBROOT="$WORK_DIR/webroot"
  RESP_DIR="$ARTIFACT_DIR/responses"
  LOG_DIR="$ARTIFACT_DIR/logs"
  NETWORK="ocmcp-e2e-${RUN_ID}"
  DB_CONTAINER="ocmcp-db-${RUN_ID}"
  WEB_CONTAINER="ocmcp-web-${RUN_ID}"
  PHP_IMAGE="ocmcp-e2e-php:${RUN_ID}"

  rm -rf "$ARTIFACT_DIR" "$WORK_DIR"
  mkdir -p "$RESP_DIR" "$LOG_DIR" "$WORK_DIR"
  {
    printf '# OpenCart %s E2E\n\n' "$OC_MAJOR"
    printf -- '- OpenCart version: `%s`\n' "$OC_VERSION"
    printf -- '- Artifact dir: `%s`\n' "$ARTIFACT_DIR"
    printf -- '- Work dir: `%s`\n\n' "$WORK_DIR"
  } >"$ARTIFACT_DIR/summary.md"
}

collect_diagnostics() {
  set +e
  mkdir -p "$LOG_DIR"
  if [[ -n "$WEB_CONTAINER" ]]; then
    docker logs "$WEB_CONTAINER" >"$LOG_DIR/docker-web.log" 2>&1
  fi
  if [[ -n "$DB_CONTAINER" ]]; then
    docker logs "$DB_CONTAINER" >"$LOG_DIR/docker-db.log" 2>&1
  fi
  if [[ -d "$WEBROOT/system/storage/logs" ]]; then
    mkdir -p "$LOG_DIR/opencart"
    cp -R "$WEBROOT/system/storage/logs/." "$LOG_DIR/opencart/" 2>/dev/null
  fi
  if [[ -n "$DB_CONTAINER" ]] && docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER"; then
    docker exec -e MYSQL_PWD="$DB_PASSWORD" "$DB_CONTAINER" \
      mariadb -u"$DB_USERNAME" "$DB_NAME" -e "SELECT audit_id, request_id, client_id, method, tool_name, status, error_code, created_at FROM ${DB_PREFIX}mcp_audit_log ORDER BY audit_id DESC LIMIT 20" \
      >"$LOG_DIR/mcp_audit_tail.tsv" 2>"$LOG_DIR/mcp_audit_tail.err"
  fi
  set -e
}

write_failure() {
  local code="$1"
  local phase="$2"
  local message="$3"
  local suggested_focus="$4"
  local command="${5:-$CURRENT_COMMAND}"
  local response_file="${RESPONSE_FILE:-}"
  local http_status="${HTTP_STATUS:-}"
  local jsonrpc_error="${JSONRPC_ERROR:-}"

  collect_diagnostics
  cat >"$ARTIFACT_DIR/failure.json" <<JSON
{
  "phase": $(json_quote "$phase"),
  "code": $(json_quote "$code"),
  "message": $(json_quote "$message"),
  "command": $(json_quote "$command"),
  "http_status": $(json_quote "$http_status"),
  "jsonrpc_error": $(json_quote "$jsonrpc_error"),
  "response_file": $(json_quote "$response_file"),
  "suggested_focus": $(json_quote "$suggested_focus")
}
JSON
  append_summary ""
  append_summary "## Failure"
  append_summary "- Phase: \`$phase\`"
  append_summary "- Code: \`$code\`"
  append_summary "- Message: $message"
  append_summary "- Suggested focus: $suggested_focus"
  if [[ -n "$response_file" ]]; then
    append_summary "- Response file: \`$response_file\`"
  fi
  if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
    printf '::error title=%s::%s. See %s\n' "$code" "$message" "$ARTIFACT_DIR/failure.json"
  fi
}

fatal() {
  local code="$1"
  local phase="$2"
  local message="$3"
  local suggested_focus="$4"
  local command="${5:-$CURRENT_COMMAND}"
  FAIL_REPORTED="1"
  write_failure "$code" "$phase" "$message" "$suggested_focus" "$command"
  exit 1
}

on_err() {
  local exit_code="$1"
  local line="$2"
  local command="$3"
  if [[ "$FAIL_REPORTED" != "1" ]]; then
    FAIL_REPORTED="1"
    write_failure "unexpected.error" "$CURRENT_PHASE" "Unexpected command failure at line $line with exit code $exit_code." "Inspect the command, shell trace, and Docker/OpenCart logs." "$command"
  fi
  exit "$exit_code"
}

cleanup() {
  set +e
  if [[ "${KEEP_E2E:-0}" == "1" ]]; then
    log "KEEP_E2E=1; preserving containers and work dir: $WORK_DIR"
    return
  fi
  if [[ -n "$WEB_CONTAINER" ]] && docker ps --format '{{.Names}}' | grep -qx "$WEB_CONTAINER"; then
    docker exec "$WEB_CONTAINER" sh -c 'chmod -R a+rwX /var/www/html 2>/dev/null || true' >/dev/null 2>&1
  fi
  [[ -n "$WEB_CONTAINER" ]] && docker rm -f "$WEB_CONTAINER" >/dev/null 2>&1
  [[ -n "$DB_CONTAINER" ]] && docker rm -f "$DB_CONTAINER" >/dev/null 2>&1
  [[ -n "$NETWORK" ]] && docker network rm "$NETWORK" >/dev/null 2>&1
  [[ -n "$PHP_IMAGE" ]] && docker image rm "$PHP_IMAGE" >/dev/null 2>&1
  [[ -n "$WORK_DIR" ]] && rm -rf "$WORK_DIR" >/dev/null 2>&1
}

preflight() {
  CURRENT_PHASE="preflight"
  local missing=()
  for dep in docker curl jq unzip php composer; do
    command -v "$dep" >/dev/null 2>&1 || missing+=("$dep")
  done
  if (( ${#missing[@]} > 0 )); then
    fatal "preflight.missing_dependency" "$CURRENT_PHASE" "Missing required command(s): ${missing[*]}" "Install the missing local dependency or update the GitHub Actions runner setup." "command -v ${missing[*]}"
  fi
  CURRENT_COMMAND="docker info"
  docker info >"$LOG_DIR/docker-info.log" 2>&1 || fatal "preflight.docker_unavailable" "$CURRENT_PHASE" "Docker daemon is not available." "Start Docker locally or use a GitHub-hosted runner with Docker enabled." "$CURRENT_COMMAND"
  append_summary "- Preflight passed."
}

download_opencart() {
  CURRENT_PHASE="opencart.download"
  log "Downloading OpenCart $OC_VERSION"
  CURRENT_COMMAND="curl -fsSL $OC_DOWNLOAD_URL"
  curl -fsSL "$OC_DOWNLOAD_URL" -o "$WORK_DIR/opencart.zip" 2>"$LOG_DIR/opencart-download.err" \
    || fatal "opencart.download_failed" "$CURRENT_PHASE" "Failed to download OpenCart release $OC_VERSION." "Verify OC_VERSION or OC_DOWNLOAD_URL and network access." "$CURRENT_COMMAND"

  mkdir -p "$WORK_DIR/opencart-src"
  CURRENT_COMMAND="unzip -q $WORK_DIR/opencart.zip"
  unzip -q "$WORK_DIR/opencart.zip" -d "$WORK_DIR/opencart-src" 2>"$LOG_DIR/opencart-unzip.err" \
    || fatal "opencart.download_failed" "$CURRENT_PHASE" "Failed to extract OpenCart release archive." "Inspect the downloaded archive and release layout." "$CURRENT_COMMAND"

  local upload_dir="$WORK_DIR/opencart-src/upload"
  if [[ ! -d "$upload_dir" ]]; then
    upload_dir="$(find "$WORK_DIR/opencart-src" -maxdepth 3 -type d -name upload | head -n 1)"
  fi
  [[ -n "$upload_dir" && -d "$upload_dir" ]] \
    || fatal "opencart.download_failed" "$CURRENT_PHASE" "OpenCart archive did not contain an upload/ directory." "Check the release archive layout for the pinned version." "find upload"

  mkdir -p "$WEBROOT"
  cp -R "$upload_dir/." "$WEBROOT/"
  append_summary "- Downloaded and extracted OpenCart."
}

build_php_image() {
  CURRENT_PHASE="docker.image"
  mkdir -p "$WORK_DIR/docker"
  cat >"$WORK_DIR/docker/Dockerfile" <<'DOCKERFILE'
FROM php:8.3-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    curl \
    default-mysql-client \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    unzip \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" curl gd mbstring mysqli zip \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

RUN { \
    echo 'display_errors=1'; \
    echo 'log_errors=1'; \
    echo 'memory_limit=512M'; \
    echo 'post_max_size=64M'; \
    echo 'upload_max_filesize=64M'; \
    echo 'max_execution_time=120'; \
  } > /usr/local/etc/php/conf.d/oc-mcp-e2e.ini
DOCKERFILE

  log "Building PHP-Apache E2E image"
  CURRENT_COMMAND="docker build -t $PHP_IMAGE $WORK_DIR/docker"
  docker build -t "$PHP_IMAGE" "$WORK_DIR/docker" >"$LOG_DIR/docker-build.log" 2>&1 \
    || fatal "docker.image_failed" "$CURRENT_PHASE" "Failed to build PHP-Apache image." "Inspect docker-build.log for missing packages or Docker Hub/network failures." "$CURRENT_COMMAND"
}

start_database() {
  CURRENT_PHASE="docker.database"
  CURRENT_COMMAND="docker network create $NETWORK"
  docker network create "$NETWORK" >"$LOG_DIR/docker-network.log" 2>&1 \
    || fatal "docker.network_failed" "$CURRENT_PHASE" "Failed to create Docker network." "Inspect Docker availability and existing resources." "$CURRENT_COMMAND"

  log "Starting MariaDB"
  CURRENT_COMMAND="docker run mariadb:10.11"
  docker run -d --name "$DB_CONTAINER" --network "$NETWORK" \
    -e MARIADB_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
    -e MARIADB_DATABASE="$DB_NAME" \
    -e MARIADB_USER="$DB_USERNAME" \
    -e MARIADB_PASSWORD="$DB_PASSWORD" \
    mariadb:10.11 \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci \
    >"$LOG_DIR/docker-db.cid" 2>"$LOG_DIR/docker-db-start.err" \
    || fatal "docker.database_failed" "$CURRENT_PHASE" "Failed to start MariaDB container." "Inspect docker-db-start.err and Docker daemon state." "$CURRENT_COMMAND"

  for _ in $(seq 1 90); do
    if docker exec -e MYSQL_PWD="$DB_PASSWORD" "$DB_CONTAINER" mariadb -u"$DB_USERNAME" "$DB_NAME" -e 'SELECT 1' >/dev/null 2>&1; then
      append_summary "- MariaDB is ready."
      return
    fi
    sleep 1
  done
  fatal "docker.database_failed" "$CURRENT_PHASE" "MariaDB did not become ready in time." "Inspect docker-db.log for startup or permission errors." "mariadb-admin ping"
}

start_web() {
  CURRENT_PHASE="docker.web"
  chmod -R a+rwX "$WEBROOT"
  local port_spec
  if [[ -n "${OC_HTTP_PORT:-}" ]]; then
    port_spec="127.0.0.1:${OC_HTTP_PORT}:80"
  else
    port_spec="127.0.0.1::80"
  fi

  log "Starting OpenCart web container"
  CURRENT_COMMAND="docker run $PHP_IMAGE"
  docker run -d --name "$WEB_CONTAINER" --network "$NETWORK" \
    -p "$port_spec" \
    -v "$WEBROOT:/var/www/html" \
    "$PHP_IMAGE" \
    >"$LOG_DIR/docker-web.cid" 2>"$LOG_DIR/docker-web-start.err" \
    || fatal "docker.web_failed" "$CURRENT_PHASE" "Failed to start PHP-Apache container." "Inspect docker-web-start.err and port availability." "$CURRENT_COMMAND"

  local mapped_port
  mapped_port="$(docker port "$WEB_CONTAINER" 80/tcp | awk -F: 'NR == 1 {print $NF}')"
  [[ -n "$mapped_port" ]] || fatal "docker.web_failed" "$CURRENT_PHASE" "Could not determine mapped HTTP port." "Inspect Docker port mappings for the web container." "docker port $WEB_CONTAINER 80/tcp"
  BASE_URL="http://127.0.0.1:${mapped_port}/"
  ENDPOINT_URL="${BASE_URL}index.php?route=extension/mcp/server"
  HEALTH_URL="${BASE_URL}index.php?route=extension/mcp/health"
  append_summary "- Web container started at \`$BASE_URL\`."
}

install_opencart() {
  CURRENT_PHASE="opencart.install"
  log "Installing OpenCart $OC_VERSION"
  docker exec "$WEB_CONTAINER" bash -lc 'touch /var/www/html/config.php /var/www/html/admin/config.php && chmod -R a+rwX /var/www/html/config.php /var/www/html/admin/config.php /var/www/html/system/storage /var/www/html/image' \
    >"$LOG_DIR/opencart-permissions.log" 2>&1 \
    || fatal "opencart.install_failed" "$CURRENT_PHASE" "Failed to prepare OpenCart writable paths." "Inspect web container filesystem permissions." "chmod OpenCart writable paths"

  CURRENT_COMMAND="php install/cli_install.php install"
  docker exec "$WEB_CONTAINER" php /var/www/html/install/cli_install.php install \
    --db_hostname "$DB_CONTAINER" \
    --db_username "$DB_USERNAME" \
    --db_password "$DB_PASSWORD" \
    --db_database "$DB_NAME" \
    --db_driver mysqli \
    --db_port 3306 \
    --db_prefix "$DB_PREFIX" \
    --username admin \
    --password admin \
    --email admin@example.com \
    --http_server "$BASE_URL" \
    >"$LOG_DIR/opencart-install.log" 2>&1 \
    || fatal "opencart.install_failed" "$CURRENT_PHASE" "OpenCart CLI installer failed." "Inspect opencart-install.log and database logs." "$CURRENT_COMMAND"

  docker exec "$WEB_CONTAINER" rm -rf /var/www/html/install >/dev/null 2>&1 || true
  wait_for_http "$BASE_URL" "opencart.install_failed" "$CURRENT_PHASE" "OpenCart storefront did not respond after install." "Inspect OpenCart logs and Apache container logs."
  append_summary "- OpenCart CLI install completed."
}

build_extension_package() {
  CURRENT_PHASE="mcp.package_build"
  log "Building MCP extension package"
  CURRENT_COMMAND="php tools/build.php --version=0.0.0-e2e"
  (cd "$ROOT_DIR" && php tools/build.php --version=0.0.0-e2e) >"$LOG_DIR/package-build.log" 2>&1 \
    || fatal "mcp.package_install_failed" "$CURRENT_PHASE" "Failed to build installable MCP packages." "Inspect package-build.log and PHP ZipArchive availability." "$CURRENT_COMMAND"
  append_summary "- MCP package build completed."
}

install_extension_package() {
  CURRENT_PHASE="mcp.package_install"
  local package="$ROOT_DIR/dist/mcp.ocmod.zip"
  if [[ "$OC_MAJOR" == "3" ]]; then
    package="$ROOT_DIR/dist/oc_mcp-opencart3-v0.0.0-e2e.ocmod.zip"
  fi
  [[ -f "$package" ]] || fatal "mcp.package_install_failed" "$CURRENT_PHASE" "Expected package was not built: $package" "Check tools/build.php artifact naming." "test -f $package"

  local plugin_dir="$WORK_DIR/plugin"
  rm -rf "$plugin_dir"
  mkdir -p "$plugin_dir"
  CURRENT_COMMAND="unzip -q $package"
  unzip -q "$package" -d "$plugin_dir" 2>"$LOG_DIR/package-unzip.err" \
    || fatal "mcp.package_install_failed" "$CURRENT_PHASE" "Failed to extract MCP package." "Inspect package-unzip.err and built package integrity." "$CURRENT_COMMAND"

  if [[ "$OC_MAJOR" == "3" ]]; then
    [[ -d "$plugin_dir/upload" ]] || fatal "mcp.package_install_failed" "$CURRENT_PHASE" "OC3 package missing upload/ directory." "Inspect tools/build.php OC3 package layout." "test -d $plugin_dir/upload"
    cp -R "$plugin_dir/upload/." "$WEBROOT/"
    [[ -f "$WEBROOT/system/library/mcp/bootstrap.php" && -f "$WEBROOT/catalog/controller/extension/mcp/server.php" ]] \
      || fatal "mcp.package_install_failed" "$CURRENT_PHASE" "OC3 MCP files were not copied to expected locations." "Inspect OC3 package layout and copy step." "test expected OC3 files"
  else
    mkdir -p "$WEBROOT/extension/mcp"
    cp -R "$plugin_dir/." "$WEBROOT/extension/mcp/"
    [[ -f "$WEBROOT/extension/mcp/system/library/mcp/bootstrap.php" && -f "$WEBROOT/extension/mcp/catalog/controller/server.php" ]] \
      || fatal "mcp.package_install_failed" "$CURRENT_PHASE" "OC4 MCP files were not copied to expected locations." "Inspect OC4 package layout and copy step." "test expected OC4 files"
  fi
  docker exec "$WEB_CONTAINER" sh -c 'chmod -R a+rwX /var/www/html/system/library/mcp /var/www/html/catalog/controller/extension/mcp /var/www/html/admin/controller/extension/module /var/www/html/admin/model/extension/module /var/www/html/admin/language/en-gb/extension/module /var/www/html/admin/view/template/extension/module /var/www/html/extension/mcp 2>/dev/null || true' >/dev/null 2>&1
  append_summary "- MCP extension files installed deterministically."
}

write_setup_helper() {
  cat >"$WEBROOT/oc_mcp_e2e_setup.php" <<'PHP'
<?php
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

$major = isset($argv[1]) ? (string)$argv[1] : '3';
$bootstrap = $major === '4'
    ? __DIR__ . '/extension/mcp/system/library/mcp/bootstrap.php'
    : __DIR__ . '/system/library/mcp/bootstrap.php';

if (!is_file($bootstrap)) {
    fwrite(STDERR, "MCP bootstrap not found: " . $bootstrap . PHP_EOL);
    exit(10);
}

require_once $bootstrap;

class E2eDbResult {
    public $num_rows = 0;
    public $row = array();
    public $rows = array();
}

class E2eDb {
    private $mysqli;

    public function __construct() {
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        $this->mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, $port);
        if ($this->mysqli->connect_errno) {
            throw new RuntimeException('DB connection failed: ' . $this->mysqli->connect_error);
        }
        $this->mysqli->set_charset('utf8mb4');
    }

    public function query($sql) {
        $result = $this->mysqli->query($sql);
        if ($result === false) {
            throw new RuntimeException('DB query failed: ' . $this->mysqli->error . "\nSQL: " . $sql);
        }
        $out = new E2eDbResult();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $out->rows[] = $row;
            }
            $out->num_rows = count($out->rows);
            $out->row = $out->rows ? $out->rows[0] : array();
            $result->free();
        }
        return $out;
    }

    public function escape($value) {
        return $this->mysqli->real_escape_string((string)$value);
    }

    public function getLastId() {
        return $this->mysqli->insert_id;
    }
}

class E2eConfig {
    private $db;
    private $cache = array();

    public function __construct($db) {
        $this->db = $db;
    }

    public function get($key) {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = 0 AND `key` = '" . $this->db->escape($key) . "' ORDER BY `setting_id` DESC LIMIT 1");
        $this->cache[$key] = $query->row ? $query->row['value'] : null;
        return $this->cache[$key];
    }

    public function clear() {
        $this->cache = array();
    }
}

class E2eRegistry {
    private $items;

    public function __construct($items) {
        $this->items = $items;
    }

    public function get($key) {
        return isset($this->items[$key]) ? $this->items[$key] : null;
    }
}

function e2e_setting($db, $key, $value) {
    $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = 0 AND `code` = 'module_mcp' AND `key` = '" . $db->escape($key) . "'");
    $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = 0, `code` = 'module_mcp', `key` = '" . $db->escape($key) . "', `value` = '" . $db->escape($value) . "', `serialized` = 0");
}

function e2e_column_exists($db, $table, $column) {
    $query = $db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $db->escape($column) . "'");
    return (bool)$query->row;
}

function e2e_register_extension($db) {
    try {
        if (e2e_column_exists($db, 'extension', 'extension')) {
            $query = $db->query("SELECT `extension_id` FROM `" . DB_PREFIX . "extension` WHERE `extension` = 'mcp' AND `type` = 'module' AND `code` = 'mcp' LIMIT 1");
            if (!$query->row) {
                $db->query("INSERT INTO `" . DB_PREFIX . "extension` SET `extension` = 'mcp', `type` = 'module', `code` = 'mcp'");
            }
            return;
        }

        $query = $db->query("SELECT `extension_id` FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` = 'mcp' LIMIT 1");
        if (!$query->row) {
            $db->query("INSERT INTO `" . DB_PREFIX . "extension` SET `type` = 'module', `code` = 'mcp'");
        }
    } catch (Exception $e) {
        // Some OpenCart versions can route copied extension controllers without this row.
    }
}

$db = new E2eDb();
$config = new E2eConfig($db);
$registry = new E2eRegistry(array('db' => $db, 'config' => $config));
$repository = new \OpenCartMcp\Repository($registry);
$installer = new \OpenCartMcp\Installer($repository);
$installer->install();

e2e_register_extension($db);
e2e_setting($db, 'module_mcp_status', '1');
e2e_setting($db, 'module_mcp_display_name', 'OpenCart MCP Server');
e2e_setting($db, 'module_mcp_max_body_bytes', '1048576');
e2e_setting($db, 'module_mcp_alerts_enabled', '0');
e2e_setting($db, 'module_mcp_alert_email', '');
e2e_setting($db, 'module_mcp_advanced_tools_visible', '0');
$config->clear();

$client = $repository->createClient(array(
    'name' => 'E2E Catalog Read Client',
    'client_type' => 'automation',
    'environment' => 'e2e',
    'user_group_id' => 1,
    'capability_packs' => array('catalog_read'),
    'allowed_tools' => array(),
    'allowed_store_ids' => array(),
    'ip_allowlist' => '',
    'origin_allowlist' => '',
    'rate_limit_per_minute' => 1000,
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'created_by_user_id' => 1,
));

echo json_encode(array(
    'client_id' => $client['client_id'],
    'token' => $client['token'],
    'token_hint' => $client['token_hint'],
    'enabled' => $repository->isEnabled(),
), JSON_UNESCAPED_SLASHES) . PHP_EOL;
PHP
}

setup_mcp() {
  CURRENT_PHASE="mcp.setup"
  log "Setting up MCP extension"
  write_setup_helper
  CURRENT_COMMAND="php /var/www/html/oc_mcp_e2e_setup.php $OC_MAJOR"
  docker exec "$WEB_CONTAINER" php /var/www/html/oc_mcp_e2e_setup.php "$OC_MAJOR" \
    >"$RESP_DIR/mcp-setup.json" 2>"$LOG_DIR/mcp-setup.err" \
    || fatal "mcp.setup_failed" "$CURRENT_PHASE" "MCP setup helper failed." "Inspect mcp-setup.err, extension bootstrap path, and repository installer SQL." "$CURRENT_COMMAND"

  TOKEN="$(jq -r '.token // empty' "$RESP_DIR/mcp-setup.json")"
  [[ -n "$TOKEN" && "$TOKEN" != "null" ]] \
    || fatal "mcp.setup_failed" "$CURRENT_PHASE" "MCP setup did not return a client token." "Inspect mcp-setup.json and client creation in Repository." "$CURRENT_COMMAND"
  jq -e '.enabled == true' "$RESP_DIR/mcp-setup.json" >/dev/null \
    || fatal "mcp.setup_failed" "$CURRENT_PHASE" "MCP setup did not enable the server." "Inspect module_mcp_status setting writes." "$CURRENT_COMMAND"
  append_summary "- MCP installer/settings/client setup completed."
}

wait_for_http() {
  local url="$1"
  local code="$2"
  local phase="$3"
  local message="$4"
  local focus="$5"
  local status
  for _ in $(seq 1 60); do
    status="$(curl -sS -o "$RESP_DIR/wait-http.html" -w '%{http_code}' "$url" 2>"$LOG_DIR/wait-http.err" || true)"
    if [[ "$status" =~ ^(200|301|302|303)$ ]]; then
      return
    fi
    sleep 1
  done
  HTTP_STATUS="$status"
  RESPONSE_FILE="$RESP_DIR/wait-http.html"
  fatal "$code" "$phase" "$message" "$focus" "curl $url"
}

http_get_json() {
  local url="$1"
  local out="$2"
  local code="$3"
  local phase="$4"
  local message="$5"
  local focus="$6"
  HTTP_STATUS="$(curl -sS -o "$out" -w '%{http_code}' "$url" 2>"$out.curl.err" || true)"
  RESPONSE_FILE="$out"
  if [[ ! "$HTTP_STATUS" =~ ^2 ]]; then
    fatal "$code" "$phase" "$message HTTP status: $HTTP_STATUS." "$focus" "curl $url"
  fi
  jq empty "$out" >/dev/null 2>"$out.jq.err" || fatal "$code" "$phase" "$message Response was not valid JSON." "$focus" "jq empty $out"
}

assert_cors_preflight() {
  local out="$RESP_DIR/cors-options.json"
  local headers="$RESP_DIR/cors-options.headers"
  HTTP_STATUS="$(curl -sS -D "$headers" -o "$out" -w '%{http_code}' -X OPTIONS "$ENDPOINT_URL" 2>"$out.curl.err" || true)"
  RESPONSE_FILE="$out"
  if [[ ! "$HTTP_STATUS" =~ ^2 ]]; then
    fatal "mcp.cors_failed" "$CURRENT_PHASE" "CORS preflight failed. HTTP status: $HTTP_STATUS." "Inspect catalog server OPTIONS handling." "curl -X OPTIONS $ENDPOINT_URL"
  fi
  grep -qi '^Access-Control-Allow-Origin:' "$headers" \
    || fatal "mcp.cors_failed" "$CURRENT_PHASE" "CORS preflight missing Access-Control-Allow-Origin." "Inspect catalog server OPTIONS headers." "grep Access-Control-Allow-Origin $headers"
  grep -qi '^Access-Control-Allow-Methods:.*POST' "$headers" \
    || fatal "mcp.cors_failed" "$CURRENT_PHASE" "CORS preflight missing POST in Access-Control-Allow-Methods." "Inspect catalog server OPTIONS headers." "grep Access-Control-Allow-Methods $headers"
  grep -qi '^Access-Control-Allow-Headers:.*Authorization' "$headers" \
    || fatal "mcp.cors_failed" "$CURRENT_PHASE" "CORS preflight missing Authorization in Access-Control-Allow-Headers." "Inspect catalog server OPTIONS headers." "grep Access-Control-Allow-Headers $headers"
}

post_json() {
  local url="$1"
  local payload="$2"
  local out="$3"
  local auth_mode="${4:-token}"
  local curl_args=(-sS -o "$out" -w '%{http_code}' -H 'Content-Type: application/json')
  if [[ "$auth_mode" == "token" ]]; then
    curl_args+=(-H "Authorization: Bearer $TOKEN")
  elif [[ "$auth_mode" == "invalid" ]]; then
    curl_args+=(-H "Authorization: Bearer ocmcp_invalid")
  fi
  HTTP_STATUS="$(curl "${curl_args[@]}" -X POST --data "$payload" "$url" 2>"$out.curl.err" || true)"
  RESPONSE_FILE="$out"
  if [[ ! "$HTTP_STATUS" =~ ^2 ]]; then
    return 1
  fi
  jq empty "$out" >/dev/null 2>"$out.jq.err"
}

assert_json() {
  local file="$1"
  local filter="$2"
  local code="$3"
  local phase="$4"
  local message="$5"
  local focus="$6"
  RESPONSE_FILE="$file"
  JSONRPC_ERROR="$(jq -r '.error.data.code // .error.message // empty' "$file" 2>/dev/null || true)"
  jq -e "$filter" "$file" >/dev/null 2>"$file.assert.err" \
    || fatal "$code" "$phase" "$message" "$focus" "jq -e $filter $file"
}

run_mcp_tests() {
  CURRENT_PHASE="mcp.health"
  log "Running MCP health/auth/protocol tests"
  http_get_json "$HEALTH_URL" "$RESP_DIR/health.json" "mcp.health_failed" "$CURRENT_PHASE" "MCP health endpoint failed." "Check route registration, module_mcp_status, and extension file placement."
  assert_json "$RESP_DIR/health.json" '.enabled == true and .server == "OpenCart MCP Server"' "mcp.health_failed" "$CURRENT_PHASE" "MCP health response was not enabled." "Check setup helper settings writes."
  assert_cors_preflight

  CURRENT_PHASE="mcp.auth"
  local ping='{"jsonrpc":"2.0","id":10,"method":"ping","params":{}}'
  post_json "${ENDPOINT_URL}&token=${TOKEN}" "$ping" "$RESP_DIR/query-token-rejected.json" "none" \
    || fatal "mcp.auth_failed" "$CURRENT_PHASE" "Query-token rejection request failed at HTTP/JSON level." "Inspect endpoint routing and protocol error serialization." "curl query-token rejection"
  assert_json "$RESP_DIR/query-token-rejected.json" '.error.data.code == "TOKEN_IN_QUERY_REJECTED"' "mcp.auth_failed" "$CURRENT_PHASE" "Query-string token was not rejected with TOKEN_IN_QUERY_REJECTED." "Inspect ProtocolServer::rejectQueryTokens."

  post_json "$ENDPOINT_URL" "$ping" "$RESP_DIR/missing-auth.json" "none" \
    || fatal "mcp.auth_failed" "$CURRENT_PHASE" "Missing-auth request failed at HTTP/JSON level." "Inspect endpoint routing and auth error serialization." "curl missing auth"
  assert_json "$RESP_DIR/missing-auth.json" '.error.data.code == "AUTHENTICATION_FAILED"' "mcp.auth_failed" "$CURRENT_PHASE" "Missing bearer token was not rejected with AUTHENTICATION_FAILED." "Inspect ProtocolServer bearer auth handling."

  post_json "$ENDPOINT_URL" "$ping" "$RESP_DIR/invalid-auth.json" "invalid" \
    || fatal "mcp.auth_failed" "$CURRENT_PHASE" "Invalid-auth request failed at HTTP/JSON level." "Inspect endpoint routing and token lookup." "curl invalid auth"
  assert_json "$RESP_DIR/invalid-auth.json" '.error.data.code == "AUTHENTICATION_FAILED"' "mcp.auth_failed" "$CURRENT_PHASE" "Invalid bearer token was not rejected with AUTHENTICATION_FAILED." "Inspect token hashing secret and Repository::findClientByToken."

  CURRENT_PHASE="mcp.initialize"
  local initialize='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"oc-mcp-e2e","version":"1.0.0"}}}'
  post_json "$ENDPOINT_URL" "$initialize" "$RESP_DIR/initialize.json" "token" \
    || fatal "mcp.initialize_failed" "$CURRENT_PHASE" "Initialize request failed at HTTP/JSON level." "Inspect endpoint routing, auth, and ProtocolServer::initialize." "curl initialize"
  assert_json "$RESP_DIR/initialize.json" '.result.serverInfo.name == "opencart-mcp-server"' "mcp.initialize_failed" "$CURRENT_PHASE" "Initialize response did not identify opencart-mcp-server." "Inspect ProtocolServer::initialize and response JSON."

  CURRENT_PHASE="mcp.tools_list"
  local tools_list='{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
  post_json "$ENDPOINT_URL" "$tools_list" "$RESP_DIR/tools-list.json" "token" \
    || fatal "mcp.tools_list_failed" "$CURRENT_PHASE" "tools/list request failed at HTTP/JSON level." "Inspect client scopes/capability packs and ToolRegistry." "curl tools/list"
  assert_json "$RESP_DIR/tools-list.json" '.result.tools | any(.name == "catalog.store.get") and any(.name == "catalog.product.search")' "mcp.tools_list_failed" "$CURRENT_PHASE" "tools/list did not include expected catalog read tools." "Inspect ToolRegistry capability filtering and client creation."

  CURRENT_PHASE="mcp.tool_call"
  local store_get='{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"catalog.store.get","arguments":{}}}'
  post_json "$ENDPOINT_URL" "$store_get" "$RESP_DIR/store-get.json" "token" \
    || fatal "mcp.tool_call_failed" "$CURRENT_PHASE" "catalog.store.get request failed at HTTP/JSON level." "Inspect ToolExecutor::storeGet and config access." "curl catalog.store.get"
  assert_json "$RESP_DIR/store-get.json" '.result.content[0].json.server_version != null' "mcp.tool_call_failed" "$CURRENT_PHASE" "catalog.store.get did not return expected JSON content." "Inspect ToolExecutor::storeGet and MCP content wrapping."

  local product_search='{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"catalog.product.search","arguments":{"query":"MacBook","store_id":0,"language_id":1,"limit":5,"page":1}}}'
  post_json "$ENDPOINT_URL" "$product_search" "$RESP_DIR/product-search.json" "token" \
    || fatal "mcp.tool_call_failed" "$CURRENT_PHASE" "catalog.product.search request failed at HTTP/JSON level." "Inspect ToolExecutor::catalogProductSearch and default catalog data." "curl catalog.product.search"
  assert_json "$RESP_DIR/product-search.json" '(.result.content[0].json.products | length) >= 1' "mcp.tool_call_failed" "$CURRENT_PHASE" "catalog.product.search did not find sample MacBook catalog data." "Inspect default OpenCart sample data, visibility filters, and product search SQL."

  CURRENT_PHASE="mcp.audit"
  local count
  count="$(docker exec -e MYSQL_PWD="$DB_PASSWORD" "$DB_CONTAINER" mariadb -u"$DB_USERNAME" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM ${DB_PREFIX}mcp_audit_log" 2>"$LOG_DIR/audit-count.err" || true)"
  [[ "$count" =~ ^[0-9]+$ && "$count" -ge 7 ]] \
    || fatal "mcp.audit_failed" "$CURRENT_PHASE" "MCP audit table did not contain expected request rows. Count: ${count:-empty}." "Inspect Repository::audit, mcp_audit_log schema, and request execution path." "SELECT COUNT(*) FROM ${DB_PREFIX}mcp_audit_log"
  collect_diagnostics
  append_summary "- MCP health, auth, protocol, tool, and audit checks passed."
}

write_success() {
  cat >"$ARTIFACT_DIR/result.json" <<JSON
{
  "status": "passed",
  "opencart_major": "$OC_MAJOR",
  "opencart_version": "$OC_VERSION",
  "base_url": "$BASE_URL",
  "artifact_dir": "$ARTIFACT_DIR"
}
JSON
  append_summary ""
  append_summary "## Result"
  append_summary "Passed."
}

e2e_main() {
  OC_MAJOR="$1"
  local default_version="$2"
  init_paths "$default_version"
  trap 'on_err "$?" "$LINENO" "$BASH_COMMAND"' ERR
  trap cleanup EXIT

  log "Starting OpenCart $OC_MAJOR E2E for version $OC_VERSION"
  preflight
  download_opencart
  build_php_image
  start_database
  start_web
  install_opencart
  build_extension_package
  install_extension_package
  setup_mcp
  run_mcp_tests
  write_success
  log "OpenCart $OC_MAJOR E2E passed. Artifacts: $ARTIFACT_DIR"
}
