# Codex Instructions — Generate bm_help_ai Module

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

- Path: `/admin/help/ai`
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
- No JavaScript
- No external libraries
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
