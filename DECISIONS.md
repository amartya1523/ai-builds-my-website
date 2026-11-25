# DECISIONS

Key choices behind **AI Builds My Website**, what I cut to ship in time, and what I would build next.

## The one rule everything is built around

> Never invent facts. If unsure, ask the user.

An LLM asked to "make a website" will happily add "serving Jaipur since 1985" or "award-winning". So the AI is never
trusted on its own. Every fact on a published page goes through four gates:

1. **Source → fact, with a quote.** The model must return `source_id` and an exact `quote` for each fact.
   `AiFactExtractor::grounded()` checks the quote really is in that source, and that every number, phone digit and
   email in the value is inside the quote. Facts that fail are dropped (and counted in the UI).
2. **Trust levels.** Owner answers > owner's own files/links > the map listing the owner confirmed > web search.
   Web-search facts are only hints: they are never used until the owner clicks "Yes, use it".
3. **Conflicts become questions.** If two trusted sources disagree on a single-valued field (phone, email, address,
   hours) the app does not pick; it asks, and blocks generation until answered (`FactResolver`).
4. **Claim guard on the copy.** After the AI writes headline/about/service text, `ClaimGuard` removes any sentence
   with a number, year, contact detail or boast ("best", "award", "since", "family-owned", …) that is not in the
   accepted facts. Removed sentences are shown to the owner, not silently hidden.

Contact details, prices and hours are **copied from facts, never written by the AI**. Prices are shown exactly as
the business wrote them.

## Key choices

| Decision | Why |
|---|---|
| **Laravel 12 + MySQL, Blade, no JS build step** | The brief asks for Laravel + MySQL. Server-rendered Blade keeps the whole flow testable with feature tests and the project runs with `composer install` only — no Node toolchain. |
| **Synchronous pipeline** | One build takes 15–60 s. A queue + polling UI adds moving parts for little gain at demo scale. The pipeline is a single service (`BuildPipeline`) so moving it to a queued job is a one-line change. |
| **Groq (`openai/gpt-oss-120b`) by default, Gemini supported** | Free tier, fast, supports JSON mode. The provider is behind an `AiClient` interface; `AI_PROVIDER=gemini` switches to Gemini, `AI_PROVIDER=none` runs with no AI at all. |
| **Two-layer prompt-injection defence** | Uploaded files are untrusted. A regex layer catches the common patterns even when the AI is down; Llama Prompt Guard 2 (on Groq) scores the rest. Flagged lines are removed **before** any text reaches the main model and shown to the owner. Hidden HTML text (`display:none`, `aria-hidden`, zero font size) is scanned too, as that is where page-level injections hide. Prompts also wrap sources in `<source>` tags and say they are data. |
| **Same-name businesses → OpenStreetMap Nominatim** | Free, key-less, returns real places with addresses and coordinates. If any place with a similar name exists in the city, the owner must pick one (or "none of these"); only then are its address/phone used. The chosen coordinates also drive the map on the published site. |
| **Web search → DuckDuckGo HTML endpoint** | No API key needed. Results are stored as a low-trust source; if the search engine rate-limits us, the build simply continues with the owner's sources. |
| **Social sites that block scraping** | Instagram/Facebook/LinkedIn usually return a login wall. We detect it, mark the source *blocked* with a plain-English next step ("paste your bio as a note"), keep whatever public preview (og:title/description) exists, and still use the link itself on the site. Google Maps links: the place name and coordinates are read from the URL even when the page cannot be fetched. |
| **Graceful failure when the AI is down** | Every AI call has a timeout, retries and an audit row (`ai_calls`). On failure the deterministic parts still work (phones, emails, JSON-LD, links), a **template writer** produces plain but correct copy, the UI shows "AI unavailable · template copy", and "Regenerate" retries later. Nothing crashes. |
| **Publishing is a snapshot** | Publish renders the page into `sites.published_html`. Edits after that stay in the draft until "Publish changes", so a half-finished edit never goes live. Public URL: `/s/{slug}`. |
| **Edits are plain forms** | Each section is JSON in MySQL; the editor shows a field per key and lists as "one per line, `name | price`". Owner edits are marked `edited by you` and are never overwritten by a full rewrite unless the owner asks. |

## Data model (MySQL)

`sites` → `sources` (every file/link/search, with status, extracted text, injection flags) → `facts` (field, value,
evidence quote, origin, status) → `questions` (identity / conflict / confirm / missing, blocking or optional) →
`sections` (typed JSON content, who wrote it, removed claims) and `ai_calls` (provider, latency, errors).

## What I cut (on purpose)

- **OCR for scanned PDFs and photos of menus.** Scanned PDFs are detected and the owner is asked to type the details.
- **Excel (.xlsx) price lists.** CSV/TXT/PDF are supported; xlsx needs PhpSpreadsheet and was not worth the weight.
- **Custom domains, auth and multi-user accounts.** One local owner; public sites live under `/s/{slug}`.
- **Background queue + live progress bar.** A loading overlay covers the 15–60 s build instead.
- **Image understanding.** Photos go to the gallery and the hero, but the AI does not describe them (it could invent).
- **Themes picker.** One polished, responsive template with an accent colour.

## What's next

1. Queue the pipeline (Laravel Horizon) and stream progress per source.
2. Google Places API (with the owner's consent) for richer, structured listing data and reviews the owner selects.
3. OCR (Tesseract or Gemini vision) with the same quote-grounding rule.
4. Section-level version history with one-click undo, and an SEO checklist.
5. Custom domains + automatic HTTPS, WhatsApp click-to-chat, and a lead form that emails the owner.
6. An evaluation set of ~50 real businesses to measure "invented fact" rate per prompt/model change.
