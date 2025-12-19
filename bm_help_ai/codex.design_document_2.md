# Codex Instructions — bm_help_ai Taxonomy + Hierarchy Plan

## Use the instructions from document bm_help_ai/codex.md to process this document.

Architecture plan for enhancing context/UX by introducing a taxonomy-driven hierarchy and sidebar navigation for help discovery. Applies only to `bm_help_ai`.

- Execute the steps in this document,
- after a step is completed: update this document `##` topic with a `✅` mark.

## Objectives
- ✅ Organize help into browsable hierarchies (topics + module help).
- ✅ Provide term-based filtering for unrelated help items.
- ✅ Preserve existing aggregation services and AI stubs; add classification as an orthogonal layer.
- ✅ Keep deterministic fallbacks when taxonomy is missing or incomplete.

## Scope & Constraints
- No external services; Drupal 11 + PHP 8.3 only.
- No schema changes to core help systems; classification is additive.
- Must respect existing access (`access administration pages`) and future role-based filtering.
- Avoid JS; server-rendered tree + filters.

## Data Model ✅
- ✅ **Vocabulary**: `bm_help_ai_help_topics` (machine name) used for hierarchy.
  - Supports nested terms; terms may be reused as tags and in hierarchy.
  - Optional term fields: `display_name` (string), `description` (formatted), `weight`.
- ✅ **Help topic metadata import**:
  - Source: help topic Twig front matter (`label`, `top_level`, `related`) and file mtime.
  - Vocabulary for metadata storage: `help_topics_metadata` with term fields:
    - `help_topic_timestamp` (datetime of the help file on import)
    - `help_topic_type` (list: `help_topic`, `.module`, `wiki`)
    - `help_topic_label` (string)
    - `help_topic_top_level` (boolean/flag)
    - `help_topic_related` (string or entity reference list, depending on implementation choice)
    - `help_topic_status` (list: `current` = file present, `not found`, `updated` = file present and newer than stored)
  - Extraction: parse Twig front matter for help topics; parse module `.module` help metadata; map wiki imports to `help_topic_type: wiki`.
  - Status evaluation compares stored `help_topic_timestamp` to file mtime; mark as `updated` when file is newer.
- ✅ **Classification mapping** (config entity):
  - ID: help item ID (`help_topic` plugin ID or `hook_help.{module}`).
  - Fields: `terms` (term reference list), `primary_term` (single term), `manual_order` (int).
  - Source metadata preserved (e.g., `source`, `module`) for display/audit.
- **Autodiscovery** (optional, non-blocking):
  - Help topic definitions may expose `bm_help_ai_terms` to seed mappings on cron/install, but manual config overrides.
  - Hook-based help has no metadata; relies on manual mapping.

## Services & Responsibilities ✅
- ✅ `HelpAggregationService`
  - Extend normalized items to include `terms` (term IDs) and `primary_term` when available.
  - Remains source-of-truth for help data; uses `HelpClassificationService` to attach taxonomy (which now extends the shared `bm_knowledge_ai` classification service).
- ✅ `HelpClassificationService` (new)
  - Loads mappings for a list of help IDs; resolves term entities.
  - Provides helper: `getTree()` returning a term tree shaped for sidebar (nested arrays with labels/links/counts).
  - Caches per language + role-aware contexts (cache contexts: `languages:language_interface`, `user.roles`).
- ✅ `HelpContextService`
  - Optionally expose selected term from request (e.g., `?tid=123`) to inform filtering.
- `HelpAiRelevanceService`
  - Accepts pre-filtered items; may later use taxonomy as a signal. Default behavior unchanged.

## UI/UX
- ✅ **Sidebar navigation**
  - Left column details element or vertical navigation rendering term tree.
  - Links to `/help/ai?tid={term_id}`; server filters lists by selected term (including descendants).
  - Shows counts per term (items in subtree) for quick scanning.
- ✅ **Main content**
  - Topics/Module Overviews/Situation-specific sections respect active term filter:
    - If `tid` present, show items tagged with term or descendant; otherwise show all.
    - Clear “filter badge” + “Reset filter” link to base route.
  - Maintain current AI placeholder section unchanged; add copy noting taxonomy-based filtering above.
- **Empty states**
  - If no items for selected term, display guidance to select another term or clear filter.
  - If taxonomy not configured, render current UI unchanged with a notice “No taxonomy configured; showing all help.”

## Routing & Parameters ✅
- Keep route `/help/ai`.
- Accept optional query `tid` (integer). Validation: term exists in vocabulary; otherwise ignore.

## Storage & Admin Workflow
- Provide config form (future) to manage mappings:
  - List help items with autocomplete term assignment.
  - Batch operations: assign term to selected items.
- Provide Drush command (future) to import mappings from YAML (e.g., `bm_help_ai.classification.yml`) for deployability.

## Caching & Performance
- Cache the term tree and classified items (per language + role contexts).
- Invalidate on:
  - Taxonomy term CRUD in the vocabulary.
  - Config entity CRUD for classifications.
  - Module enable/disable (affects available help).
  - Keep list rendering pageless; keep item descriptions trimmed as today.

## Testing Strategy (incremental)
- ✅ Unit:
  - Classification mapping merges into normalized items.
  - Term-tree builder returns expected nesting/counts.
  - Filter helper returns items for term + descendants.
- Kernel (future):
  - Vocabulary creation and term tree generation.
  - Query param `tid` filters rendered form output.

## Detailed Implementation Steps
- ✅ **Vocabulary & fields**
  - Create vocabularies `bm_help_ai_help_topics` and `help_topics_metadata`.
  - Add fields (storage + form/view displays) on `help_topics_metadata`:
    - `help_topic_timestamp`: datetime.
    - `help_topic_type`: list (allowed values: `help_topic`, `.module`, `wiki`).
    - `help_topic_label`: plain string.
    - `help_topic_top_level`: boolean.
    - `help_topic_related`: text or entity reference depending on chosen relation model.
    - `help_topic_status`: list (allowed values: `current`, `not found`, `updated`).
- ✅ **Metadata import pipeline**
  - Command/service reads:
    - Help topic Twig front matter (YAML section) for `label`, `top_level`, `related`.
    - File mtime for `help_topic_timestamp`.
    - `.module` hook_help metadata (module name, overview text) treated as type `.module`.
    - Optional wiki source, flagged as `help_topic_type: wiki`.
  - For each source help item:
    - Ensure a term exists in `help_topics_metadata` keyed by help ID; create/update fields.
    - Compute status:
      - If file missing -> `not found`.
      - If file present and mtime > stored timestamp -> `updated`.
      - Else -> `current`.
    - Preserve `help_topic_related` relationships; if using entity references, resolve related IDs to terms.
  - Expose Drush command (future) `bm-help-ai:import-metadata` to run the pipeline; callable service for UI button reuse.
- ✅ **Classification service**
  - Add `HelpClassificationService` to load mappings and attach term IDs to normalized help items.
  - Provide `getTree()` using taxonomy storage to assemble hierarchy with counts and URLs (with `tid` query).
  - Add helper to filter items by `tid` including descendant terms.
- ✅ **Aggregation updates**
  - `HelpAggregationService` asks `HelpClassificationService` for term data and merges `terms`/`primary_term` into each item.
  - Include optional `help_topic_type` and `help_topic_status` when available for display badges in the form.
- ✅ **Form UX**
  - Sidebar navigation uses `getTree()`; links apply `tid` query parameter.
  - Main sections filter by selected `tid`; show reset link and empty states.
  - AI placeholder text notes taxonomy-based filtering.
- **Caching/invalidations**
  - Cache contexts: `languages:language_interface`, `user.roles`, and `url.query_args:tid`.
  - Invalidate on term changes in both vocabularies, classification config changes, module enable/disable, or metadata import completion.
- **Future admin UI**
  - Config form to manage mappings (assign terms to help items).
  - Batch assign and YAML import/export for deployability.

## Open Decisions / Decisions
- Q. Whether to seed default terms (e.g., “Getting started”, “Content”, “Configuration”).
- A. Yes
- Q. Whether to allow multiple vocabularies (initially: single vocabulary to simplify UI).
- A. Yes, multiple vocabularies, with specific fields, with a common identification (help_ai_{})
- Q. Whether manual order should override taxonomy weight in situational ranking. For now, use term weight + item manual_order as secondary sort keys.
- A. Yes, it will fully need drag drop, manage parent and child within the taxonomy management.
