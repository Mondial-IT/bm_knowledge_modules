# bm_help_ai — Help Aggregation and AI-Assisted Relevance Architecture

## Purpose

This document describes the architectural strategy and design intent behind the `bm_help_ai` module.

The goal of this module is **not** to replace existing Drupal help systems, but to **aggregate, orchestrate, and enhance** them—optionally using AI—while preserving existing contracts, editorial intent, and fallback behavior.

The module provides a unified help experience that:
- respects existing hardcoded help sources
- improves relevance through situational awareness
- introduces AI strictly as an enhancement layer, never as an authority

---

## Background and Problem Statement

Drupal currently exposes multiple help mechanisms:

- Help Topics (`help_topics/*.yml`)
- Legacy module help via `hook_help()`
- Implicit module descriptions in `.info.yml`
- Contextual help attached to forms and routes

These systems:
- serve different audiences
- are discovered through different entry points
- lack a single, situationally-aware aggregation surface

As a result:
- users must navigate fragmented help surfaces
- relevant help exists but is not surfaced at the right time
- enable-time and post-action help are disconnected

This module addresses **composition and relevance**, not content creation.

---

## Core Design Principles

1. **Authoritative help remains static**
- All canonical help originates from existing systems.
- AI never invents or replaces help content.

2. **Aggregation over replacement**
- Existing help systems remain intact.
- The module re-composes them into a single experience.

3. **Explicit metadata over inference**
- Help placement is opt-in and declarative.
- No automatic ownership guessing.

4. **AI as relevance and synthesis, not authority**
- AI may sort, group, filter, or summarize.
- AI output is clearly separated and labeled.

5. **Graceful degradation**
- The system must function fully without AI enabled.

---

## High-Level Architecture

The module provides a **custom help form**, conceptually similar to `/admin/help`, but extended.

The form is divided into **four sections**:

---

### 1. Topics

- Source: `help_topics`
- Behavior:
  - Collected and rendered as-is
  - Preserves hierarchy and translations
- Purpose:
  - Conceptual and task-oriented documentation

This section mirrors the existing Help Topics UI.

---

### 2. Module Overviews

- Sources:
  - `hook_help()`
  - Help Topics explicitly marked as suitable for module overview display
- Behavior:
  - Flat, module-oriented listing
  - No automatic discovery; requires explicit opt-in metadata
- Purpose:
  - High-level understanding of enabled modules

This section maintains backward compatibility while allowing modern help topics to participate.

---

### 3. Situation-Specific Help

- Sources:
  - Help Topics
  - `hook_help()` entries
- Selection:
  - Based on current context (route, form, permissions, recent actions)
  - AI-assisted filtering, grouping, or ordering
- Constraints:
  - AI may not introduce new help items
  - At least a minimal fallback set is always shown

Purpose:
- Answer “what is relevant right now?” without replacing canonical docs.

---

### 4. AI Interpretation and Prompts (Optional)

- Clearly separated section
- Allows:
  - AI-generated summaries
  - Explanations based on authoritative inputs
  - Admin-defined prompt lenses (keywords, domains)
- Restrictions:
  - AI output is non-authoritative
  - Sources must be traceable
  - Prompts cannot override system constraints

Purpose:
- Provide situational synthesis and explanation, not doctrine.

---

## Role of AI

AI is used only for:
- relevance ranking
- grouping
- summarization
- contextual explanation

AI is never used for:
- creating canonical documentation
- mutating system state
- hiding all existing help
- replacing human-authored intent

AI must operate under strict, predefined system instructions.

---

## Governance and Safety

- AI functionality is optional and configurable
- All AI features have deterministic fallbacks
- Help sources are auditable and inspectable
- Admin-defined prompts are scoped and validated

---

## Expected Benefits

- Improved discoverability of existing help
- Reduced cognitive load for administrators
- Situational and temporal relevance
- Future-ready integration point for Drupal AI

---

## Non-Goals

- Replacing `/admin/help`
- Generating documentation automatically
- Introducing a chatbot-first UI
- Modifying existing help APIs

---

## Summary

`bm_help_ai` is an orchestration layer that:
- respects Drupal’s historical help systems
- enhances relevance through aggregation
- introduces AI conservatively and transparently
- aligns with Drupal’s long-term architectural values
