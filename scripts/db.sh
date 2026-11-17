#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKUP_DIR="$PROJECT_ROOT/backups/mysql"
LOCAL_BACKUP_DIR="$BACKUP_DIR/local"
PRODUCTION_BACKUP_DIR="$BACKUP_DIR/production"
PRODUCTION_ENV="$PROJECT_ROOT/.env.prod"

if [[ -t 1 ]]; then
  RED=$'\033[1;31m'
  GREEN=$'\033[1;32m'
  RESET=$'\033[0m'
else
  RED=''
  GREEN=''
  RESET=''
fi

cd "$PROJECT_ROOT"

confirm() {
  local prompt="$1"
  local answer

  read -r -p "$prompt [y/N] " answer
  [[ "$answer" == "y" || "$answer" == "Y" || "$answer" == "yes" || "$answer" == "YES" ]]
}

success() {
  printf '%sSUKCES:%s %s\n' "$GREEN" "$RESET" "$1"
}

load_production_config() {
  local values
  local -a config

  if [[ ! -f "$PRODUCTION_ENV" ]]; then
    echo "Production settings file not found: $PRODUCTION_ENV" >&2
    return 1
  fi

  if ! values="$(docker compose exec -T app php -r '
    require "/var/www/html/vendor/autoload.php";
    $values = Dotenv\Dotenv::parse(file_get_contents("/var/www/html/.env.prod"));
    foreach (["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"] as $key) {
        if (!array_key_exists($key, $values)) {
            fwrite(STDERR, "Missing $key in .env.prod\n");
            exit(1);
        }
        echo base64_encode($values[$key]), "\n";
    }
  ')"; then
    echo "Could not read production DB settings. Ensure the app container and Composer dependencies are available." >&2
    return 1
  fi

  mapfile -t config <<< "$values"
  if [[ "${#config[@]}" -ne 5 ]]; then
    echo "Could not parse production DB settings from .env.prod." >&2
    return 1
  fi

  PROD_DB_HOST="$(printf '%s' "${config[0]}" | base64 --decode)"
  PROD_DB_PORT="$(printf '%s' "${config[1]}" | base64 --decode)"
  PROD_DB_NAME="$(printf '%s' "${config[2]}" | base64 --decode)"
  PROD_DB_USER="$(printf '%s' "${config[3]}" | base64 --decode)"
  PROD_DB_PASSWORD="$(printf '%s' "${config[4]}" | base64 --decode)"

  if [[ -z "$PROD_DB_HOST" || -z "$PROD_DB_PORT" || -z "$PROD_DB_NAME" || -z "$PROD_DB_USER" || -z "$PROD_DB_PASSWORD" ]] ||
     [[ "$PROD_DB_HOST" == "CHANGE_ME" || "$PROD_DB_NAME" == "CHANGE_ME" || "$PROD_DB_USER" == "CHANGE_ME" || "$PROD_DB_PASSWORD" == "CHANGE_ME" ]]; then
    echo "Production DB settings in .env.prod are incomplete or still contain CHANGE_ME." >&2
    return 1
  fi
}

run_local_mysql() {
  docker compose exec -T db sh -c '
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
    exec mysql --user=root --database="$MYSQL_DATABASE" "$@"
  ' sh "$@"
}

run_production_mysql() {
  MYSQL_PWD="$PROD_DB_PASSWORD" docker compose exec -T -e MYSQL_PWD db \
    mysql --protocol=TCP --host="$PROD_DB_HOST" --port="$PROD_DB_PORT" \
    --user="$PROD_DB_USER" --database="$PROD_DB_NAME" "$@"
}

run_local_dump() {
  docker compose exec -T db sh -c '
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
    exec mysqldump --user=root --single-transaction --routines --triggers --events "$MYSQL_DATABASE"
  '
}

run_production_dump() {
  MYSQL_PWD="$PROD_DB_PASSWORD" docker compose exec -T -e MYSQL_PWD db \
    mysqldump --protocol=TCP --host="$PROD_DB_HOST" --port="$PROD_DB_PORT" \
    --user="$PROD_DB_USER" --single-transaction --routines --triggers --events "$PROD_DB_NAME"
}

clear_database() {
  local scope="$1"
  local runner=run_local_mysql
  local drop_statements

  if [[ "$scope" == "production" ]]; then
    runner=run_production_mysql
  fi

  if ! drop_statements="$("$runner" --batch --skip-column-names --execute "
    SELECT statement
    FROM (
      SELECT CONCAT(
        'DROP ', IF(TABLE_TYPE = 'VIEW', 'VIEW', 'TABLE'),
        ' IF EXISTS \`', REPLACE(TABLE_NAME, '\`', '\`\`'), '\`;'
      ) AS statement,
      IF(TABLE_TYPE = 'VIEW', 1, 2) AS drop_order
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
      UNION ALL
      SELECT CONCAT(
        'DROP ', ROUTINE_TYPE, ' IF EXISTS \`',
        REPLACE(ROUTINE_NAME, '\`', '\`\`'), '\`;'
      ), 1
      FROM information_schema.ROUTINES
      WHERE ROUTINE_SCHEMA = DATABASE()
      UNION ALL
      SELECT CONCAT(
        'DROP EVENT IF EXISTS \`', REPLACE(EVENT_NAME, '\`', '\`\`'), '\`;'
      ), 1
      FROM information_schema.EVENTS
      WHERE EVENT_SCHEMA = DATABASE()
    ) AS database_objects
    ORDER BY drop_order
  ")"; then
    echo "Could not list ${scope} database objects; restore aborted." >&2
    return 1
  fi

  if ! { printf 'SET FOREIGN_KEY_CHECKS=0;\n%s\n' "$drop_statements"; } | "$runner"; then
    echo "Could not clear ${scope} database; restore aborted." >&2
    return 1
  fi
}

backup_database() {
  local scope="$1"
  local target_dir="$LOCAL_BACKUP_DIR"
  local prefix="local"
  local temporary_file
  local output_file
  local timestamp

  if [[ "$scope" == "production" ]]; then
    load_production_config || return 1
    target_dir="$PRODUCTION_BACKUP_DIR"
    prefix="production"
  fi

  mkdir -p "$target_dir"
  timestamp="$(date +%Y%m%d_%H%M%S)"
  output_file="$target_dir/${prefix}_${timestamp}.sql"
  if [[ -e "$output_file" ]]; then
    echo "Backup already exists for this timestamp: $output_file" >&2
    return 1
  fi
  temporary_file="$(mktemp "$target_dir/.${prefix}_backup.XXXXXX")"

  if [[ "$scope" == "production" ]]; then
    if ! run_production_dump > "$temporary_file"; then
      rm -f "$temporary_file"
      echo "Production database backup failed." >&2
      return 1
    fi
  elif ! run_local_dump > "$temporary_file"; then
    rm -f "$temporary_file"
    echo "Local database backup failed." >&2
    return 1
  fi

  if [[ ! -s "$temporary_file" ]]; then
    rm -f "$temporary_file"
    echo "Backup failed: mysqldump returned an empty file." >&2
    return 1
  fi

  chmod 600 "$temporary_file"
  if ! mv -- "$temporary_file" "$output_file"; then
    rm -f "$temporary_file"
    echo "Could not save backup: $output_file" >&2
    return 1
  fi
  if [[ "$scope" == "production" ]]; then
    success "Kopia produkcyjnej bazy została utworzona: $output_file"
  else
    success "Kopia lokalnej bazy została utworzona: $output_file"
  fi
}

list_backups() {
  local scope="$1"

  if [[ "$scope" == "production" ]]; then
    find "$PRODUCTION_BACKUP_DIR" -maxdepth 1 -type f -name 'production_*.sql' -print 2>/dev/null | sort
  else
    {
      find "$LOCAL_BACKUP_DIR" -maxdepth 1 -type f -name 'local_*.sql' -print 2>/dev/null
      find "$BACKUP_DIR" -maxdepth 1 -type f -name 'backup_*.sql' -print 2>/dev/null
    } | sort
  fi
}

select_backup() {
  local scope="$1"
  local -a files
  local choice
  local index

  mapfile -t files < <(list_backups "$scope")
  if [[ "${#files[@]}" -eq 0 ]]; then
    echo "No ${scope} SQL backups found under $BACKUP_DIR." >&2
    return 1
  fi

  printf '\nAvailable %s backups:\n' "$scope"
  for index in "${!files[@]}"; do
    printf '  %d) %s\n' "$((index + 1))" "${files[$index]#"$PROJECT_ROOT"/}"
  done
  read -r -p "Select backup number: " choice

  if [[ ! "$choice" =~ ^[0-9]+$ ]] || (( choice < 1 || choice > ${#files[@]} )); then
    echo "Invalid backup selection." >&2
    return 1
  fi
  SELECTED_BACKUP="${files[$((choice - 1))]}"
}

restore_database() {
  local scope="$1"
  local confirmation

  if [[ "$scope" == "production" ]]; then
    load_production_config || return 1
  fi
  if [[ -z "${SELECTED_BACKUP:-}" ]]; then
    select_backup "$scope" || return 1
  elif [[ ! -f "$SELECTED_BACKUP" ]]; then
    echo "Backup file not found: $SELECTED_BACKUP" >&2
    return 1
  fi

  if [[ "$scope" == "production" ]]; then
    confirmation="RESTORE PRODUCTION"
    printf '\n%sWARNING: this will permanently replace every table in the production database.%s\n' "$RED" "$RESET" >&2
  else
    confirmation="RESTORE LOCAL"
    printf '\nWARNING: this will permanently replace every table in the local database.\n' >&2
  fi
  read -r -p "Type '$confirmation' to continue: " reply
  if [[ "$reply" != "$confirmation" ]]; then
    echo "Restore cancelled."
    return 1
  fi

  if ! clear_database "$scope"; then
    return 1
  fi

  if [[ "$scope" == "production" ]]; then
    if ! run_production_mysql < "$SELECTED_BACKUP"; then
      echo "Production restore failed while importing the backup." >&2
      return 1
    fi
  else
    if ! run_local_mysql < "$SELECTED_BACKUP"; then
      echo "Local restore failed while importing the backup." >&2
      return 1
    fi
  fi

  if [[ "$scope" == "production" ]]; then
    success "Produkcyjna baza została odtworzona z kopii: $SELECTED_BACKUP"
  else
    success "Lokalna baza została odtworzona z kopii: $SELECTED_BACKUP"
  fi
}

run_action() {
  local action="$1"
  local scope="$2"
  local label="${scope^}"

  if [[ "$scope" == "production" ]]; then
    label="${RED}${label} PRODUCTION${RESET}"
  fi

  if ! confirm "Confirm ${action} for ${label}?"; then
    echo "Cancelled."
    return 0
  fi

  if [[ "$action" == "backup" ]]; then
    backup_database "$scope"
  else
    restore_database "$scope"
  fi
}

show_menu() {
  printf '\nDatabase backup and restore\n'
  printf '  1) Backup local database\n'
  printf '  2) Restore local database\n'
  printf '  3) %sBackup production database%s\n' "$RED" "$RESET"
  printf '  4) %sRestore production database%s\n' "$RED" "$RESET"
  printf '  5) Exit\n'
}

if [[ $# -gt 0 ]]; then
  case "$1" in
    backup) run_action backup local ;;
    restore)
      if [[ $# -ne 2 || ! -f "$2" ]]; then
        echo "Usage: $0 restore <path-to-sql-file>" >&2
        exit 1
      fi
      confirm "Confirm full local database replacement using '$2'?" || {
        echo "Cancelled."
        exit 0
      }
      SELECTED_BACKUP="$2"
      restore_database local
      ;;
    backup-production) run_action backup production ;;
    restore-production) run_action restore production ;;
    *)
      echo "Usage: $0 [backup|restore <path-to-sql-file>|backup-production|restore-production]" >&2
      exit 1
      ;;
  esac
  exit $?
fi

while true; do
  show_menu
  read -r -p "Choose an option: " choice || exit 0

  case "$choice" in
    1) run_action backup local ;;
    2) run_action restore local ;;
    3) run_action backup production ;;
    4) run_action restore production ;;
    5) exit 0 ;;
    *) echo "Invalid selection." >&2 ;;
  esac
done
