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

## Project packaging for manual FTP deployment

```bash
cd /media/ps/PS_M2/GitHub/3dprint
./zip-project.sh
```
Tworzy archiwum z datą w nazwie (np. `3dprint_20260922_2307.zip`) w katalogu nadrzędnym, z wykluczeniem zależności lokalnych, plików środowiskowych i katalogów roboczych.

## Deployment package for FTP upload

```bash
cd /media/ps/PS_M2/GitHub/3dprint
./deploy-package.sh
```
Tworzy osobne archiwum deploymentowe z datą w nazwie (np. `3dprint_deploy_20260922_2310.zip`) do ręcznego wrzucenia na hosting, bez plików lokalnych, magazynu i danych środowiskowych.

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

## Dokumentacja przekazaniowa dla agentów AI (docs/ai-handover)

```bash
mkdir -p docs/ai-handover
```
Nowy katalog na kompletną dokumentację projektu dla agentów AI (podstawa do budowy analogicznego portfolio fotograficznego).

```bash
chmod +x docs/ai-handover/generate-code-appendix.sh && ./docs/ai-handover/generate-code-appendix.sh
```
Generuje `docs/ai-handover/10-kod-zrodlowy.md` — snapshot 55 kluczowych plików źródłowych wprost z repo (uruchamiać ponownie po istotnych zmianach kodu). Przy okazji `deploy-package.sh` wyklucza teraz `docs/` z archiwum deploymentowego.

## Linux Mint - Docker ze snapa (bootstrap bez sudo)

Stan zastany: `snap list docker` → 29.8.0 (compose v5.5.1), brak grupy `docker`, socket `root:root`, interfejs `removable-media` niepodłączony.

```bash
sudo addgroup --system docker && sudo usermod -aG docker $USER
```
Snapowy Docker nie tworzy grupy `docker` sam — trzeba ją założyć ręcznie i dodać do niej użytkownika.

```bash
sudo snap disable docker && sudo snap enable docker
```
Restart snapa, żeby dockerd przejął grupę i zmienił właściciela `/var/run/docker.sock` na `root:docker`.

```bash
newgrp docker
```
Odświeżenie grup w bieżącej sesji (docelowo wyloguj/zaloguj się ponownie).

```bash
sudo snap connect docker:removable-media
```
Projekt leży w `/media/ps/PS_M2/...`, a snap domyślnie podłącza tylko interfejs `home` — bez `removable-media` bind-mount `./:/var/www/html` z `docker-compose.yml` kończy się „permission denied".

```bash
snap connections docker | grep -E 'removable-media|home' && ls -l /var/run/docker.sock && docker compose up -d
```
Weryfikacja: oba interfejsy podłączone, socket `root:docker`, kontenery startują bez `sudo`.

## Linux Mint - migracja Docker snap do Docker CE (2026-11-09)

Wybrany kierunek: oficjalne repozytorium APT Docker, kanal `stable`,
Ubuntu `noble` (baza Linux Mint 22.1). Migracja jeszcze niewykonana.

```bash
sudo -n true
command -v docker
docker compose ps -a
snap list docker
```
Kontrola przed migracja: `sudo` wymaga hasla, klient nadal wskazuje
`/snap/bin/docker`, a dostep do socketa Dockera bez `sudo` jest odrzucany.
Nie wykonano backupu, nie zatrzymano kontenerow i nie zmieniono instalacji.
Wczesniejsza sekcja bootstrapu snapa opisuje instrukcje, nie potwierdzone
wykonanie konfiguracji.

Migracje nalezy rozpoczac od potwierdzonego backupu bazy i plikow `storage/`.
Snap pozostaje zainstalowany do czasu sprawdzenia projektu na Docker CE;
nowy daemon nie przejmuje automatycznie jego wolumenow.

```bash
sudo bash ./scripts/db.sh backup
sudo docker compose down
sudo snap remove docker
```


```angular2html
sudo apt  install docker.io
sudo apt  install docker-compose
```

### Diagnoza brakujacego `docker compose` (2026-11-09)

```bash
command -v docker
docker --version
docker compose version
docker-compose --version
apt-cache policy docker-compose-v2 docker-compose-plugin
id -nG
ls -l /var/run/docker.sock
```
Potwierdzono Docker z `/usr/bin/docker` (pakiet `docker.io`) oraz stary
Compose 1.29.2 (`docker-compose`, osobne polecenie z myslnikiem).
Brakuje pluginu Compose V2; repozytorium Ubuntu udostepnia pakiet
`docker-compose-v2`. Biezaca sesja uzytkownika nie ma grupy `docker`,
a socket nalezy do `root:docker`.

Do wykonania w terminalu uzytkownika (agent nie ma dostepu do `sudo` bez hasla):

```bash
sudo apt update
sudo apt install docker-compose-v2
sudo usermod -aG docker "$USER"
```
Po wylogowaniu i ponownym zalogowaniu:

```bash
docker compose version
docker compose up -d
```
Dla instalacji `docker.io` z Ubuntu uzywamy `docker-compose-v2`, nie
`docker-compose-plugin` z oficjalnego repozytorium Docker CE.

```bash
id -nG
id -nG "$(id -un)"
getent group docker
ls -l /var/run/docker.sock
```
Kontrola bledu `permission denied` po dodaniu uzytkownika: konto jest juz
w grupie `docker`, ale biezaca sesja jeszcze jej nie ma. Socket ma poprawne
uprawnienia `root:docker`. W terminalu uruchomic `newgrp docker` lub
wylogowac sie i zalogowac ponownie; nie zmieniac uprawnien socketa.

## Produkcyjna paczka FTP i osobny .env.prod (2026-11-09)

Dodano `.env.prod.example` oraz lokalny `.env.prod` ignorowany przez Git.
Przed rzeczywistym pakowaniem trzeba uzupelnic produkcyjne DB, SMTP i APP_KEY.
`deploy-package.sh` buduje w izolacji: Composer bez dev, assety Filamenta,
npm/Vite, a nastepnie ZIP z `vendor`, `.env` i pustym storage.
Nie zmienia lokalnego `.env` ani zaleznosci. ZIP zawiera sekrety (tryb 600).

```bash
chmod 600 .env.prod
git check-ignore .env.prod
```
Ograniczenie dostepu do konfiguracji i potwierdzenie ignorowania jej przez Git.

```bash
. "$HOME/.nvm/nvm.sh"
bash -n deploy-package.sh tests/deploy-package.sh
bash tests/deploy-package.sh
```
Test integracyjny z fikcyjnymi danymi produkcyjnymi: rzeczywiste instalacje
Composer/npm, build Vite, zawartosc ZIP, brak sekretow dev i lokalnych mediow,
odrzucenie niewypelnionego szablonu, niezmiennosc lokalnego srodowiska,
uruchomienie Laravel z rozpakowanej paczki i wymagania platformy Composer.
Pierwszy przebieg wykryl brak pustego `bootstrap/cache/` w ZIP; poprawiono
wykluczenie i ponowny przebieg zakonczyl sie powodzeniem.

Po uzupelnieniu `.env.prod` polecenie do wykonania przez uzytkownika:

```bash
./deploy-package.sh
```
Prawdziwej paczki z konfiguracja serwera jeszcze nie utworzono.
Procedura wdrozenia: `docs/ai-handover/02-srodowisko-docker.md`, sekcja 7.

## Pakowanie produkcyjne w Dockerze (2026-11-09)

Usunieto zaleznosc pakowania od PHP/Composera/Node/npm/zip na hoscie.
Obraz `docker/php/Dockerfile` zawiera teraz Node 22, npm i zip.
`deploy-package.sh` sam buduje ten obraz z cache i uruchamia jednorazowy
kontener jako UID/GID hosta. Zrodla sa tylko do odczytu, sekrety nie sa
czescia kontekstu budowania obrazu, build odbywa sie w osobnym katalogu.
Nie restartuje dzialajacej aplikacji ani bazy.

```bash
chmod +x deploy-package.sh
bash -n deploy-package.sh scripts/build-deploy.sh tests/deploy-package.sh
sg docker -c 'bash tests/deploy-package.sh'
git diff --check
```
Wykonano pelny test integracyjny w Dockerze: instalacje Composer/npm,
build Vite, zawartosc ZIP i start rozpakowanej aplikacji. Wynik poprawny.
`sg docker` zastosowano tylko dlatego, ze sesja agenta ma nieodswiezone grupy.

```bash
./deploy-package.sh
```
Docelowe polecenie uzytkownika: samodzielnie przygotowuje obraz i paczke.
Jesli Node/npm maja byc dostepne rowniez w juz dzialajacym kontenerze `app`:

```bash
docker compose up -d --build --no-deps app
```
To ostatnie polecenie jest instrukcja, nie zostalo wykonane przez agenta.

## Dodatek: tryb ciemny na stronie publicznej (2026-11-16)

Dodano przełącznik motywu w nagłówku; wybór jest zapamiętywany w `localStorage`,
a przy pierwszej wizycie motyw odpowiada ustawieniom systemu.

```bash
docker compose exec app npm run build
docker compose exec -u www-data app php artisan test
curl -I http://127.0.0.1:8080/
```
Build assetów, testy aplikacji i kontrola odpowiedzi strony po wdrożeniu zmian.

## Fix: brak `intl` w uruchomionym obrazie PHP (2026-11-16)

`docker/php/Dockerfile` zawiera `libicu-dev` i kompiluje rozszerzenie `intl`;
uruchomiony kontener korzystał jednak ze starszego obrazu bez tego rozszerzenia.

```bash
docker compose build app
docker compose up -d --no-deps app
docker compose exec -T app php -m | grep -i '^intl$'
docker compose exec -T app php -r 'echo class_exists(\NumberFormatter::class) ? "NumberFormatter available\n" : "NumberFormatter missing\n";'
```
Przebudowanie i odtworzenie wyłącznie kontenera aplikacji. Potwierdzono
załadowanie `intl` oraz dostępność `NumberFormatter`.

## Menu backupu i restore lokalnej/produkcyjnej bazy (2026-11-16)

Skrypt `scripts/db.sh` udostępnia cztery operacje w menu. Używa `.env` dla
lokalnego kontenera i bezpiecznie parsuje `.env.prod` przez Dotenv; restore
zastępuje tabele i widoki, wymaga potwierdzenia oraz wpisania frazy ochronnej.
Kopie są rozdzielone na `backups/mysql/local/` i `backups/mysql/production/`.

```bash
chmod +x scripts/db.sh
bash -n scripts/db.sh
printf '5\n' | ./scripts/db.sh
docker compose exec -T app php -r 'require "vendor/autoload.php"; echo method_exists(Dotenv\Dotenv::class, "parse") ? "dotenv parser available\n" : "dotenv parser unavailable\n";'
```
Nadanie prawa wykonania, kontrola składni/menu oraz potwierdzenie dostępności
parsera Dotenv. Backupów ani restore bazy nie uruchamiano podczas tej zmiany.

## Komunikaty sukcesu backupu i restore (2026-11-16)

Po udanym zakończeniu operacji `db.sh` wyświetla zielony komunikat `SUKCES`
z nazwą pliku kopii i informacją, której bazy dotyczy operacja.

```bash
bash -n scripts/db.sh
printf '5\n' | ./scripts/db.sh
```
Kontrola składni skryptu i powrotu z menu; nie wykonywano operacji na bazach.

## Opcjonalny upload zwyklym FTP po buildzie produkcyjnym (2026-11-16)

`deploy-package.sh` po utworzeniu paczki pyta o upload zwyklym FTP na port 21
do `/public_html/3dprint.4fm.pl`. Domyslny host/uzytkownik to
`ftp.piotrsta.webd.pl` / `piotrsta`; haslo podaje sie ukrycie. Wymagany
transfer jest nieszyfrowany, wiec wymaga ostrzezenia i wpisania `PLAIN FTP`.
Zdalne `.env` i `storage/` pozostaja bez zmian.

```bash
bash -n deploy-package.sh scripts/build-deploy.sh tests/deploy-package.sh
bash tests/deploy-package.sh
git diff --check
```
Kontrola skladni, pelny test paczki produkcyjnej i formatowania zmian.

## Katalogi runtime podczas wdrozenia FTP (2026-11-17)

Upload tworzy brakujace puste katalogi Laravel, w tym `storage/framework/views`,
bez wysylania `.env` i zawartosci `storage/` ani usuwania danych z serwera.
Uprawnienia zapisu dla PHP trzeba zapewnic na hostingu.

```bash
bash -n deploy-package.sh scripts/prepare-deploy-upload.sh tests/deploy-package.sh
bash tests/deploy-package.sh
git diff --check
```
Kontrola skladni i test budowania paczki oraz odtworzenia katalogow i
zachowania danych przy dwukrotnym mirrorze na lokalnym celu testowym.

## Konfiguracja produkcyjna przez cPanel (2026-11-17)

Po wysylce FTP skrypt przypomina o recznym sprawdzeniu i aktualizacji `.env`
na podstawie `.env.prod` przez cPanel HTTPS oraz o zachowaniu `APP_KEY`
i wyczyszczeniu cache konfiguracji. Sekrety nadal nie sa wysylane przez FTP.

```bash
bash -n deploy-package.sh
git diff --check
```
Kontrola skladni i formatowania zmian.
