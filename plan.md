# Plan projektu: 3dprint.4fm.pl (Laravel + CMS)

## 1) Cel i zakres (MVP)
- Aplikacja monolityczna w Laravel (bez osobnego API).
- Publiczna warstwa prezentacji: strona główna + sekcja „Projekty”.
- Prosty panel administracyjny (CMS) do zarządzania projektami, zdjęciami i plikami do pobrania.
- Logowanie administratora oparte o standardowy mechanizm Laravel.

## 2) Zasady pracy i artefakty
- Srodowisko lokalne (2026-11-09): zamiast planowanego Docker CE uzytkownik zainstalowal `docker.io` i stary `docker-compose` z repozytorium Ubuntu. Potwierdzono brak pluginu `docker compose`; do wykonania instalacja `docker-compose-v2` oraz dodanie uzytkownika do grupy `docker` i ponowne logowanie. Agent nie ma dostepu do `sudo` bez hasla. Odtworzenie danych po migracji nie zostalo zweryfikowane.
- Ustalenia projektowe zapisujemy na bieżąco w tym pliku.
- Podczas implementacji prowadzimy osobny dziennik kluczowych komend (z krótkim komentarzem), proponowana lokalizacja:
  - `/media/ps/PS_M2/GitHub/3dprint/commands-log.md`
- Implementacje JS w projekcie zawierają krótki komentarz wyjaśniający działanie.
- Pełna dokumentacja przekazaniowa dla agentów AI (architektura, środowisko, model, pipeline mediów, pułapki, blueprint portfolio fotograficznego, snapshot kodu): `docs/ai-handover/` (start: `docs/ai-handover/README.md`). Załącznik z kodem regeneruje `docs/ai-handover/generate-code-appendix.sh`.

## 3) Architektura i technologie (propozycja robocza)
- Backend: Laravel (aktualna stabilna wersja na moment startu).
- Baza danych: MySQL w osobnym kontenerze Docker.
- Frontend: Blade + Bootstrap.
- CMS: Filament (zatwierdzone).
- Edytor treści: Trix (zatwierdzone).
- Build assets: Vite (domyślnie w nowym Laravel), bez Gulp.
- JS: vanilla JS lub Vue tylko tam, gdzie realnie potrzebne (np. galeria/lightbox).
- Galeria: GLightbox (zatwierdzone).

## 4) Model domeny (MVP)
- `projects`
  - `id`, `slug`, `title`, `short_description`, `description`, `print_notes`, `is_published`, `published_at`, `created_at`, `updated_at`
- `project_images`
  - `id`, `project_id`, `path_large`, `path_thumb`, `alt`, `sort_order`, `created_at`, `updated_at`
- `project_files`
  - `id`, `project_id`, `label`, `file_type` (`stl|freecad|py|other`), `path`, `sort_order`, `created_at`, `updated_at`
- (opcjonalnie) `settings` dla treści strony głównej.
- Bez kategorii/tagów w MVP (prosta lista projektów).

## 5) UX/UI warstwy publicznej (MVP)
- Layout:
  - minimalistyczny nagłówek (mała wysokość),
  - stałe menu po lewej stronie z aktywnym wyróżnieniem,
  - główna kolumna treści.
- Strony:
  - `/` — opis serwisu + grafika hero (placeholder),
  - `/projekty` — lista projektów,
  - `/projekty/{slug}` — karta projektu.
- Język interfejsu w MVP: angielski.
- Karta projektu:
  - nagłówek (nazwa),
  - mini-galeria (ok. 5 zdjęć), kliknięcie otwiera powiększenie dopasowane do ekranu,
  - opis ogólny,
  - sekcja plików do pobrania,
  - opis techniki druku (materiał, parametry itd.).

## 6) CMS / administracja (MVP)
- Autoryzacja:
  - logowanie administratora przez standardowy mechanizm Laravel.
- Framework panelu: Filament.
- Konta administracyjne: jedno konto administratora na start.
- Zarządzanie projektami:
  - CRUD projektu,
  - zarządzanie publikacją (`is_published`, data publikacji),
  - edycja pól tekstowych przez lekki darmowy WYSIWYG (wybrane).
- Zarządzanie multimediami:
  - upload wielu zdjęć,
  - kolejność zdjęć,
  - upload plików do pobrania + etykieta + typ.

## 7) Obróbka obrazów (propozycja)
- Po uploadzie zapisywać:
  - wersję „large” do podglądu (max 1200x900, bez rozciągania),
  - miniaturę „thumb” (np. 300x300, crop do kwadratu 1:1),
  - bez przechowywania oryginału (wybrane).
- Format wersji wynikowych: WebP (wybrane).
- Docelowo dla FHD:
  - lista projektów/mini-galeria oparta o thumb 1:1,
  - lightbox na bazie large.

## 8) Środowisko deweloperskie (Docker) — plan
- `docker-compose.yml`:
  - `app` (PHP + Composer + rozszerzenia Laravel),
  - `web` (Nginx),
  - `db` (MySQL, oddzielny kontener),
  - (opcjonalnie) `phpmyadmin` wyłącznie lokalnie.
- Woluminy:
  - montowanie katalogu projektu do kontenera aplikacji.
- Wariant: własny `docker-compose` (bez Sail).
- Konfiguracja:
  - `.env` pod Docker,
  - healthcheck dla DB,
  - sieć wewnętrzna między usługami,
  - lokalny host development: `3dprint.local`.

## 9) Backup i restore bazy (plan)
- `scripts/db.sh` udostępnia menu:
  - backup i restore lokalnej bazy,
  - backup i restore produkcyjnej bazy.
- Ustawienia lokalne pochodzą z `.env` i kontenera MySQL Compose; ustawienia
  produkcyjne są bezpiecznie parsowane z `.env.prod` przez bibliotekę Dotenv.
  Skrypt nie wykonuje plików środowiskowych jako kodu powłoki.
- Kopie trafiają do `backups/mysql/local/` lub `backups/mysql/production/`;
  stare pliki `backup_*.sql` w `backups/mysql/` są nadal widoczne jako lokalne.
- Restore wybiera kopię, a następnie usuwa istniejące tabele/widoki i odtwarza
  wybraną kopię. Wymaga potwierdzenia, a dodatkowo wpisania `RESTORE LOCAL`
  albo `RESTORE PRODUCTION`; produkcyjne opcje są wyróżnione kolorem.
- Media w `storage/app/public/` nie są częścią backupu bazy i wymagają osobnej kopii.

## 10) Bezpieczeństwo i jakość (MVP)
- Walidacja uploadów (typ, rozmiar, liczba).
- Przechowywanie w lokalnym `storage` z przygotowaniem do późniejszej migracji na S3.
- Ograniczenie dostępu do panelu admin.
- Podstawowe logowanie błędów i czytelne komunikaty dla użytkownika.

## 11) Etapy realizacji (po akceptacji planu)
1. Setup repo aplikacji Laravel + Docker + uruchomienie lokalne. **(zrobione)**
2. Konfiguracja auth/admin i struktury bazy (migracje + modele). **(zrobione)**
3. Publiczny layout + strony + nawigacja. **(zrobione)**
4. CMS: CRUD projektów, zdjęć i plików. **(zrobione)**
5. Obróbka obrazów i galeria z powiększeniem. **(częściowo zrobione: galeria działa; obróbka obrazów pozostaje dodatkiem)**
6. Skrypt backup/restore bazy. **(zrobione)**
7. Dopracowanie UX, seed testowy, porządki migracji. **(zrobione w podstawowym zakresie)**
8. Pakowanie release produkcyjnego: `.env.prod` jako `.env`, `vendor` instalowany z locka bez zaleznosci dev, assety Vite budowane w jednorazowym kontenerze; po buildzie opcjonalny upload zwyklym FTP na porcie 21. Niezaszyfrowany transfer wymaga dodatkowego wpisania `PLAIN FTP`; zdalne `.env` i dane `storage/` sa zachowywane, a brakujace katalogi runtime tworzone. Skrypt przypomina o recznej aktualizacji `.env` przez cPanel HTTPS. **(skonfigurowane; zalecane przejscie na FTPS/SFTP, gdy bedzie dostepne)**
9. Poczta (Mailpit w dev), reset hasła e-mailem i 2FA (TOTP + e-mail) przez wbudowane MFA Filamenta; usunięte własne trasy `/login`, `/logout`. **(zrobione)**

## 12) Pytania otwarte do doprecyzowania
1. CMS: **Filament** (wybrane).
2. Edytor treści: **lekki darmowy WYSIWYG (Trix)** (wybrane).
3. Galeria: **GLightbox** (wybrane).
4. Przechowywanie plików: **lokalny storage + gotowość do migracji na S3** (wybrane).
5. Kategorie/tagi: **nie w MVP** (wybrane).
6. Język MVP: **tylko angielski** (wybrane).
7. 2FA: **wbudowane MFA Filamenta — aplikacja TOTP z kodami odzyskiwania + kody e-mail, włączane per konto w profilu (`/admin/profile`)** (wybrane).

## 13) Plan dodatków i ulepszeń

Podstawowy zakres projektu jest zakończony. Poniższe pozycje są dalszym
rozwojem i nie blokują działania obecnej strony. Kolejność jest wstępna;
pracujemy nad nimi etapami.

1. **Tryb ciemny** — przełącznik w prawym górnym rogu, domyślne dopasowanie
   do ustawień systemu i zapamiętywanie ręcznego wyboru w przeglądarce.
   **(w trakcie)**
2. **Przetwarzanie zdjęć** — generowanie WebP: duży obraz do 1200×900 i
   kwadratowa miniatura 300×300; podmiana istniejących kopii i zachowanie
   czyszczenia plików przez observery. **(do zrobienia)**
3. **Edycja treści** — zastąpienie zwykłych pól opisu i notatek edytorem
   WYSIWYG, z zachowaniem bezpiecznego wyświetlania treści. **(do zrobienia)**
4. **Strona główna** — uzupełnienie o docelową sekcję hero i możliwość
   edycji jej treści z panelu. **(do zrobienia)**
5. **Dostępność i dopracowanie interfejsu** — przegląd kontrastu, obsługi
   klawiaturą, stanów focus i zachowania na małych ekranach.
   **(do zrobienia)**
6. **Przygotowanie do S3** — weryfikacja konfiguracji dysków i przepływu
   mediów z zewnętrznym storage bez zmiany obecnego lokalnego storage.
   **(do zrobienia)**
