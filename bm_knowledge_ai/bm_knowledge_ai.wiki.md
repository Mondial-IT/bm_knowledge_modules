# BM Knowledge AI — Knowledge Layer Overview

BM Knowledge AI is a headless Drupal 11 module that standardizes how knowledge is gathered, classified, and served to AI-facing consumers. It is designed to let multiple sources (help topics, nodes, documents, external systems) coexist behind one contract, so UIs and AI tooling stay stable as sources grow.

## Core building blocks
- **KnowledgeItem** — Canonical value object carrying ID, source type/ID, title, markdown body, summary, taxonomy terms, primary term, context hints, authority level, and timestamps.
- **KnowledgeAdapterInterface** — `discover()`, `normalize()`, `getSourceType()`; stateless adapters convert raw source data to KnowledgeItems.
- **Adapter manager** — Collects tagged adapters (`bm_knowledge_ai.adapter`), aggregates KnowledgeItems, and applies classification.
- **Classification service** — Reuses existing Help vocabularies to attach taxonomy terms, track status/type/path metadata, and provide term trees/filter helpers.
- **Context service** — Captures route, parameters, roles, permissions, enabled modules, and selected term to inform relevance decisions.
- **Relevance service** — Stubbed reordering hook; deterministic today, ready for AI-assisted ranking later.

## Current usage
- `bm_help_ai` registers a `HelpKnowledgeAdapter` (help topics + `hook_help()`); its UI consumes KnowledgeItems filtered by `source_type = help`.
- `NodeKnowledgeAdapter` (in this module) ingests configured published node bundles (disabled by default until configured).

## Demo page
- Admin demo at `/admin/config/system/bm-knowledge-ai/demo` shows counts and a sample of KnowledgeItems (first 25) across adapters to illustrate what AI consumers can access.
- A configure link on the page leads to the adapter settings form.

## How to add a new adapter
1) Implement `KnowledgeAdapterInterface` in your module.  
2) Tag the service with `bm_knowledge_ai.adapter`.  
3) Emit stable IDs and source types (`node`, `document`, `external`) and include any source-specific extras in `KnowledgeItem->extra`.  
4) Map items to taxonomy terms via configuration to enable filtering and explainability.  
5) Consume items through `bm_knowledge_ai.adapter_manager` in your UI or API layer.

## Notes and forward work
- Vocabulary names remain the existing Help vocabularies; future work can introduce generic naming without changing behavior.  
- No UI is provided by this module; it is intentionally headless and reusable.
