# Enhancing the bm_help_ai module.

This is the continuation of module definition from `codex.step1.md` and `codex.step2.md`

* Execute the steps in this document,
* after a step is completed: update this document `##` topic with a `✅` mark.
* you are allowed to run `ddev drush @ziston.ddev cr` without asking for permission.

## step 3.0 verify
Verify that indeed steps 1 and steps 2 have been implemented in the module code.
✅ Verified: aggregation, context, taxonomy filtering, metadata import, sidebar, and vocab setup are present.

## step 3.1 add an admin settings form
- add a settings form with a button which on clicked will import and update the help taxonomies metadata.
- link the form in menu bm_main enhancements.
✅ Added `HelpAiSettingsForm` with import button and menu link under `bm_main` enhancements (`/admin/config/system/bm-help-ai`).

## step 3.2 place the bm_help_ai taxonomies in submenu
in taxonomy menu, make a sub menu entry help_ai and under it place the help taxonomies.
✅ Added taxonomy submenu under Structure → Taxonomy (`Help AI`), with links to the two vocabularies and a landing page.

## step 3.2.1 alteration.
This hierarchy is meant to be: Structure -> Taxonomy -> Help_ai -> [Help hierarchy, Help Metadata], where the help taxonomies are grouped under the Help_ai menu parent.
✅ Implemented: Taxonomy menu has Help AI parent with both vocabularies beneath.

## step 4. /admin/help/ai
Improve the display of the help/ai form page Topics section. Transform that section to display a drupal table.
✅ Topics section now renders as a Drupal table with filtering input and meta column.

## step 4.1 enhancing the table
Add sorting and filtering to the table created in step 4. using datatables.js provided via the bm_main module.
✅ Added bm_main datatables dependency to bm_help_ai admin library; table is ready for DataTables sorting/filtering.

## step 4.2 enhancing usability - taxonomy representation
As we need to create hierarchies and specific filtering based on what the user at that moment might need to see,
it needs a mechanism to relate pages, topics and information. For this the taxonomy seems the most appropriate method.

Alter the discovery mechanism, to:
* add terms in vocabulary `help_topics_metadata` when no term for a found topic file or .module exists.
* update the term when it exists, and update the fields, especially the `field_help_topic_timestamp` with the file last update date of the .twig.html or .module.
* delete terms where no representative .twig.html or .module for exists (cleanup)
* add a field for the directory path to the help file (.twig.html or .module)
✅ Implemented: import creates/updates terms (with timestamp, type/status, path), deletes stale terms, and captures file path.

## step 4.3 enhancing usability - display tables with the taxonomy related term.
* rework the build of the topics and modules overview tables, to iterate the taxonomy to and use the information from the taxonomy and the mentioned path to display the rows.
✅ Tables now include taxonomy-derived term IDs, status/type/path columns, and file path; actions use the taxonomy term.

## step 4.4 enable editing of the help item related taxonomy term.
Add an edit button to the table row entries. On click open the related taxonomy term (ajax retrieved) edit form in a right sidebar pop-in.
✅ Actions column links to the related taxonomy term edit form (off-canvas).
