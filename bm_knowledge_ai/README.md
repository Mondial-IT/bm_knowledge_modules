# BM Knowledge AI

Headless knowledge layer that normalizes content into `KnowledgeItem` objects, collects items via pluggable adapters, attaches taxonomy metadata, and exposes context/relevance services for downstream consumers (e.g., `bm_help_ai`).

## What it does
- Defines the `KnowledgeItem` value object and `KnowledgeAdapterInterface` for source-agnostic ingestion.
- Provides an adapter manager that aggregates items from tagged adapters.
- Ships generic services for taxonomy classification, context capture, and AI relevance scaffolding.
- Lets consumers request items by source type (e.g., `help`) without knowing implementation details.

## Current adapters
- `bm_help_ai` registers `HelpKnowledgeAdapter` (help topics + `hook_help()`), demonstrating the pattern.
- `NodeKnowledgeAdapter` (in this module) ingests configured published node bundles (disabled by default; see config below).

## Key services
- `bm_knowledge_ai.adapter_manager` — gathers items from tagged adapters.
- `bm_knowledge_ai.classification` — attaches taxonomy metadata (reuses existing Help vocabularies).
- `bm_knowledge_ai.context` — captures route, roles, permissions, enabled modules, and selected term.
- `bm_knowledge_ai.relevance` — stub for relevance ordering (no AI calls yet).

## Notes
- Taxonomy vocabularies still use the existing Help IDs; future work can introduce generic naming without behavioral changes.
- Additional adapters (nodes, documents, external data) can be added by tagging services with `bm_knowledge_ai.adapter`.

## Node adapter configuration
- Config key: `bm_knowledge_ai.node_adapter` (config/install provided; disabled by default).
- Fields:
  - `enabled`: toggle adapter on/off.
  - `bundles`: whitelist of node bundles to ingest.
  - `fields.title`/`fields.body`: mapped field names (body used for normalized text).
  - `language_behavior`: `per_translation` to emit each translation; otherwise defaults to the base entity language.
  - `authority_level`: stored on KnowledgeItems (defaults to `canonical`).
