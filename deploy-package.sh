#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARENT_DIR="$(dirname "$PROJECT_ROOT")"
ARCHIVE_PATH="$PARENT_DIR/3dprint_deploy_$(date +%Y%m%d_%H%M%S).zip"
IMAGE=3dprint-deploy:local
FTP_SERVER="${FTP_SERVER:-ftp.piotrsta.webd.pl}"
FTP_USER="${FTP_USER:-piotrsta}"
FTP_PASSWORD="${FTP_PASSWORD:-}"
FTP_PORT="${FTP_PORT:-21}"
FTP_REMOTE_DIR="${FTP_REMOTE_DIR:-/public_html/3dprint.4fm.pl}"

if ! command -v docker > /dev/null; then
  echo "Missing required tool: docker" >&2
  exit 1
fi
docker info > /dev/null

if [[ ! -f "$PROJECT_ROOT/.env.prod" ]]; then
  echo "Create .env.prod from .env.prod.example and fill in production settings." >&2
  exit 1
fi
if [[ -e "$ARCHIVE_PATH" ]]; then
  echo "Archive already exists: $ARCHIVE_PATH" >&2
  exit 1
fi

# Only the Dockerfile enters the build context; production secrets stay out of image layers.
docker build --tag "$IMAGE" - < "$PROJECT_ROOT/docker/php/Dockerfile"

umask 077
STAGING_DIR="$(mktemp -d "$PARENT_DIR/.3dprint-deploy.XXXXXXXX")"
trap 'rm -r -- "$STAGING_DIR"' EXIT

docker run --rm \
  --user "$(id -u):$(id -g)" \
  --env HOME=/tmp \
  --env TMPDIR=/tmp \
  --mount "type=bind,source=$PROJECT_ROOT,target=/source,readonly" \
  --mount "type=bind,source=$STAGING_DIR,target=/build" \
  --workdir /build \
  "$IMAGE" bash /source/scripts/build-deploy.sh

mv "$STAGING_DIR/release.zip" "$ARCHIVE_PATH"
echo "WARNING: this archive contains production secrets (.env). Store and transfer it securely."
echo "Prepared deployment archive: $ARCHIVE_PATH"

upload_to_ftp() {
  local upload_dir="$STAGING_DIR/upload"
  local upload_files="$upload_dir/files"
  local batch_file="$upload_dir/lftp.batch"

  if [[ ! "$FTP_SERVER" =~ ^[A-Za-z0-9.-]+$ ||
        ! "$FTP_USER" =~ ^[A-Za-z0-9_.@-]+$ ||
        ! "$FTP_PORT" =~ ^[0-9]+$ ||
        ! "$FTP_REMOTE_DIR" =~ ^/[A-Za-z0-9._/-]+$ ||
        "$FTP_REMOTE_DIR" == *"/../"* ||
        "$FTP_REMOTE_DIR" == */.. ]]; then
    echo "Invalid FTP server, user, port or remote directory configuration." >&2
    return 1
  fi
  if [[ "$FTP_PASSWORD" == *$'\n'* || "$FTP_PASSWORD" == *$'\r'* ]]; then
    echo "FTP password must not contain newline characters." >&2
    return 1
  fi

  if [[ -z "$FTP_PASSWORD" ]]; then
    read -r -s -p "FTP password for $FTP_USER@$FTP_SERVER: " FTP_PASSWORD
    printf '\n'
  fi
  if [[ -z "$FTP_PASSWORD" ]]; then
    echo "FTP password cannot be empty." >&2
    return 1
  fi

  printf '\nWARNING: plain FTP sends the password and files without encryption.\n' >&2
  read -r -p "Type 'PLAIN FTP' to confirm this unencrypted transfer: " ftp_confirmation
  if [[ "$ftp_confirmation" != "PLAIN FTP" ]]; then
    echo "FTP upload cancelled."
    return 1
  fi

  mkdir -m 700 "$upload_dir"
  mkdir "$upload_files"
  docker run --rm \
    --user "$(id -u):$(id -g)" \
    --mount "type=bind,source=$ARCHIVE_PATH,target=/tmp/release.zip,readonly" \
    --mount "type=bind,source=$upload_files,target=/tmp/files" \
    "$IMAGE" sh -c 'unzip -q /tmp/release.zip -d /tmp/files'

  bash "$PROJECT_ROOT/scripts/prepare-deploy-upload.sh" "$upload_files"

  quote_lftp() {
    local value="${1//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//\$/\\\$}"
    value="${value//\`/\\\`}"
    printf '"%s"' "$value"
  }

  {
    printf 'set cmd:fail-exit yes\n'
    printf 'set ftp:ssl-allow no\n'
    printf 'set ftp:ssl-force no\n'
    printf 'set ftp:ssl-protect-data no\n'
    printf 'set ftp:passive-mode yes\n'
    printf 'set mirror:no-empty-dirs no\n'
    printf 'open -u %s,%s ftp://%s:%s\n' \
      "$(quote_lftp "$FTP_USER")" \
      "$(quote_lftp "$FTP_PASSWORD")" \
      "$FTP_SERVER" "$FTP_PORT"
    printf 'mirror --reverse --verbose --no-perms --no-symlinks %s %s\n' \
      "$(quote_lftp /upload/files)" \
      "$(quote_lftp "$FTP_REMOTE_DIR")"
    printf 'bye\n'
  } > "$batch_file"
  chmod 600 "$batch_file"
  unset FTP_PASSWORD

  echo "Uploading application files over unencrypted FTP to $FTP_SERVER:$FTP_PORT$FTP_REMOTE_DIR ..."
  if ! docker run --rm \
       --user "$(id -u):$(id -g)" \
       --mount "type=bind,source=$upload_files,target=/upload/files,readonly" \
       --mount "type=bind,source=$batch_file,target=/upload/lftp.batch,readonly" \
       "$IMAGE" lftp -f /upload/lftp.batch; then
    echo "FTP upload failed. The server may have received some files; existing files were not deleted." >&2
    return 1
  fi

  echo "FTP upload completed. Remote .env and storage data were preserved; runtime directories were prepared."
  echo "ACTION REQUIRED: Update $FTP_REMOTE_DIR/.env from .env.prod using cPanel over HTTPS."
  echo "Preserve the existing production APP_KEY and clear Laravel's configuration cache after changes."
  echo "Ensure the PHP user can write to storage/ and bootstrap/cache/."
}

if [[ -t 0 && -t 1 ]]; then
  read -r -p "Upload the built files over FTP now? [y/N] " upload_choice
  if [[ "$upload_choice" == "y" || "$upload_choice" == "Y" ||
        "$upload_choice" == "yes" || "$upload_choice" == "YES" ]]; then
    upload_to_ftp
  else
    echo "FTP upload skipped."
  fi
else
  echo "FTP upload skipped (no interactive terminal)."
fi
