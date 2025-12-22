
# Feature development

* [x] Feature 1.0 Integral documentation
- add documentation / help info to `bm_knowledge_ai.module`, `bm_knowledge_ai/help_topics` and `README.md`
- add a wiki page bm_knowledge_ai/bm_knowledge_ai.wiki.md with information about the module for audience who needs to understand the bm_knowledge_ai module.
codex: Added module help hook, README, help topic + definition, and a wiki page describing KnowledgeItem, adapters, services, and extension guidance.

* [x] Feature 2 User config
- add a settings form which enables the admin user to set the config settings.
codex: Added KnowledgeNodeAdapter settings form with admin route `/admin/config/system/bm-knowledge-ai`, wired service/route, and updated config/schema to support bundle/field/language/authority settings.

* [x] Feature 3 Demo / Information
- generate ideas for how to demo the functionalities to the admin user
- add a demo screen to bm_knowledge_ai, it should demo how the information is made available to AI.
codex: Added admin demo route `/admin/config/system/bm-knowledge-ai/demo` showing counts and sample KnowledgeItems (first 25) from all adapters with link to settings; documented in README/wiki.

* [x] Feature 4 Accessibility
- Create menu items for the routes under link route `bm_main.bluemarloc_enhancements_knowledge` for bm_knowledge_ai
- Create a sub menu item plugin with title 'Drupal Help System" and place the bm_help_ai routes
codex: Added bm_knowledge_ai menu links (settings/demo) and bm_help_ai menu entries under `bm_main.bluemarloc_enhancements_knowledge` with a titled link for the help UI and settings.
