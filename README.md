# BM Knowledge Modules

This directory groups two related Drupal 11 custom modules:

- **bm_knowledge_ai** — Headless knowledge layer that normalizes content into KnowledgeItems, aggregates pluggable adapters (help, nodes, future sources), and attaches taxonomy/context metadata for AI-safe consumption.
- **bm_help_ai** — Help-specific adapter and UI built on bm_knowledge_ai. It aggregates help topics and legacy `hook_help()` entries, attaches taxonomy metadata, and provides the `/help/ai` interface with off-canvas editing/display.

Repository path: `web/modules/custom/bm_knowledge_modules/`.

Both modules are decoupled: bm_knowledge_ai is UI-agnostic, while bm_help_ai remains the help consumer. Keep new work aligned with this separation (adapters in bm_knowledge_ai, help UI in bm_help_ai).
