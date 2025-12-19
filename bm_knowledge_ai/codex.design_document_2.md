Below is **`codex.step5.md`**, written as a **validation step** for the new `bm_knowledge_ai` architecture by introducing the **first non-help adapter**: a **read-only NodeKnowledgeAdapter**.

This step is deliberately constrained: no UI, no editing, no AI prompts. Its sole purpose is to **prove that help, content, and future data sources are truly interchangeable knowledge sources**.

Status: **implemented**. NodeKnowledgeAdapter now exists (disabled by default via config), with bundle/field configuration, per-translation discovery, and KnowledgeItem normalization; no UI was added, and bm_help_ai remains unchanged.

---

# codex.step5.md

## Validate bm_knowledge_ai with NodeKnowledgeAdapter (read-only)

---

## Purpose of Step 5

This step validates that the architectural separation introduced in **Step 4** is correct and future-proof by:

* Adding a **NodeKnowledgeAdapter**
* Treating Drupal nodes as **knowledge**, not content
* Reusing **exactly the same pipelines** as help:

  * normalization
  * taxonomy attachment
  * context filtering
  * AI-readiness

If this step works cleanly, the architecture is sound.

---

## Scope & Constraints

**In scope**

* Read-only ingestion of selected nodes
* Normalization into `KnowledgeItem`
* Taxonomy attachment via existing mechanisms
* Availability through `bm_knowledge_ai` APIs

**Out of scope**

* No UI
* No node editing
* No search page
* No AI calls
* No document ingestion yet

---

## 5.1 Introduce NodeKnowledgeAdapter

Create a new adapter in `bm_knowledge_ai`:

```
src/Adapter/NodeKnowledgeAdapter.php
```

This adapter must implement:

```
KnowledgeAdapterInterface
```

---

## 5.2 Adapter responsibility definition

### NodeKnowledgeAdapter must:

* Discover nodes based on **explicit configuration**
* Normalize nodes into `KnowledgeItem`
* Never assume presentation intent
* Never expose unpublished content
* Never bypass access checks

### NodeKnowledgeAdapter must NOT:

* Render nodes
* Depend on themes or view modes
* Infer taxonomy automatically
* Replace Views or Search

---

## 5.3 Configuration (minimal, required)

Introduce a config object:

```
bm_knowledge_ai.node_adapter.yml
```

Minimum fields:

```
enabled: true
bundles:
  - article
  - page
fields:
  title: title
  body: body
language_behavior: per_translation
authority_level: canonical
```

Rules:

* Only configured bundles are ingested
* Only configured fields are read
* Missing fields must fail gracefully

---

## 5.4 Discovery logic

Discovery must:

* Load nodes via entity query
* Respect:

  * published status
  * access checks
  * language availability
* Return **raw node references**, not rendered output

Discovery result is an iterable of node IDs + langcodes.

---

## 5.5 Normalization into KnowledgeItem

For each node + language:

```
id: node:{nid}:{langcode}
source_type: node
source_id: node:{nid}
title: node title
body_markdown: normalized body text
language: langcode
updated_at: node changed timestamp
authority_level: canonical
```

### Body normalization rules

* Strip HTML safely
* Convert to markdown
* Remove navigation, metadata, or layout artifacts
* No summaries generated yet

---

## 5.6 Taxonomy attachment (reuse existing services)

Node knowledge **must use the same taxonomy pipeline** as help:

* Classification service attaches:

  * `taxonomy_terms`
  * `primary_term`
* No special logic for nodes
* No automatic term guessing

If no taxonomy mapping exists:

* KnowledgeItem remains valid
* Deterministic fallback applies

---

## 5.7 Context hints (optional, but supported)

If present, attach:

* content type
* route hint (`entity.node.canonical`)
* module hint (`node`)

These hints must not affect authority.

---

## 5.8 Exposure via bm_knowledge_ai API

Ensure that:

```
KnowledgeRepository::getItems()
```

returns:

* help knowledge
* node knowledge
* in one unified iterable

Consumers must be able to filter by:

```
source_type === 'node'
```

---

## 5.9 Validation checks (manual)

After implementation, validate:

1. bm_help_ai still works unchanged
2. KnowledgeItem collection now includes nodes
3. Taxonomy filtering works identically
4. Context service does not break
5. No UI changes are required

This step is **backend-only validation**.

---

## 5.10 Explicit non-goals (important)

* No admin UI for selecting nodes
* No per-node overrides
* No search page
* No AI summaries
* No embeddings
* No permissions UI

All of that comes later.

---

## 5.11 Why nodes first (design rationale)

Nodes are:

* structured
* permission-aware
* multilingual
* first-class Drupal entities

If nodes work cleanly as knowledge:

* documents will work
* ERP snapshots will work
* external APIs will work

This is the **acid test** of the architecture.

---

## 5.12 Completion criteria

Mark Step 5 as complete when:

* NodeKnowledgeAdapter exists
* Configuration-driven bundle selection works
* Nodes appear as KnowledgeItems
* Taxonomy and context apply identically
* No regressions in bm_help_ai
* No UI changes were required

---

## Strategic Outcome

After Step 5:

* `bm_knowledge_ai` is **proven generic**
* Help is no longer privileged
* Knowledge becomes a **first-class system concern**
* AI enablement is now a safe, incremental choice

---

### Next logical step (not executed yet)

**Step 6 – DocumentKnowledgeAdapter (PDF/DOCX, derived authority)**
This will validate chunking, versioning, and non-canonical sources.

When you are ready, we can formalize that step as well.
