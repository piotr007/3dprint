# Poradnik: budowanie stron opartych o dane w Laravel

*Na przykładzie tego projektu (portfolio wydruków 3D + panel Filament). Wszystkie fragmenty kodu pochodzą z tego repozytorium — możesz je otworzyć i porównać.*

---

## Spis treści

1. [Wielki obraz: jak Laravel przetwarza żądanie](#1-wielki-obraz-jak-laravel-przetwarza-żądanie)
2. [Warstwa bazy danych od zera](#2-warstwa-bazy-danych-od-zera)
3. [CRUD w Eloquent — mapowanie na SQL](#3-crud-w-eloquent--mapowanie-na-sql)
4. [Seedowanie i fabryki](#4-seedowanie-i-fabryki)
5. [Gdzie przygotowywać i przekształcać dane](#5-gdzie-przygotowywać-i-przekształcać-dane)
6. [Wzorce projektowe stosowane obecnie](#6-wzorce-projektowe-stosowane-obecnie)
7. [Monolit vs API + osobny front](#7-monolit-vs-api--osobny-front)
8. [Raport: narzędzia do zarządzania treścią](#8-raport-narzędzia-do-zarządzania-treścią)
9. [Co to jest S3 i po co „przygotowanie pod migrację"](#9-co-to-jest-s3-i-po-co-przygotowanie-pod-migrację)
10. [Przepis: taki projekt krok po kroku](#10-przepis-taki-projekt-krok-po-kroku)
11. [Konta administracyjne i reset hasła](#11-konta-administracyjne-i-reset-hasła)
12. [Słowniczek](#12-słowniczek)
13. [Co czytać dalej](#13-co-czytać-dalej)

---

## 1. Wielki obraz: jak Laravel przetwarza żądanie

Laravel to framework MVC (Model–View–Controller). Każde żądanie HTTP przechodzi tę samą drogę:

```
przeglądarka
    │  GET /projekty/sample-bracket
    ▼
public/index.php          ← jedyny punkt wejścia (front controller)
    ▼
Middleware                ← sesja, CSRF, auth… (warstwy „cebuli" wokół aplikacji)
    ▼
Router (routes/web.php)   ← dopasowanie URL → kontroler + akcja
    ▼
Kontroler                 ← orkiestracja: pobierz dane, przekaż do widoku
    ▼
Model (Eloquent)  ⇄  MySQL   ← ORM tłumaczy PHP ↔ SQL
    ▼
Widok (Blade)             ← HTML z wstawionymi danymi
    ▼
Response → przeglądarka
```

W tym projekcie ta droga wygląda konkretnie tak. Routing (`routes/web.php`):

```php
Route::get('/projekty', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projekty/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
```

Zapis `{project:slug}` to **route model binding**: Laravel sam wykona `SELECT * FROM projects WHERE slug = ?` i wstrzyknie gotowy model do kontrolera. Jeśli rekordu nie ma — automatyczne 404. Nie piszesz tego kodu ręcznie.

Kontroler (`app/Http/Controllers/ProjectController.php`):

```php
public function show(Project $project)          // ← model już pobrany przez binding
{
    abort_unless($project->is_published, 404);  // logika dostępu
    $project->load(['images', 'files']);        // dociągnięcie relacji
    return view('projects.show', compact('project')); // dane → szablon Blade
}
```

**Kluczowa intuicja:** kontroler nie „buduje HTML-a" i nie pisze SQL-a. Jest cienkim łącznikiem: *pobierz dane → ewentualnie przekształć → oddaj do warstwy prezentacji*. W monolicie prezentacją jest Blade; w API byłby to JSON (patrz §7) — reszta przepływu jest identyczna.

---

## 2. Warstwa bazy danych od zera

### 2.1 Migracje — schemat bazy jako kod

Zamiast klikać tabele w phpMyAdmin, opisujesz je w PHP. Migracja to plik z dwiema metodami: `up()` (co utworzyć) i `down()` (jak cofnąć). Z tego projektu (`database/migrations/2026_08_29_213304_create_projects_table.php`):

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();                                    // BIGINT AUTO_INCREMENT PRIMARY KEY
    $table->string('slug')->unique();                // VARCHAR(255) + UNIQUE index
    $table->string('title');
    $table->text('short_description');
    $table->longText('description');
    $table->longText('print_notes')->nullable();     // może być NULL
    $table->boolean('is_published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();                            // created_at + updated_at
});
```

Uruchomienie: `php artisan migrate`. Laravel pamięta w tabeli `migrations`, które pliki już wykonał — dlatego można to wołać wielokrotnie i bezpiecznie.

**Dlaczego to najlepsza praktyka:**
- schemat jest w repozytorium — każdy klon projektu odtwarza identyczną bazę jedną komendą;
- zmiany są przyrostowe: nowa kolumna = nowa migracja (patrz `2026_08_30_225700_add_is_featured_to_project_images_table.php` — dodaliśmy `is_featured` później, bez ruszania starych plików);
- historia zmian schematu = historia gita.

Zasada: **istniejących migracji, które weszły na serwer, nie edytuje się** — dopisuje się nowe.

### 2.2 Eloquent — ORM Laravela

Eloquent realizuje wzorzec **Active Record**: jedna klasa modelu = jedna tabela, jeden obiekt = jeden wiersz. Obiekt „umie sam się zapisać" (`$project->save()`).

Konwencja nazw robi 90% roboty za Ciebie:

| Ty piszesz | Laravel zakłada |
|---|---|
| klasa `Project` | tabela `projects` (liczba mnoga, snake_case) |
| klasa `ProjectImage` | tabela `project_images` |
| relacja `hasMany(ProjectImage::class)` | klucz obcy `project_id` w `project_images` |
| `$table->id()` | kolumna `id` jako klucz główny |

Model z tego projektu (`app/Models/Project.php`):

```php
class Project extends Model
{
    protected $fillable = [    // ← które pola wolno masowo przypisywać (ochrona!)
        'slug', 'title', 'short_description', 'description',
        'print_notes', 'is_published', 'published_at',
    ];

    protected $casts = [       // ← automatyczna konwersja typów baza ↔ PHP
        'is_published' => 'boolean',   // TINYINT(1) → true/false
        'published_at' => 'datetime',  // string → obiekt Carbon (daty)
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(ProjectImage::class)->where('is_featured', true);
    }
}
```

Trzy rzeczy warte zapamiętania:

1. **`$fillable`** — bezpiecznik. `Project::create($request->all())` zapisze *tylko* wymienione pola. Bez tego użytkownik mógłby dopisać do formularza pole `is_admin=1` (atak mass assignment).
2. **`$casts`** — w bazie `is_published` to `0/1`, w PHP dostajesz `true/false`; `published_at` to nie string, tylko obiekt `Carbon` z metodami typu `->format('Y-m-d')`, `->diffForHumans()`.
3. **Relacje to metody**, które opisują klucze obce. Potem używasz ich jak właściwości: `$project->images` (kolekcja), `$project->featuredImage` (jeden obiekt albo `null`).

### 2.3 Relacje używane w tym projekcie

```
Project 1 ──── * ProjectImage     (hasMany / belongsTo)
Project 1 ──── * ProjectFile      (hasMany / belongsTo)
Project 1 ──── 1 ProjectImage     (hasOne z warunkiem is_featured = true)
```

Najczęstsze typy relacji w Eloquent: `hasOne` / `hasMany` (1:1, 1:N), `belongsTo` (strona odwrotna), `belongsToMany` (N:M przez tabelę pośrednią, np. projekty ↔ tagi — przyda się, gdy dodasz tagowanie).

**Pułapka N+1 i eager loading.** Ten kod w pętli po 20 projektach wykona 21 zapytań SQL:

```php
$projects = Project::all();                 // 1 zapytanie
foreach ($projects as $p) { $p->featuredImage; }  // +20 zapytań (po jednym na projekt!)
```

Rozwiązanie — `with()` — widoczne w naszym kontrolerze:

```php
Project::query()
    ->where('is_published', true)
    ->with(['featuredImage', 'images'])   // ← eager loading: 3 zapytania zamiast 41
    ->latest('published_at')
    ->get();
```

To najczęstszy błąd wydajnościowy początkujących; wyrób nawyk używania `with()` zawsze, gdy w pętli sięgasz po relację.

---

## 3. CRUD w Eloquent — mapowanie na SQL

CRUD (Create, Read, Update, Delete) przekłada się na Eloquent wprost. Ściąga:

| Operacja | Eloquent | SQL pod spodem |
|---|---|---|
| **C**reate | `Project::create([...])` | `INSERT INTO projects ...` |
|  | `$p = new Project(); $p->title = 'X'; $p->save();` | jw. |
| **R**ead — wszystko | `Project::all()` | `SELECT * FROM projects` |
| Read — po kluczu | `Project::find(5)` / `findOrFail(5)` | `SELECT ... WHERE id = 5` |
| Read — z warunkami | `Project::where('is_published', true)->get()` | `SELECT ... WHERE is_published = 1` |
| Read — pierwszy | `Project::where(...)->first()` | `... LIMIT 1` |
| Read — stronicowanie | `Project::paginate(12)` | `... LIMIT 12 OFFSET n` (+ COUNT) |
| **U**pdate | `$p->update(['title' => 'Y'])` | `UPDATE projects SET title=... WHERE id=...` |
|  | `$p->title = 'Y'; $p->save();` | jw. (zapisuje tylko zmienione kolumny) |
| **D**elete | `$p->delete()` | `DELETE FROM projects WHERE id=...` |
| Upsert | `Project::updateOrCreate([klucz], [wartości])` | SELECT + INSERT *lub* UPDATE |

Metody budujące zapytanie (`where`, `orderBy`, `latest`, `limit`, `with`) można łańcuchować — SQL powstaje dopiero przy wywołaniu „kończącym": `get()`, `first()`, `paginate()`, `count()`. To tzw. query builder.

Gdy chcesz podejrzeć, jaki SQL naprawdę się wykona:

```php
Project::where('is_published', true)->toSql();      // zwraca string SQL
// albo interaktywnie: docker compose exec app php artisan tinker
```

`tinker` to REPL — najlepsze narzędzie do nauki Eloquenta. Wpisujesz wyrażenia, od razu widzisz wynik i możesz eksperymentować na żywej bazie deweloperskiej.

---

## 4. Seedowanie i fabryki

### Co to jest seedowanie?

**Seeder** to klasa, która wypełnia bazę danymi startowymi („zasiewa" ją — stąd nazwa). Po co?

- świeży klon projektu ma od razu konto admina i przykładowe dane — nie klikasz nic ręcznie;
- każdy deweloper (i CI) pracuje na tych samych danych testowych;
- dane startowe są w repo, więc są udokumentowane i powtarzalne.

Nasz `database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    User::query()->updateOrCreate(
        ['email' => 'admin@3dprint.local'],   // ← po czym szukać
        ['name' => 'Admin', 'password' => 'password'],  // ← co ustawić
    );

    Project::updateOrCreate(
        ['slug' => 'sample-bracket'],
        ['title' => 'Sample Bracket', 'is_published' => true, /* … */],
    );
}
```

Uruchomienie: `php artisan db:seed` (u nas: `docker compose exec app php artisan db:seed`).

**Konwencja tego projektu: seedery muszą być idempotentne** — czyli wielokrotne uruchomienie nie tworzy duplikatów. Dlatego `updateOrCreate` (znajdź po e-mailu/slugu; jeśli jest → zaktualizuj, jeśli nie ma → utwórz), a nie `create`. To dobra praktyka ogólnie, nie tylko u nas.

### Fabryki (factories)

Do *masowych* danych testowych służą fabryki + biblioteka Faker (generator losowych nazwisk, zdań, dat):

```php
// database/factories/ProjectFactory.php (przykład — u nas jeszcze nie ma)
public function definition(): array
{
    return [
        'slug' => fake()->unique()->slug(2),
        'title' => fake()->sentence(3),
        'short_description' => fake()->paragraph(),
        'description' => fake()->paragraphs(3, true),
        'is_published' => fake()->boolean(80),   // 80% szans na true
        'published_at' => fake()->dateTimeThisYear(),
    ];
}

// użycie w seederze lub teście:
Project::factory()->count(30)->create();   // 30 losowych projektów jednym wierszem
```

Rozróżnienie w praktyce: **seeder** = dane niezbędne/konkretne (admin, słowniki, przykładowy rekord), **fabryka** = dane losowe w dużej ilości (do testów wydajności listy, stronicowania, testów automatycznych).

Przydatny komplet komend:

```sh
php artisan migrate            # wykonaj zaległe migracje
php artisan migrate:fresh --seed   # UWAGA: kasuje bazę, buduje od zera i seeduje (tylko dev!)
php artisan db:seed            # sam seeding
php artisan make:model ProjectTag -mfs   # model + migracja + fabryka + seeder za jednym razem
```

---

## 5. Gdzie przygotowywać i przekształcać dane

To sedno Twojego pytania. W Laravelu każda faza przetwarzania ma swoje dedykowane miejsce. Mapa:

```
        WEJŚCIE (request)                         WYJŚCIE (response)
┌─────────────────────────────┐          ┌──────────────────────────────┐
│ Routing / route model binding│          │ Blade (monolit)              │
│ Form Request → WALIDACJA     │          │   lub                        │
│ $casts → typy PHP            │          │ API Resource → kształt JSON  │
│ Mutatory → normalizacja      │          │ Akcesory → pola wyliczane    │
└──────────────┬───────────────┘          └──────────────▲───────────────┘
               │            KONTROLER (cienki!)          │
               └────────► Serwis / Akcja ────────────────┘
                          Model + Observer (skutki uboczne)
```

### 5.1 Walidacja wejścia: Form Request

Nie waliduj w kontrolerze — wydziel to do klasy. `php artisan make:request StoreProjectRequest`:

```php
class StoreProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug'  => ['required', 'alpha_dash', 'unique:projects,slug'],
            'is_published' => ['boolean'],
        ];
    }
}

// kontroler dostaje TYLKO zwalidowane dane:
public function store(StoreProjectRequest $request)
{
    $project = Project::create($request->validated());
    return redirect()->route('projects.show', $project);
}
```

Jeśli walidacja się nie powiedzie, Laravel *sam* zawraca użytkownika do formularza z błędami (w API — sam zwraca JSON 422). Kontroler w ogóle nie widzi złych danych. W tym projekcie walidację form robi za nas Filament, ale w kodzie pisanym ręcznie Form Request to standard.

### 5.2 Normalizacja przy zapisie: mutatory i `$casts`

- `$casts` (widziałeś w §2.2) — konwersja typów w obie strony, deklaratywnie.
- **Mutator** — funkcja przekształcająca wartość *przy zapisie do modelu*. Przykład z `app/Models/User.php`: cast `'password' => 'hashed'` sprawia, że seeder może wpisać `'password'` tekstem, a do bazy trafia bcrypt. Podobnie u nas `ProjectImage` ma mutator, który z przesłanego pliku (`UploadedFile`) robi ścieżkę stringową.

### 5.3 Kształtowanie wyjścia: akcesory i API Resources

**Akcesor** — pole wyliczane przy odczycie, nieistniejące w bazie:

```php
// w modelu Project:
protected function thumbUrl(): Attribute
{
    return Attribute::get(fn () =>
        $this->featuredImage
            ? asset('storage/' . $this->featuredImage->path_thumb)
            : null
    );
}
// w Blade: {{ $project->thumb_url }}
```

**API Resource** — gdybyś budował API zamiast Blade, to jest warstwa „prezentacji JSON". Kontrolujesz dokładnie, co wychodzi na świat (nigdy nie zwracaj surowego modelu — wyciekną kolumny, o których zapomnisz):

```php
class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'  => $this->slug,
            'title' => $this->title,
            'published_at' => $this->published_at?->toIso8601String(),
            'images' => ProjectImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
// w kontrolerze API: return ProjectResource::collection($projects);
```

Zauważ symetrię: **Form Request filtruje to, co wchodzi; Resource filtruje to, co wychodzi.** Model w środku pozostaje „czysty".

### 5.4 Skutki uboczne: Observery

Gdy zapis/kasowanie rekordu ma pociągać za sobą dodatkowe operacje (pliki na dysku, liczniki, cache), nie wpychaj tego do kontrolera — użyj **observera**, który nasłuchuje zdarzeń cyklu życia modelu (`creating`, `updating`, `deleting`…).

Realny przykład z tego repo — `app/Observers/ProjectImageObserver.php`:

- `creating` → wygeneruj miniaturę, dopilnuj, by tylko jedno zdjęcie było `is_featured`;
- `updating` → jeśli podmieniono plik, skasuj stary z dysku i zrób nową miniaturę;
- `deleting` → usuń oba pliki z dysku.

Dzięki temu **każda** ścieżka zapisu (Filament, tinker, przyszłe API) przechodzi przez tę samą logikę. Uwaga: operacje omijające Eloquent (surowe `DELETE`, `Model::query()->update()`) *nie* odpalają observerów — dlatego w konwencjach projektu jest zakaz obchodzenia ich raw query.

---

## 6. Wzorce projektowe stosowane obecnie

Co realnie stosuje się w społeczności Laravel w 2025/26 — od najważniejszego:

**1. Active Record (Eloquent) używany wprost.** Największa zmiana mentalna względem starszych poradników: **wzorzec Repository w Laravelu wyszedł z mody**. Owijanie Eloquenta w interfejsy `ProjectRepositoryInterface` daje niewiele (i tak nie podmienisz ORM-a), a odbiera pół siły Eloquenta (relacje, scope'y, paginację). Modele wołamy bezpośrednio z kontrolerów/serwisów. Repository ma sens tylko przy naprawdę egzotycznych źródłach danych (zewnętrzne API udające bazę).

**2. Cienkie kontrolery + klasy Action / Service.** Gdy logika przekracza kilka linii, wydziela się ją do klasy z jedną publiczną metodą:

```php
// app/Actions/PublishProject.php — konwencja „jedna akcja = jedna klasa"
class PublishProject
{
    public function handle(Project $project): void
    {
        $project->update(['is_published' => true, 'published_at' => now()]);
        // + np. powiadomienie, czyszczenie cache…
    }
}
// w kontrolerze: app(PublishProject::class)->handle($project);
```

Ten styl spopularyzowały Laravel Actions i kod źródłowy Filamenta. „Service" to to samo, tylko klasa grupuje kilka powiązanych metod. Dla małych projektów jak ten — logika może spokojnie żyć w kontrolerze, dopóki się nie rozrośnie.

**3. Query scope'y** — nazwane, wielokrotnego użytku kawałki zapytań w modelu:

```php
// w Project: public function scopePublished($q) { $q->where('is_published', true); }
Project::published()->latest('published_at')->get();   // czytelniej niż powtarzany where
```

Idealny pierwszy refaktor dla naszego `ProjectController::index()`.

**4. Observery / Eventy** — skutki uboczne poza kontrolerem (mamy: `ProjectImageObserver`, `ProjectFileObserver`). Eventy+listenery to luźniejsza wersja tego samego, gdy reakcji jest wiele lub mają iść do kolejki.

**5. Form Request + API Resource** — granice wejścia/wyjścia (§5).

**6. Policy** — autoryzacja per model (`ProjectPolicy::update($user, $project)`), używana automatycznie przez Filament i `$this->authorize()`.

**7. Jobs / kolejki** — długie operacje (np. konwersja obrazów do WebP z naszej roadmapy §7 plan.md) wyrzuca się do tła: `GenerateThumbnails::dispatch($image)`.

**Antywzorce, których unikać:** gruby kontroler (walidacja+logika+SQL w jednym), logika w szablonach Blade, zapytania w pętli (N+1), `Model::all()` bez paginacji na dużych tabelach, obchodzenie observerów surowymi query.

---

## 7. Monolit vs API + osobny front

Twoja intuicja jest trafna: **warstwa danych jest identyczna**. Różni się tylko ostatni krok:

| | Monolit (ten projekt) | API + front (React/Vue) |
|---|---|---|
| Routing | `routes/web.php` | `routes/api.php` |
| Wyjście | `return view('projects.show', ...)` | `return new ProjectResource($project)` |
| Format | HTML (Blade) | JSON |
| Stan zalogowania | sesja + cookie | token (Sanctum) |
| Walidacja błędów | redirect z błędami do formularza | JSON 422 z listą błędów |

Migracje, modele, relacje, seedery, observery, walidacja — **bez zmian**. Dlatego nauka na monolicie w niczym nie przepada; przejście na API to głównie zamiana `view()` na `Resource` i dodanie autoryzacji tokenowej. Monolit dla portfolio/CMS to zresztą wciąż w pełni zasadny, rekomendowany wybór (mniej ruchomych części, SEO za darmo, jeden deploy). Ścieżką pośrednią jest Livewire (Blade + interaktywność bez pisania JS) — to na nim stoi Filament.

---

## 8. Raport: narzędzia do zarządzania treścią

W ekosystemie Laravel „CMS" realizuje się zwykle jako **panel administracyjny generowany nad własnymi modelami** (tak zrobiliśmy), rzadziej jako pełny gotowy CMS. Stan rynku:

### Panele administracyjne (nad Twoimi modelami Eloquent)

| Narzędzie | Cena | Stack | Ocena |
|---|---|---|---|
| **Filament** ⭐ *(nasz wybór)* | darmowy (MIT) | Livewire + Tailwind | Obecny standard de facto. Najżywszy rozwój, ogromny ekosystem pluginów, formularze/tabele/relacje deklarowane w PHP. Wada: spore API do nauki, breaking changes między wersjami (my jesteśmy na v5, większość przykładów w sieci dotyczy v3 — patrz „Filament 5 gotchas" w AGENTS.md). |
| **Laravel Nova** | $99+/projekt | Vue | Oficjalny panel od twórców Laravela. Dopracowany, ale płatny, wolniej rozwijany i mniej elastyczny od Filamenta; społeczność wyraźnie odpłynęła do Filamenta. |
| **Backpack** | darmowy core, płatne pro | Blade + Bootstrap | Dojrzały, dobra dokumentacja. Sensowna alternatywa, jeśli ktoś nie chce Livewire; funkcjonalnie uboższy w wersji darmowej. |
| **Orchid** | darmowy | Blade + „screens" | Nisza; własna, mniej intuicyjna abstrakcja ekranów. |
| **Voyager** | darmowy | Blade | ⚠️ Praktycznie porzucony, nie wybierać do nowych projektów. |

### Pełne CMS-y (gdy treść > aplikacja)

- **Statamic** — CMS na Laravelu (płaskie pliki lub baza), świetny dla stron contentowych; płatny w wersji pro. Wybór, gdy edytor treści jest ważniejszy niż własny model danych.
- **Twill** (Area 17) — darmowy CMS-toolkit, ładny UX dla redaktorów.
- **October CMS** — płatny, po rozłamie społeczności (fork Winter CMS) stracił impet.

### Dlaczego Filament był tu właściwym wyborem

1. Nasz „CMS" to w istocie CRUD na 3 własnych modelach (`Project`, `ProjectImage`, `ProjectFile`) — panel nad Eloquentem pasuje idealnie, pełny CMS byłby przerostem.
2. Darmowy i open source (Nova odpada kosztem przy projekcie hobbystycznym).
3. Definicje w czystym PHP — zobacz `app/Filament/Admin/Resources/ProjectResource.php`: formularz, tabela, walidacja i upload plików bez linijki JS.
4. Relation managers (`ImagesRelationManager`, `FilesRelationManager`) dały edycję zdjęć/plików w kontekście projektu — dokładnie nasz przypadek użycia.
5. Największa społeczność = najłatwiej o pomoc i gotowe pluginy.

Realna konkurencja w 2026 to tylko Filament vs Backpack vs (za pieniądze) Nova — i domyślnym wyborem społeczności jest Filament.

---

## 9. Co to jest S3 i po co „przygotowanie pod migrację"

**Amazon S3** (Simple Storage Service) to usługa **object storage**: przechowywanie plików w chmurze za API HTTP. Zamiast ścieżki na dysku serwera plik ma klucz w „buckecie" (kontenerze), np. `projects/images/abc.webp`, i URL do pobrania. Płaci się za gigabajty i transfer. „S3" stało się też *standardem API* — zgodne z nim są tańsze zamienniki: Cloudflare R2, DigitalOcean Spaces, Backblaze B2, samodzielnie hostowane MinIO.

**Po co, skoro mamy dysk serwera?**

- pliki przeżywają wymianę/awarię serwera i nie zapychają jego dysku;
- przy >1 serwerze aplikacji wszystkie widzą te same pliki;
- łatwe podpięcie CDN (szybkie serwowanie obrazków na świat);
- praktycznie nieograniczona pojemność.

**Jak Laravel to abstrahuje.** Cały dostęp do plików idzie przez fasadę `Storage` i „dyski" zdefiniowane w `config/filesystems.php` (pod spodem biblioteka Flysystem):

```php
Storage::disk('public')->put($path, $contents);   // dziś: storage/app/public na dysku
Storage::disk('public')->delete($path);
Storage::disk('public')->url($path);
```

Nasz kod już tak pisze — `app/Support/ProjectMedia.php` i observery operują wyłącznie przez `Storage::disk('public')` i przechowują w bazie **względne ścieżki** (`projects/images/plik.webp`), a nie pełne URL-e.

**„Przygotowanie pod migrację do S3" znaczy więc tyle, że w przyszłości wystarczy:**

1. `composer require league/flysystem-aws-s3-v3`
2. wpisać klucze S3/R2 w `.env` i przestawić dysk (np. `FILESYSTEM_DISK=s3`),
3. przekopiować istniejące pliki do bucketa,
4. ewentualnie podmienić `asset('storage/…')` w widokach na `Storage::url(…)` (jedyne miejsce, gdzie jeszcze zakładamy lokalny dysk).

Zero zmian w logice uploadu, observerach i bazie — to jest wartość trzymania się abstrakcji `Storage` od pierwszego dnia.

---

## 10. Przepis: taki projekt krok po kroku

Kolejność, w jakiej samodzielnie odtworzysz projekt tego typu. Przy każdym kroku — rozdział, który go objaśnia.

```sh
# 1. Szkielet aplikacji + środowisko (Docker: app/web/db — patrz docker-compose.yml)
composer create-project laravel/laravel moja-strona
cp .env.example .env && php artisan key:generate     # konfiguracja przez .env

# 2. Zaprojektuj model danych NA PAPIERZE: encje, pola, relacje
#    (u nas: Project 1-N ProjectImage, Project 1-N ProjectFile)

# 3. Modele + migracje (§2.1, §2.2)
php artisan make:model Project -m
php artisan make:model ProjectImage -m
php artisan migrate

# 4. Uzupełnij modele: $fillable, $casts, relacje (§2.2, §2.3)

# 5. Seeder z kontem admina i przykładowymi danymi (§4) — idempotentnie!
php artisan db:seed

# 6. Publiczna część: routing → kontroler → widoki Blade (§1, §5)
php artisan make:controller ProjectController

# 7. Panel administracyjny: Filament (§8)
composer require filament/filament
php artisan filament:install --panels
php artisan make:filament-resource Project    # CRUD w panelu gotowy

# 8. Pliki/obrazy: dysk 'public' + storage:link + observery do sprzątania (§5.4, §9)
php artisan storage:link
php artisan make:observer ProjectImageObserver --model=ProjectImage

# 9. Front: Vite + Tailwind
npm install && npm run build

# 10. Iteruj: paginacja, scope'y, testy, obróbka obrazów w Jobach…
```

Reguła nadrzędna: **zacznij od danych** (kroki 2–5). Gdy schemat i modele są przemyślane, warstwa WWW i panel to już „tylko” prezentacja — Filament wygeneruje 80% panelu z samych definicji modeli.

---

## 11. Konta administracyjne i reset hasła

### 11.1 Tworzenie nowego konta admina

W tym projekcie **nie ma systemu ról** — administratorem jest po prostu każdy rekord w tabeli `users`. Konto tworzysz na trzy sposoby:

```sh
# a) interaktywnie — wbudowana komenda Filamenta (pyta o imię, e-mail, hasło):
docker compose exec app php artisan make:filament-user

# b) jednorazowo przez tinker:
docker compose exec app php artisan tinker
>>> App\Models\User::create(['name' => 'Piotr', 'email' => 'piotr@example.com', 'password' => 'tajne'])

# c) trwale i powtarzalnie — dopisz blok updateOrCreate w DatabaseSeeder:
docker compose exec app php artisan db:seed
```

Hasła **nie hashujesz ręcznie** — robi to cast `'password' => 'hashed'` w `app/Models/User.php` (§5.2). Dotyczy to każdej z powyższych ścieżek.

### 11.2 Uwaga produkcyjna: kto może wejść do `/admin`

Dwie rzeczy do załatwienia **przed** wystawieniem strony na świat:

1. **Zmień/usuń konto z seedera.** `admin@3dprint.local` / `password` to dane znane każdemu, kto zajrzy do repo. Pamiętaj też, że `db:seed` na produkcji **nadpisałby hasło admina** z powrotem na `password` (tak działa `updateOrCreate`) — seeduj produkcję świadomie albo owiń blok w `if (app()->isLocal())`.
2. **Ogranicz dostęp do panelu.** Filament w środowisku innym niż `local` wymaga, by model `User` implementował kontrakt `FilamentUser` — inaczej zwróci 403. **W tym projekcie już zaimplementowane** (`app/Models/User.php`): `canAccessPanel()` zwraca `true`, bo każde konto jest tu z założenia kontem admina. Gdy kiedyś pojawią się zwykli użytkownicy — zaostrz warunek:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->is_admin;   // kolumna-flaga
    // albo system ról (np. pakiet spatie/laravel-permission)
}
```

### 11.3 Reset hasła — co Laravel ma wbudowane ✅ *(zaimplementowane w tym projekcie)*

**Silnik resetu jest w frameworku**: broker `Password`, tabela `password_reset_tokens` (w domyślnej migracji users), generowanie tokenów i mail z linkiem. Czego **nie ma** w gołej aplikacji, to routingu i formularzy — dostarczają je starter kity (Breeze/Fortify), Filament albo piszesz sam.

W tym projekcie używamy **wbudowanych stron Filamenta** — `app/Providers/Filament/AdminPanelProvider.php`:

```php
return $panel
    ->id('admin')
    ->path('admin')
    ->login()            // /admin/login (własne trasy /login, /logout usunęliśmy)
    ->passwordReset()    // link „Forgot password?" + /admin/password-reset/…
    ->profile()          // /admin/profile — edycja konta + sekcja 2FA (§11.4)
    // ...
```

Poczta w dev idzie do **Mailpit** — kontenera-łapki na maile (usługa `mailpit` w `docker-compose.yml`, skrzynka: `http://localhost:8025`). Konfiguracja w `.env`:

```ini
MAIL_MAILER=smtp
MAIL_HOST=mailpit          # na produkcji: SMTP hostingu + prawdziwe dane
MAIL_PORT=1025
MAIL_FROM_ADDRESS="noreply@3dprint.local"
QUEUE_CONNECTION=sync      # patrz pułapka niżej!
```

> **Pułapka z kolejką (przerobiona na własnej skórze):** powiadomienia Filamenta (reset hasła, kody e-mail 2FA) implementują `ShouldQueue`. Przy `QUEUE_CONNECTION=database` bez uruchomionego workera (`php artisan queue:work`) maile **cicho utykają** w tabeli `jobs` i nic nie dochodzi. W małej aplikacji najprościej ustawić `sync` (wysyłka natychmiast, w trakcie żądania); przy większym ruchu — zostać przy `database` i odpalić workera jako osobny proces/kontener.

**Awaryjny reset bez poczty** (np. gdy sam zapomnisz hasła na serwerze):

```sh
php artisan tinker
>>> App\Models\User::where('email', 'piotr@example.com')->first()->update(['password' => 'nowe-haslo'])
```

### 11.4 Uwierzytelnianie dwuskładnikowe (2FA) ✅ *(zaimplementowane w tym projekcie)*

Filament 5 ma **wbudowane MFA** — nie potrzeba zewnętrznych pakietów. Włączone w panelu:

```php
->multiFactorAuthentication([
    AppAuthentication::make()->recoverable(),  // aplikacja TOTP + kody odzyskiwania
    EmailAuthentication::make(),               // jednorazowe kody na e-mail
])
```

**Jak to działa dla admina:** w `/admin/profile` jest sekcja „Multi-factor authentication", gdzie każdy użytkownik sam włącza swoją metodę — skanuje kod QR aplikacją (Google Authenticator, Aegis, 1Password…) i zapisuje kody odzyskiwania, albo aktywuje kody e-mailowe. Od tej pory po podaniu hasła logowanie wymaga drugiego składnika.

**Jak to wygląda w bazie** (migracja `add_two_factor_columns_to_users_table`, kontrakty w `app/Models/User.php`):

| Kolumna `users` | Rola |
|---|---|
| `app_authentication_secret` | sekret TOTP; `null` = metoda wyłączona. Cast `encrypted` — w bazie zaszyfrowany kluczem `APP_KEY` |
| `app_authentication_recovery_codes` | kody odzyskiwania, cast `encrypted:array` |
| `has_email_authentication` | zwykła flaga `boolean` — to jest ta „flaga w bazie": `UPDATE users SET has_email_authentication = 1` włącza kody e-mail dla konta |

Model implementuje trzy kontrakty Filamenta (`HasAppAuthentication`, `HasAppAuthenticationRecovery`, `HasEmailAuthentication`) — to zwykłe gettery/settery na powyższe kolumny, więc 2FA da się też włączać/wyłączać programowo (tinker, seeder, przyszły toggle w panelu).

Dwie opcje warte znajomości:

- `AppAuthentication::make()->recoverable()` — bez tego nie ma kodów odzyskiwania (zgubiony telefon = konto odzyskujesz tinkerem, czyszcząc `app_authentication_secret`);
- `->multiFactorAuthentication([...], isRequired: true)` — **wymusza** skonfigurowanie 2FA przy pierwszym logowaniu każdego konta; rozważ na produkcji.

Całość pokrywają testy `tests/Feature/AdminAuthTest.php` (strony auth, przekierowania, flaga e-mail 2FA, szyfrowanie sekretu) — `docker compose exec app php artisan test`.

---

## 12. Słowniczek

| Termin | Znaczenie |
|---|---|
| **ORM** | Object-Relational Mapping — biblioteka tłumacząca obiekty PHP ↔ wiersze SQL. W Laravelu: Eloquent. |
| **Active Record** | Wzorzec ORM: obiekt reprezentuje wiersz i sam się zapisuje (`$model->save()`). |
| **Migracja** | Plik PHP opisujący zmianę schematu bazy; wykonywany `php artisan migrate`. |
| **Seeder** | Klasa wypełniająca bazę danymi startowymi (`php artisan db:seed`). |
| **Fabryka** | Generator losowych rekordów do testów (`Project::factory()->count(30)->create()`). |
| **Idempotentny** | Dający ten sam efekt przy wielokrotnym uruchomieniu (np. seeder z `updateOrCreate`). |
| **Eager loading** | Dociąganie relacji z góry (`with()`), zapobiega problemowi N+1 zapytań. |
| **Route model binding** | Automatyczne pobranie modelu z bazy na podstawie parametru URL (`{project:slug}`). |
| **Mass assignment** | Masowe przypisanie pól z tablicy (`create($data)`); ograniczane przez `$fillable`. |
| **Akcesor / Mutator** | Przekształcenie wartości pola przy odczycie / zapisie w modelu. |
| **Observer** | Klasa reagująca na zdarzenia cyklu życia modelu (creating, updating, deleting…). |
| **Form Request** | Klasa walidująca dane wejściowe żądania przed dotarciem do kontrolera. |
| **API Resource** | Klasa definiująca kształt JSON zwracanego przez API. |
| **Slug** | Czytelny identyfikator w URL (`sample-bracket`), zwykle z indeksem UNIQUE. |
| **S3 / object storage** | Przechowywanie plików w chmurze za API HTTP; standard zgodny z wieloma dostawcami. |
| **Bucket** | „Kontener" na pliki w object storage. |
| **Flysystem** | Biblioteka, przez którą Laravel jednolicie obsługuje dysk lokalny, S3 itd. (`Storage`). |
| **Tinker** | REPL Laravela (`php artisan tinker`) — interaktywna konsola do eksperymentów. |

---

## 13. Co czytać dalej

Dokumentacja Laravel jest obszerna — czytaj **w tej kolejności**, resztę traktuj jako encyklopedię:

1. [Routing](https://laravel.com/docs/routing) → [Controllers](https://laravel.com/docs/controllers) → [Blade](https://laravel.com/docs/blade) — przepływ żądania (§1)
2. [Migrations](https://laravel.com/docs/migrations) → [Eloquent](https://laravel.com/docs/eloquent) → [Relationships](https://laravel.com/docs/eloquent-relationships) — baza (§2–3)
3. [Seeding](https://laravel.com/docs/seeding) → [Factories](https://laravel.com/docs/eloquent-factories) (§4)
4. [Validation / Form Requests](https://laravel.com/docs/validation) → [Eloquent Resources](https://laravel.com/docs/eloquent-resources) → [Mutators & Casts](https://laravel.com/docs/eloquent-mutators) (§5)
5. [Filesystem (Storage/S3)](https://laravel.com/docs/filesystem) (§9)
6. [Filament docs](https://filamentphp.com/docs) — **pilnuj wersji 5.x**; przykłady dla v3 z blogów często nie działają (inne przestrzenie nazw — lista różnic w `AGENTS.md`).
7. Kursy wideo: [Laracasts](https://laracasts.com) („Laravel From Scratch") oraz kanał [Laravel Daily](https://www.youtube.com/@LaravelDaily) — praktyczne wzorce i Filament.

Ćwiczenie na start: otwórz `php artisan tinker` i przejdź §3 na żywych danych tego projektu.
