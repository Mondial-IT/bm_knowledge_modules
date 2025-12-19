# Codex Instructions — Generate bm_help_ai Module

## Use the instructions from document bm_help_ai/codex.md to process this document.

## Environment

- Platform: Ubuntu
- Local setup: ddev
- Drupal version: 11.x
- PHP version: 8.3
- Module type: Custom contrib-style module
- Name: bm_help_ai

Do not generate installation instructions. Assume ddev and Drupal are already running.

---

## Goal

Generate the initial implementation of a Drupal module named `bm_help_ai` that:

- Provides a new admin help form
- Aggregates existing Drupal help systems
- Is architected for optional AI-assisted relevance
- Contains no hard dependency on AI services

Focus on structure, APIs, and extensibility rather than UI polish.

---

## Functional Requirements

### ✅ 1. Module Skeleton

Create:
- `bm_help_ai.info.yml`
- `bm_help_ai.module`
- `bm_help_ai.routing.yml`
- `bm_help_ai.links.menu.yml`
- `src/Form/HelpAiForm.php`
- `src/Service/HelpAggregationService.php`
- `src/Service/HelpContextService.php`
- `src/Service/HelpAiRelevanceService.php` (stub, no AI calls)
- `bm_help_ai.services.yml`

---

### ✅ 2. Admin Route

- Path: `/help/ai`
- Menu placement: Administration → Help
- Access: `access administration pages`

---

### ✅ 3. Help Aggregation Logic

Implement a service that can:

- Collect Help Topics via existing Drupal APIs
- Collect legacy help via `hook_help()`
- Normalize both into a common internal structure:
  - id
  - title
  - source
  - module
  - description
  - tags (optional)

No AI logic here.

---

### ✅ 4. Section Rendering

The form must render four sections:

1. **Topics**
  - All help topics
  - No filtering

2. **Module Overviews**
  - Content from `hook_help()`
  - Plus help topics with an explicit metadata flag (stub this flag)

3. **Situation-Specific Help**
  - Uses context service to collect:
    - current route
    - user roles
    - enabled modules
  - Passes candidates to relevance service
  - Relevance service returns reordered subset

4. **AI Assistance (Placeholder)**
  - Static textarea
  - Placeholder text explaining AI-assisted help
  - No actual AI calls

---

### ✅ 5. Context Service

Create a service that exposes:

- current route name
- route parameters
- user roles
- permissions
- enabled modules

This service must be injectable and testable.

---

### ✅ 6. AI Relevance Service (Stub)

- Accepts:
  - help item list
  - context object
- Returns:
  - reordered list
- Default behavior:
  - return input unchanged

Add clear TODO markers for AI integration.

---

### ✅ 7. Coding Constraints

- Use Drupal 11 APIs only
- Use dependency injection
- No static service calls in business logic
- Original Step 1 avoided JS/external libs; current implementation already uses the existing `bm_main` DataTables library and bm_help_ai admin assets. Do not introduce new libraries without explicit instruction.
- No actual AI integration

---

## Non-Goals

- No chatbot UI
- No persistence of AI output
- No modification of core help systems
- No external API calls

---

## Output Expectations

Generate:
- Clean, idiomatic Drupal 11 code
- PHP 8.3 compatible
- Clear comments explaining extension points
- No unnecessary boilerplate

The result should compile, install, and display a working help aggregation form with placeholder AI logic.

---

## Current Implementation Notes (Step 1–4 status)

This module has moved beyond the initial “no JS / no external libs” constraint to satisfy the UX goals in `codex.step2.md` + `codex.step3.md` (DataTables sorting/filtering and off-canvas editing). Use these notes when continuing development.

### Knowledge layer integration

- `bm_help_ai` now depends on `bm_knowledge_ai` and exposes help content through a `HelpKnowledgeAdapter` registered with `bm_knowledge_ai.adapter_manager`.
- Classification/context/relevance services in `bm_help_ai` extend or alias the shared implementations in `bm_knowledge_ai`; avoid duplicating logic across the two modules.
- Any new aggregation should use the adapter manager output (arrays from `KnowledgeItem::toArray()`) to stay consistent with the new architecture.

### Routes & Admin Entry Points

- Help overview UI (front-end themed, permissioned): `bm_help_ai.help_ai` at `/help/ai`
- Metadata import/settings form: `bm_help_ai.settings` at `/admin/config/system/bm-help-ai`
- Taxonomy landing page (Structure → Taxonomy → Help AI): `bm_help_ai.taxonomy_help_ai` at `/admin/structure/taxonomy/help-ai`
- Term display route used for sidebar dialogs: `bm_help_ai.taxonomy_term_display` at `/help/ai/taxonomy/{taxonomy_term}`

### Data Model (Two Vocabularies)

- Hierarchy vocabulary: `bm_help_ai_help_topics` (intended for “browse by hierarchy”).
- Metadata vocabulary: `help_topics_metadata` (1 term per discovered help source item).

Metadata terms are keyed by `taxonomy_term.name` set to the deterministic help item ID:

- Help topic plugin: `{plugin_id}` (e.g. `block.placing`)
- Module help entry: `hook_help.{module}` (e.g. `hook_help.node`)

Metadata fields on `help_topics_metadata` (field machine names):

- `field_help_topic_timestamp` (datetime)
- `field_help_topic_type` (`help_topic` | `.module` | `wiki`)
- `field_help_topic_label` (string)
- `field_help_topic_top_level` (boolean)
- `field_help_topic_related` (string_long; YAML `related` flattened)
- `field_help_topic_status` (`current` | `not_found` | `updated`)
- `field_help_topic_path` (string_long; absolute file path when discovered)

### Import Pipeline (How Metadata Gets Filled)

- Implemented in `src/Service/HelpMetadataImportService.php`.
- Triggered via the UI button on `HelpAiSettingsForm` (`/admin/config/system/bm-help-ai`).
- The importer:
  - iterates help topic plugin definitions and parses optional Twig front matter (`--- yaml ---`) for `label`, `top_level`, `related`
  - iterates enabled modules and creates `.module` entries (`hook_help.{module}`), capturing the `.module` file mtime/path
  - sets `field_help_topic_status` by comparing stored timestamp vs file mtime
  - deletes stale metadata terms if the corresponding help item is no longer discovered
- Note: `field_help_topic_path` is ensured by the importer. On older installs, running the importer is the fastest way to get this field created if it wasn’t present yet.

### Table UI + Actions (Edit/Display in Sidebar)

- `/help/ai` renders three tables: Topics, Module overviews, Situation-specific.
- Columns: Title, Description, Source, Module, Type, Status, File, Actions.
- Actions:
  - “Edit” opens `entity.taxonomy_term.edit_form` in Drupal off-canvas (requires `use-ajax` + core dialog libraries).
  - “Display” opens `bm_help_ai.taxonomy_term_display` in off-canvas.

### Classification vs Metadata: Important Continuation Detail

`HelpClassificationService` merges:

- manual classification config (`config: bm_help_ai.classification`) intended to reference hierarchy term IDs, and
- discovered metadata terms from `help_topics_metadata`.

Currently, discovered metadata contributes a `terms` entry containing the metadata term ID (so the UI can find the “edit/display” target term). For future work, consider separating “hierarchy terms” from “metadata term id” (e.g. `metadata_tid`) to avoid mixing two vocabularies into one `terms` array.

### JS/CSS Libraries (Where UX Behavior Comes From)

- `bm_help_ai/ai_admin` (`bm_help_ai.libraries.yml`) attaches:
  - core AJAX + dialog off-canvas libraries for sidebar actions
  - admin CSS/JS (`css/ai-admin.css`, `js/ai-admin.js`)
- DataTables: delivered via `bm_main` and initialized for tables that have `data-bm-help-ai-table`.
- Off-canvas width persistence: implemented client-side via `js/ai-admin.js` using `sessionStorage`.

### Tests

Unit tests exist under `tests/src/Unit/` covering aggregation/context/relevance/classification behavior. Kernel/functional tests are explicitly deferred in `tests/TESTING_STRATEGY.md`.
