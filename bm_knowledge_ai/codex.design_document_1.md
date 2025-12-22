BM Knowledge AI — architecture and intent (formerly codex.step4) documents the conceptual and structural generalization step that evolved `bm_help_ai` into a reusable, future-proof **`bm_knowledge_ai`** module, while preserving everything implemented and validated in steps 1–3. This document is **executed**; use it as reference for ongoing work and future adapters.

This step does **not** refactor UI first. It introduces **module boundaries, abstractions, and contracts** so that help, nodes, ERP data, and documents can all be treated identically by AI without duplicating logic.

All analysis and decisions below are grounded in the verified implementation and intent described in:

* bm_knowledge_ai/codex.design_document_1.md
* bm_knowledge_ai/codex.design_document_2.md
* bm_knowledge_ai/codex.features_1.md
* bm_knowledge_ai/codex.md

You are allowed to execute:

* `ddev drush @ziston.ddev cr`

Not allowed:
* `only files in bm_help_ai and bm_knowledge_ai directory are allowed to be changed or added.`

---

# Step 4 implementation status

- ✅ bm_knowledge_ai module scaffolded with KnowledgeItem model, adapter interface, adapter manager, and shared classification/context/relevance services.
- ✅ bm_help_ai registers HelpKnowledgeAdapter and consumes bm_knowledge_ai services; aggregation now reads from the adapter manager output.
- ✅ Legacy bm_help_ai classification/context/relevance services now extend the bm_knowledge_ai equivalents; UI behavior intentionally unchanged.
- ⏳ Taxonomy naming remains as-is; generic vocabulary naming is still conceptual only.
- ⏳ Future adapters (node/document/external) not implemented yet; bm_knowledge_ai is ready to accept them.
- ⏳ No data migration or UI redesign performed (explicitly out of scope for this step).

---

# codex.step4.md

## Generalising bm_help_ai → bm_knowledge_ai

---

## Purpose of Step 4

This step introduces a **generic knowledge aggregation and AI-readiness layer**, extracting help-specific logic out of `bm_help_ai` and placing it into a new base module:

```
bm_knowledge_ai   (generic, reusable, AI-facing)
bm_help_ai        (Drupal Help adapter on top of bm_knowledge_ai)
```

The result:

* `bm_help_ai` becomes **one adapter**, not the system
* future adapters (nodes, ERP, documents) plug in without redesign
* AI interfaces operate on **normalized knowledge**, not Drupal-specific constructs

---

## Core Architectural Shift

### From

```
bm_help_ai
 ├─ aggregation
 ├─ taxonomy
 ├─ context
 ├─ AI relevance
 └─ UI
```

### To

```
bm_knowledge_ai
 ├─ KnowledgeItem model
 ├─ Adapter contracts
 ├─ Context service
 ├─ Taxonomy & classification
 ├─ AI relevance & grounding
 └─ Query / ranking engine

bm_help_ai
 └─ HelpAdapter (help_topics + hook_help)
```

No functionality is lost. Only **ownership changes**.

---

## 4.1 Create new module: bm_knowledge_ai

### Module intent

`bm_knowledge_ai` is **headless and UI-agnostic**.

It provides:

* canonical KnowledgeItem normalization
* adapter registration
* taxonomy attachment
* context-aware filtering
* AI-safe retrieval APIs

It does **not**:

* render admin pages
* know what “help topics” are
* depend on Drupal Help APIs

---

## 4.2 Introduce the KnowledgeItem contract

Define a canonical internal structure (PHP value object or associative array for now):

```
KnowledgeItem
- id                  (string, deterministic)
- source_type          (help | node | document | external)
- source_id            (e.g. help_topic:views.overview, node:123)
- title
- body_markdown
- summary              (optional, derived)
- language
- taxonomy_terms       (array of term IDs)
- primary_term         (optional)
- context_hints        (routes, forms, modules)
- authority_level      (canonical | derived | ai)
- updated_at
```

This structure **already exists implicitly** in bm_help_ai; step 4 makes it explicit and reusable.

---

## 4.3 Adapter architecture (new)

### Adapter interface

Create a generic adapter interface in `bm_knowledge_ai`:

```
KnowledgeAdapterInterface
- discover(): iterable
- normalize(raw): KnowledgeItem
- getSourceType(): string
```

Adapters are:

* stateless
* discover → normalize
* unaware of AI or UI

---

## 4.4 Migrate existing logic into a Help Adapter

### New module boundary

Move (or duplicate first, refactor later):

| Current bm_help_ai responsibility | New home        |
| --------------------------------- | --------------- |
| Help discovery                    | HelpAdapter     |
| hook_help parsing                 | HelpAdapter     |
| help_topics parsing               | HelpAdapter     |
| metadata import logic             | HelpAdapter     |
| taxonomy attachment               | bm_knowledge_ai |

Resulting structure:

```
bm_help_ai
 └─ src/Adapter/HelpKnowledgeAdapter.php
```

`bm_help_ai` registers this adapter via a service tag.

---

## 4.5 Adapter registration and discovery

In `bm_knowledge_ai`:

* introduce an AdapterManager service
* collect adapters via service tags
* iterate adapters to build a unified KnowledgeItem collection

This replaces hardcoded help aggregation.

---

## 4.6 Taxonomy becomes generic Knowledge taxonomy

### Rename conceptually (not necessarily machine names yet)

| Current                | Generalised        |
| ---------------------- | ------------------ |
| help_topics_metadata   | knowledge_metadata |
| bm_help_ai_help_topics | knowledge_domains  |

The **model remains identical**:

* hierarchical
* multi-taxonomy ready
* attached via mapping config
* authoritative over raw sources

`bm_help_ai` continues using its existing vocabularies for now, but through the generic API.

---

## 4.7 Context service becomes KnowledgeContextService

Move / alias:

* route
* permissions
* roles
* enabled modules

This context is **adapter-agnostic** and reused by:

* help
* node knowledge
* ERP snapshots
* documents

No behavioral change, only ownership.

---

## 4.8 AI relevance service moves to bm_knowledge_ai

Current `HelpAiRelevanceService` becomes:

```
KnowledgeAiRelevanceService
```

Responsibilities stay unchanged:

* reorder
* group
* filter
* never invent

It operates on KnowledgeItems only.

---

## 4.9 bm_help_ai UI becomes a consumer

The `/help/ai` page now:

* asks bm_knowledge_ai for KnowledgeItems
* filters by `source_type = help`
* renders tables exactly as today

No UI regression.

Future UIs (search, chat, video) will reuse the same backend.

---

## 4.10 Forward compatibility: future adapters

This step explicitly enables:

### Node adapter

```
NodeKnowledgeAdapter
- bundle whitelist
- field mapping
- redaction rules
```

### Document adapter

```
DocumentKnowledgeAdapter
- file extraction
- chunking
- derived authority
```

### ERP / external adapter

```
ExternalKnowledgeAdapter
- snapshot import
- read-only
- versioned
```

No architectural changes needed after step 4.

---

## 4.11 Non-goals of this step

* No data migration yet
* No renaming of vocabularies required yet
* No AI prompts added
* No UI redesign
* No node/document adapters implemented yet

This step is **pure architecture & refactoring boundary definition**.

---

## 4.12 Completion criteria

Mark this step as complete when:

* bm_knowledge_ai module exists
* KnowledgeItem contract is defined
* Adapter interface and manager exist
* bm_help_ai registers a HelpAdapter
* Existing functionality continues to work unchanged
* No AI behavior changes

---

## Strategic Outcome

After Step 4:

* `bm_help_ai` is **no longer special**
* Help is just **one knowledge source**
* AI can reason across help, content, data, and documents
* Taxonomy remains the backbone for explainability and governance

This step turns your current strong implementation into a **platform**, not a feature.
