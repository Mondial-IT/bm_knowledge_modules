
This document contains the meta development instructions to be executed by codex at the user direction.

## work process

- Documents with a format `codex.{title}.md` contain instructions to execute by codex.
- These instructions are to be executed complying to the documented general agent instructions in the `git_root\codex\agents\*.md` files.

- Each feature contains a markdown checkbox `* [ ]` accompanied by a sequence number: example `* [ ] Feature 1.0`
- The user instructs (codex prompt) which feature (nr) to build next or to build 'all unchecked' features. Example: `execute open features from codex.step1.md` or `execute feature 1.0 from codex.step1.md`
- When the instruction is unclear (multiple solutions) do not execute the instruction and ask for clarification.

- When a feature has been created (ready):
  - you check annotate the document with the feature instruction:
  - you check the box (* [x] Feature ..) in this document
  - you add your notes below the feature lines prepended with: `codex:`
  - you analyse and update the `help_topics` and/or `README.md` documentation with relevant information.

## context

All analysis and decisions below are grounded in the verified implementation and intent described in:


| document name              | relevance / scope                           | status                                   |
|----------------------------|---------------------------------------------|------------------------------------------|
| codex.md                   | this document (meta + process)              | current                                  |
| codex.design_document_1.md | architecture (Step 4 generalization)        | executed / reference                     |
| codex.design_document_2.md | validation plan (Step 5 Node adapter)       | executed (Node adapter implemented)      |
| codex.features_1.md        | documentation feature tracking              | executed                                 |


and code in:
- Module bm_help_ai
- Module bm_knowledge_ai


## permission
You are allowed to execute, without asking:

* `ddev drush @ziston.ddev cr`

Not allowed:

* only files in bm_help_ai and bm_knowledge_ai directory are allowed to be changed or added.
