# commands-log

## Ubuntu 22 (clean install) - Docker environment bootstrap

```bash
sudo apt update && sudo apt install -y ca-certificates curl gnupg
```
Przygotowanie narzędzi i certyfikatów wymaganych do repozytorium Dockera.

```bash
sudo install -m 0755 -d /etc/apt/keyrings && \
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg && \
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```
Dodanie klucza GPG repozytorium Dockera.

```bash
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo $VERSION_CODENAME) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```
Dodanie oficjalnego repozytorium Dockera do APT.

```bash
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```
Instalacja Docker Engine, Buildx i Docker Compose na Ubuntu 22.

```bash
sudo usermod -aG docker $USER
```
Dodanie użytkownika do grupy `docker` (po tej komendzie wyloguj i zaloguj się ponownie).

```bash
newgrp docker
```
Odświeżenie grup w bieżącej sesji bez restartu systemu.

```bash
docker --version && docker compose version
```
Weryfikacja instalacji Dockera i Compose.

```bash
docker run --rm hello-world
```
Szybki test działania Dockera.

## Ubuntu 24 (clean install) - Docker environment bootstrap

```bash
sudo apt update && sudo apt install -y ca-certificates curl gnupg
```
Przygotowanie narzędzi i certyfikatów wymaganych do repozytorium Dockera.

```bash
sudo install -m 0755 -d /etc/apt/keyrings && \
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg && \
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```
Dodanie klucza GPG repozytorium Dockera.

```bash
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo $VERSION_CODENAME) stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```
Dodanie oficjalnego repozytorium Dockera do APT.

```bash
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```
Instalacja Docker Engine, Buildx i Docker Compose.

```bash
sudo usermod -aG docker $USER
```
Dodanie użytkownika do grupy `docker` (po tej komendzie wyloguj i zaloguj się ponownie).

```bash
newgrp docker
```
Odświeżenie grup w bieżącej sesji bez restartu systemu.

```bash
docker --version && docker compose version
```
Weryfikacja instalacji Dockera i Compose.

```bash
docker run --rm hello-world
```
Szybki test działania Dockera.

## Project setup - 3dprint (Docker)

```bash
cd /media/ps/PS_M2/GitHub/3dprint
```
Przejście do katalogu projektu.

```bash
cp .env.docker.example .env
```
Wstępny `.env` pod uruchomienie aplikacji z kontenerami.

```bash
chmod +x scripts/db.sh
```
Nadanie praw wykonywania skryptowi backup/restore bazy.

```bash
docker compose up -d --build
```
Budowa i uruchomienie kontenerów (`app`, `web`, `db`) w tle.

```bash
docker compose exec app sh -lc 'composer create-project laravel/laravel /tmp/laravel-app && cp -a /tmp/laravel-app/. /var/www/html/ && rm -rf /tmp/laravel-app'
```
Inicjalizacja Laravel w katalogu tymczasowym i skopiowanie do istniejącego katalogu projektu (który nie jest pusty).

```bash
docker compose exec app php artisan key:generate
```
Wygenerowanie klucza aplikacji Laravel.

```bash
docker compose exec app php artisan migrate
```
Utworzenie podstawowych tabel w bazie MySQL.

```bash
echo "127.0.0.1 3dprint.local" | sudo tee -a /etc/hosts
```
Dodanie lokalnej domeny developmentowej.

```bash
sudo chown -R $USER:$USER /media/ps/PS_M2/GitHub/3dprint
```
Naprawa uprawnień do plików projektu po operacjach wykonywanych z kontenera/root.

## Next implementation phase - admin + domain model

### Fix: missing PHP intl extension for Filament

```bash
docker compose exec app php -m | grep -i intl
```
Sprawdzenie, czy w kontenerze aplikacji jest włączone rozszerzenie `intl`.

```bash
docker compose build app
```
Przebudowanie obrazu `app` po dodaniu wymaganych bibliotek PHP (w szczególności `libicu-dev` i `intl`).

```bash
docker compose up -d --no-deps app
```
Uruchomienie odświeżonego kontenera aplikacyjnego po rebuildzie.

```bash
docker compose exec app composer require filament/filament -W
```
Instalacja panelu admin Filament po poprawnym zbudowaniu kontenera z rozszerzeniem `intl`.

```bash
docker compose exec app php artisan filament:install --panels
```
Inicjalizacja panelu Filament.

```bash
docker compose exec app php artisan make:filament-user
```
Utworzenie pierwszego konta administratora.

```bash
docker compose exec app php artisan make:model Project -m
```
Model + migracja tabeli projektów.

```bash
docker compose exec app php artisan make:model ProjectImage -m
```
Model + migracja zdjęć projektów.

```bash
docker compose exec app php artisan make:model ProjectFile -m
```
Model + migracja plików do pobrania.

```bash
docker compose exec app php artisan migrate
```
Wykonanie migracji po przygotowaniu schematu.

## Fix: temporary directory / Filament 500 on admin

```bash
mkdir -p storage/tmp storage/framework/{cache,data,sessions,views}
```
Utworzenie katalogów tymczasowych i cache dla aplikacji.

```bash
docker compose exec app sh -lc 'printf "\nTMPDIR=/var/www/html/storage/tmp\n" >> .env'
```
Wymuszenie stabilnego katalogu tymczasowego wewnątrz kontenera.

```bash
docker compose exec app php artisan config:clear && docker compose exec app php artisan cache:clear && docker compose exec app php artisan view:clear
```
Wyczyszczenie cache konfiguracji, aplikacji i widoków.

```bash
docker compose down && docker compose up -d
```
Pełny restart kontenerów po zmianach konfiguracji.

```bash
curl -I http://3dprint.local:8080/admin
```
Sprawdzenie, czy panel administracyjny odpowiada po restarcie.

## Fix: local DNS / hosts for 3dprint.local

```bash
grep 3dprint.local /etc/hosts
```
Sprawdzenie, czy lokalna domena jest wpisana do hosts.

```bash
echo "127.0.0.1 3dprint.local" | sudo tee -a /etc/hosts
```
Dodanie lokalnej domeny do systemowego hosts.

```bash
curl -I http://3dprint.local:8080/admin
```
Sprawdzenie panelu przez nazwę hosta po aktualizacji hosts.

## Fix: SQLite migration state / switch to Docker MySQL

```bash
docker compose exec app php artisan migrate:fresh --seed
```
Pełne odtworzenie schematu i danych testowych na bazie MySQL z Dockera.

```bash
docker compose exec app php artisan migrate:status
```
Sprawdzenie, czy migracje są w oczekiwanym stanie po odtworzeniu bazy.

```bash
curl -I http://127.0.0.1:8080/
```
Weryfikacja publicznej strony po pełnym odświeżeniu bazy.

## Final runbook: Docker + .env

```bash
cp .env.docker.example .env
```
Przywrócenie dockerowego `.env` zamiast lokalnego sqlite/default localhost.

```bash
mkdir -p storage/tmp
```
Utworzenie katalogu tymczasowego dla PHP/Laravel.

```bash
docker compose down && docker compose up -d --build
```
Start całego stacku po ustawieniu poprawnego `.env`.

```bash
docker compose exec app php artisan migrate:fresh --seed
```
Odtworzenie bazy MySQL z Dockera.

```bash
docker compose exec app php artisan optimize:clear
```
Wyczyszczenie cache po zmianie `.env`.

```bash
curl -I http://127.0.0.1:8080/
curl -I http://127.0.0.1:8080/admin
```
Sprawdzenie, czy frontend i panel admin odpowiadają.

## Fix: persistent TMPDIR in Docker image

```bash
docker compose build app
```
Przebudowanie obrazu PHP z katalogiem `storage/tmp` utworzonym na stałe.

```bash
docker compose up -d
```
Uruchomienie stacku po przebudowie obrazu.

## Fix: PHP logging and temp dir in image

```bash
docker compose exec app php -i | grep -E 'log_errors|sys_temp_dir|upload_tmp_dir'
```
Weryfikacja, czy PHP używa katalogu tymczasowego i ma włączone logowanie błędów.

```bash
docker compose exec app sh -lc 'chown -R www-data:www-data storage bootstrap/cache'
```
Naprawa uprawnień katalogów zapisu po montowaniu woluminu z hosta.

## Fix: generate APP_KEY

```bash
docker compose exec app php artisan key:generate
```
Wygenerowanie brakującego klucza szyfrowania aplikacji.

```bash
docker compose exec app php artisan config:clear
```
Odświeżenie konfiguracji po wygenerowaniu klucza.

## Fix: missing login route for Filament redirect

```bash
php artisan route:list | grep login
```
Weryfikacja, czy trasa `login` jest dostępna dla przekierowania panelu.

```bash
curl -I http://127.0.0.1:8080/login
```
Sprawdzenie strony logowania przed wejściem do panelu admin.

## Fix: missing Vite manifest

```bash
npm install
```
Zainstalowanie zależności front-endowych potrzebnych do buildu assetów.

```bash
npm run build
```
Wygenerowanie `public/build/manifest.json` dla `@vite(...)`.

```bash
docker compose exec app php artisan optimize:clear
```
Odświeżenie cache po zbudowaniu assetów.

## Fix: Node version too old for Vite

```bash
node -v
```
Sprawdzenie wersji Node; Vite 8 wymaga nowszego Node niż 12.x.

```bash
nvm install 20
nvm use 20
```
Przełączenie lokalnego Node na wspieraną wersję LTS.

```bash
npm run build
```
Ponowny build assetów po aktualizacji Node.

## Fix: recreate admin user after fresh database

```bash
docker compose exec app php artisan db:seed
```
Odtworzenie użytkowników i danych testowych po `migrate:fresh`.

```bash
docker compose exec app php artisan tinker --execute="dump(\App\Models\User::query()->pluck('email')->all());"
```
Sprawdzenie, czy konto admina zostało odtworzone.

```bash
curl -I http://127.0.0.1:8080/login
```
Weryfikacja formularza logowania przed wejściem do panelu admin.

## Fix: idempotent seed data

```bash
docker compose exec app php artisan db:seed
```
Odtworzenie danych testowych bez konfliktu duplikatów po zmianie na `updateOrCreate`.

```bash
docker compose exec app php artisan migrate:fresh --seed
```
Pełny reset i ponowne zbudowanie danych testowych.

## Fix: regenerate image media after upload

```bash
docker compose exec app php artisan tinker --execute="dump(\App\Models\ProjectImage::query()->latest('id')->first()?->toArray());"
```
Sprawdzenie, czy nowe obrazy zapisują osobne `path_large` i `path_thumb`.

```bash
docker compose exec app sh -lc 'find storage/app/public -type f | grep projects/'
```
Potwierdzenie fizycznego położenia wygenerowanych plików w public storage.

## Fix: remove missing Intervention dependency usage

```bash
docker compose exec app php artisan optimize:clear
```
Odświeżenie autoload i cache po zmianie helpera mediow.

```bash
docker compose exec app php artisan tinker --execute="dump(\App\Models\ProjectImage::query()->latest('id')->first()?->toArray());"
```
Sprawdzenie, czy rekord obrazu zapisuje ścieżki po uproszczeniu uploadu.

## Project archive zip script

```bash
chmod +x zip-project.sh
```
Nadanie praw wykonywania skryptowi tworzącemu ZIP projektu.

```bash
./zip-project.sh
```
Utworzenie archiwum `3dprint_<YYYYMMDD_HHMM>.zip` w katalogu nadrzędnym projektu z pominięciem `storage`.

## Featured project image

```bash
docker compose exec app php artisan migrate
```
Dodanie kolumny `is_featured` do `project_images` przez osobną migrację ALTER TABLE.

```bash
docker compose exec app php artisan optimize:clear
```
Odświeżenie cache po zmianie relacji i widoku listy projektów.

## Mailpit + reset hasła + 2FA (Filament MFA)

```bash
docker compose up -d
```
Start środowiska z nowym kontenerem `mailpit` (SMTP `mailpit:1025`, UI `http://localhost:8025`).

```bash
docker compose exec app php artisan migrate
```
Dodanie kolumn 2FA do `users`: `app_authentication_secret`, `app_authentication_recovery_codes`, `has_email_authentication`.

```bash
docker compose exec app php artisan queue:work database --once --stop-when-empty
```
Przetworzenie maila resetu, który utknął w kolejce `database` zanim ustawiliśmy `QUEUE_CONNECTION=sync` (powiadomienia Filamenta są `ShouldQueue`).

```bash
docker compose exec app php artisan test
```
Pełny zestaw testów (8 passed) — m.in. nowy `tests/Feature/AdminAuthTest.php`: strony logowania/resetu, przekierowanie gościa, profil, flaga e-mail 2FA w bazie, szyfrowanie sekretu TOTP. Przy okazji naprawiono duplikat kolumny `is_featured` w migracjach (guard `Schema::hasColumn`), który wywalał testy na świeżej bazie sqlite.

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/login
curl -s http://localhost:8025/api/v1/messages
```
Smoke test: strony auth Filamenta (200) i odbiór maila „Reset your password" w Mailpit.

```bash
docker compose exec app sh -c 'php artisan view:clear && chown -R www-data:www-data storage bootstrap/cache'
```
Naprawa `touch(): Utime failed: Operation not permitted` na `/admin/profile` — skompilowane widoki Blade utworzone jako root (przez `exec` bez `-u www-data`) blokowały PHP-FPM. Na przyszłość: `docker compose exec -u www-data app php artisan ...`.
