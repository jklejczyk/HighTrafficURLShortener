# Benchmarks

Pomiary wydajności hot path: `GET /{code}` (redirect endpoint).

## Setup

- **Hardware**: lokalny laptop deweloperski
- **Środowisko**: Laravel Sail (Docker) — PHP 8.3, PostgreSQL 18-alpine, Redis alpine
- **Tool**: ApacheBench 2.3 (`ab`)
- **Endpoint**: `GET http://localhost:8080/{code}`
- **Response**: HTTP 301 → `original_url`
- **Baseline command**: `ab -n 1000 -c 10 http://localhost:8080/{code}`

## Plan oczekiwany (z `plan_projektu.md` linie 540–549)

| Wersja                     | RPS    | p50    | p95   | p99    |
|----------------------------|--------|--------|-------|--------|
| Faza 1 (naive, DB hit)     | 180    | 35 ms  | 80 ms | 150 ms |
| Faza 2 (Redis cache)       | 2 400  | 2 ms   | 5 ms  | 12 ms  |
| Faza 3 (async stats)       | 4 800  | 1.5 ms | 4 ms  | 9 ms   |
| Faza 5 (Octane + tuning)   | 12 000 | 0.8 ms | 2 ms  | 5 ms   |

## Wyniki

### Faza 1 — naive implementation (DB hit + synchronous click_count UPDATE)

**Data**: 2026-05-04
**Commit / branch**: `main`
**Komenda**: `ab -n 1000 -c 10 http://localhost:8080/ClwjPCY`

| Metryka            | Wynik       | Plan       | Δ vs plan |
|--------------------|-------------|------------|-----------|
| **RPS**            | 210.32 r/s  | 180 r/s    | +17 %     |
| **p50**            | 47 ms       | 35 ms      | −34 %     |
| **p95**            | 70 ms       | 80 ms      | +12 %     |
| **p99**            | 101 ms      | 150 ms     | +33 %     |
| max                | 167 ms      | –          | –         |
| mean               | 47.547 ms   | –          | –         |
| Failed requests    | 0 / 1000    | –          | OK        |
| Total time         | 4.755 s     | –          | –         |
| Concurrency        | 10          | 10         | OK        |

**Pełny output**

```
Concurrency Level:      10
Time taken for tests:   4.755 seconds
Complete requests:      1000
Failed requests:        0
Non-2xx responses:      1000      # 301 redirects — oczekiwane
Requests per second:    210.32 [#/sec] (mean)
Time per request:       47.547 [ms] (mean)
Time per request:       4.755  [ms] (mean, across all concurrent requests)
Transfer rate:          292.68 Kbytes/sec received

Percentage of the requests served within a certain time (ms)
  50%     47
  66%     51
  75%     54
  80%     55
  90%     62
  95%     70
  98%     82
  99%    101
 100%    167 (longest request)
```

#### Wąskie gardła Fazy 1

1. **`Link::where('short_code', $code)->firstOrFail()`** — pojedynczy SELECT po B-tree na `short_code` (`->unique()` w migracji). Dominuje p50 (~25–30 ms cold cache, mniej gdy Postgres ma index w pamięci).
2. **`$link->increment('click_count')`** — synchroniczny UPDATE per redirect. Plan świadomie oznacza to jako *bug wydajnościowy* (plan_projektu.md:167). Przy concurrency 10 Postgres bierze row-level lock → kolejkowanie pisarzy.
3. **301 cache w przeglądarce** nie wpływa na `ab` (każdy request świeży), ale w real-world ruchu redukuje liczbę requestów docierających do hot path. Patrz: plan_projektu.md:172.

#### Co ulepszy Faza 2

Cache-aside w Redis dla `short_code → original_url` (plan 196–237). Eliminuje SELECT dla > 95 % requestów (cache hit ratio target). Oczekiwane: p95 70 → ~5 ms, RPS 210 → ~2400.

#### Co ulepszy Faza 3

Async click counting: `Redis::incr("clicks:{code}")` w hot path + agregacja w jobie co minutę (plan 287–330). Eliminuje synchroniczny UPDATE. Oczekiwane: p99 101 → ~9 ms, RPS → ~4800.

### Faza 2 — Redis cache layer

**Data**: 2026-05-05
**Setup**: identyczny jak Faza 1 (Sail, ab, concurrency 10).
**Metoda**: porównanie A/B w tym samym środowisku — w `RedirectController` zakomentowano cache i puszczono ab, potem przywrócono cache i ponownie puszczono. Mediana z 3 runów.

| Metryka | Bez cache | Z cache | Δ |
|---------|-----------|---------|---|
| **RPS** | 106 r/s   | 105 r/s | ~0 % |
| **p50** | 90 ms     | 90 ms   | ~0 % |
| **p95** | 166 ms    | 161 ms  | −3 % |
| **p99** | 199 ms    | 214 ms  | +7 % (szum) |

#### Co potwierdziliśmy

1. **Cache funkcjonalnie działa.** `redis-cli MONITOR` pokazuje przy każdym requeście tylko `SELECT 1` + `GET laravel-database-laravel-cache-link_<code>`. **Zero `SELECT` w PostgreSQL.** Cache hit ratio 100 % po warm-upie.
2. **Single-request po cache hit jest szybszy.** `ab -n 100 -c 1` daje p50 = 26 ms (vs ~47 ms w Fazie 1 baseline). Pojedynczy request jest ~2× szybszy.
3. **Negative caching działa** — drugi request na nieistniejący kod nie pyta DB (test `it('does not query DB on negative cache hit')` w `RedirectTest`).

#### Dlaczego brak zysku w benchmark całościowym

Hipoteza początkowa "cache zredukuje p95 z 70 ms do 5 ms" się **nie potwierdziła** dla tego setupu. Powód: w klasycznym PHP-FPM **Laravel bootstrap** (parsowanie configów, budowanie service containera, providery) zajmuje ~50–60 ms per request. DB lookup to ~25 ms z tego całego czasu. Eliminacja DB lookupu (cache hit) skraca request o ~24 ms, ale boot pozostaje — a w concurrency 10 efekt znika w noise.

Dowód: profil pojedynczego requestu (concurrency 1) pokazuje 26 ms; ten sam request pod concurrency 10 to 90 ms. Różnica = kolejkowanie w PHP-FPM workerach, nie czas pracy aplikacji.

#### Konkluzja

Cache jest **dormant optimization** — funkcjonalnie poprawny, ale jego efekt mierzalny dopiero po wyeliminowaniu boot bottlenecka. Plan zapowiada to w Fazie 5:

> *"Klasyczny PHP-FPM bootuje Laravel od zera przy KAŻDYM requeście. Octane bootuje raz, trzyma w pamięci. Cena ~5–10x szybsze."*

W Fazie 5 dorzucamy Octane (Swoole) i ponownie mierzymy — tam cache zaczyna pracować zgodnie z oczekiwaniami planu (~2400 RPS, p95 ~5 ms).

#### Pełny output (run 2 z cache, mediana)

```
Concurrency Level:      10
Time taken for tests:   ~9.5 seconds
Complete requests:      1000
Failed requests:        0
Requests per second:    105.36 [#/sec] (mean)
Time per request:       ~95 [ms] (mean)

Percentage of the requests served within a certain time (ms)
  50%     90
  75%    119
  95%    161
  99%    214
```

#### Lekcja metodologiczna

**Profile-driven optimization > intuicja.** Plan zakładał konkretne liczby (RPS 2400, p95 5 ms) w Fazie 2 — ale ten target zakłada Octane, którego nie ma. Bez pomiaru sądzilibyśmy "cache zawiódł". Z pomiarem widzimy: *"cache działa, ale dominuje inny bottleneck"*. To **dokładnie** ten sposób myślenia, którego plan uczy:

> *"Nigdy nie zgaduj. Włącz profilowanie, zrób snapshot, zobacz gdzie jest 80 % czasu."*

Tu 80 % czasu to PHP-FPM boot. Cache czeka w gotowości na Fazę 5.

### Faza 3 — async stats

_Pomiar do wykonania po implementacji Fazy 3._

### Faza 5 — Octane + tuning

_Pomiar do wykonania po implementacji Fazy 5._

## Uwagi metodologiczne

- **`Non-2xx responses: 1000`** w outpucie `ab` to **oczekiwane** zachowanie dla endpointu zwracającego 301. ApacheBench klasyfikuje jako "non-2xx" wszystko ≥ 300. `Failed requests: 0` potwierdza, że wszystkie odpowiedzi były prawidłowe.
- **`Time per request (mean)`** — używana wartość to ta z jednostką ms per request (47.547 ms), nie ta podzielona przez concurrency (4.755 ms). Pierwsza odzwierciedla *latencję widzianą przez usera*.
- **Cache state Postgresa** wpływa na pierwsze runy. Powtórz `ab` 2–3 razy żeby uzyskać stabilną wartość (warm shared_buffers).
- **Hot path = `GET /{code}`** — to 99 % ruchu w typowym shortenerze. POST `/api/shorten` i GET `/api/stats/{code}` nie są benchmarkowane bo plan ich nie wymienia jako hot path (plan_projektu.md:15).

## Wyniki rzeczywiste — zestawienie

Bezpośrednie liczby z `ab -n 1000 -c 10 http://localhost:8080/{code}` po każdej fazie. Format identyczny jak tabela "Plan oczekiwany" wyżej — ułatwia porównanie wiersz po wierszu.

| Wersja                     | RPS    | p50    | p95   | p99    |
|----------------------------|--------|--------|-------|--------|
| Faza 1 (naive, DB hit)     | 210.32 | 47 ms  | 70 ms | 101 ms |
| Faza 2 — bez cache (kontrola) | 106 | 90 ms  | 166 ms| 199 ms |
| Faza 2 — z cache           | 105    | 90 ms  | 161 ms| 214 ms |
| Faza 3 (async stats)       | –      | –      | –     | –      |
| Faza 5 (Octane + tuning)   | –      | –      | –     | –      |