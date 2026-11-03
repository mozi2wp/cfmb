# CFMB changelog (before WordPress.org)

The WordPress.org changelog starts at 1.0.0 in readme.txt. These are the development releases.


## 0.16.0
* Schema Sync: new filter `cfmb/sync/portable_group` and action `cfmb/sync/saving_group`, so add-ons can keep their own group settings in sync files. The sync diff lists those keys too (label filter `cfmb/sync/diff_label`).
* WPGraphQL: new filter `cfmb/graphql/field_config` to change the type and resolver of a field in a group's object type.
* REST API: the `cfmb` attribute is registered per post type, taxonomy, users and comments, and its schema can be filled in with the new filter `cfmb/rest/schema`.

## 0.15.0
* Switch Assistant: other plugins can add sources with the new `cfmb/switch/sources` filter and the `CFMB\Switching\Switch_Source` interface. The screen gets a "Switch from" picker when one is registered; runs, backups and undo are shared with the ACF import.
* New `CFMB\Switching\Definition_Importer` for sources: saves field groups, post types, taxonomies, options pages and connections with the same rules as the ACF import.
* Switch Assistant backups can keep values stored in several rows, and keys an import created, so undo removes them.
* `wp cfmb switch scan` and `wp cfmb switch run` take `--source=`.
* Fix: an import undone earlier can be imported again; its field groups and post types come back from the trash instead of being reported as imported.

## 0.14.0
* New filter `cfmb/rest/form_access`: lets an add-on open the field control routes (relational search, icons, paged rows) to people filling in a front-end form, including visitors who are not signed in. Off unless an add-on allows a request.
* New filter `cfmb/admin/use_media_library`: skip loading the media library for users who can't use it.

## 0.13.0
* Field Blocks: new filters `cfmb/blocks/pre_render`, which lets an add-on render a block without its PHP template, and `cfmb/blocks/editor_block`, which changes what the block editor knows about a block.
* Field Block forms follow `cfmb/field/can_view` and `cfmb/field/can_edit` like edit screens: hidden fields are left out, and fields the user may not change keep their values.
* Fix: a Field Block's form in the block editor stopped with an error after the first change.

## 0.12.0
* Connections: new `Connection_Store::link_ids()`, `links()`, `remove_links()` and `restore_links()`, and the action `cfmb/connections/unlinked`, which fires with the IDs of links that were deleted.
* New filters `cfmb/connections/settings`, `cfmb/connections/build_args` and `cfmb/connections/box_config`, and the script filter `cfmb.connections.itemExtra`, so add-ons can add settings to Connections and content under each linked item in the Connection box.

## 0.11.0
* New filter `cfmb/admin/client_field`: change a field definition before it goes to the edit screen script, for example to add data a control needs or remove settings the browser must not see.

## 0.10.0
* `cfmb/field/validate_value` gets the object being saved as a fourth argument (edit screens and `cfmb_update_field()`; null inside rows), so a check can compare against other items.

## 0.9.0
* New hooks for per-field permissions: `cfmb/field/can_view` (Dynamic Data, GraphQL, edit screens) and `cfmb/field/can_edit` (edit screens, saves, bound blocks). A field the user may not edit is left out of the form and keeps its stored value on save. Template functions are not affected.
* `Visibility::can_show_field()` takes the object as an optional second argument.

## 0.8.0
* WPML and Polylang: a Translation setting per field (translate, copy, copy once, ignore), passed down from Groups, Repeaters and Stacks. Copied images, posts and terms are swapped for their translations. Options pages keep translated values per language.
* Yoast SEO and Rank Math: text, rich text and image alt text from fields count in the content analysis, with an "Include in SEO analysis" switch per field.
* WooCommerce: location rules for product types and variations, a "Tab in the Product data box" group position, fields inside each variation, the `variation` object for Dynamic Data, and HPOS compatibility.
* WPGraphQL: a "Show in GraphQL" switch per field group (with a field name) and per field. Options page groups appear under `cfmbOptions`.
* Location rules are checked again while you edit: boxes for other templates, formats, statuses, terms or product types appear and disappear right away.
* Font Awesome Free in the Icon field, as bundled SVG. Large icon libraries load in pages in the picker.
* The Customizer shows a link to the Widgets or Menus screen for widgets and menu items with CFMB fields.
* New hooks: `cfmb/translation/mode`, `cfmb/translation/map_id`, `cfmb/seo/fields`, `cfmb/woocommerce/variation_groups`, `cfmb/graphql/field_value`, `cfmb/graphql/owners`, `cfmb/graphql/registered`, `cfmb/storage/option_language`, `cfmb/display/object`, `cfmb/admin/meta_box`. New REST route `cfmb/v1/location/match`.
* Switch Assistant: the WPML translation setting ACF Multilingual saved on each ACF field carries over.
* Hidden field inputs now send a change event when their value changes, so screens that only save changed parts (WooCommerce variations) pick them up.

## 0.7.0
* Connections: link posts, terms and users both ways, defined under CFMB > Connections. One to one, one to many, many to one or many to many. A box on each side to search, add, reorder and remove items. Links are stored in a new indexed table, `{prefix}cfmb_connections`.
* Connections in code: `cfmb_get_connected()`, `cfmb_is_connected()`, `cfmb_connect()`, `cfmb_disconnect()`, `cfmb_register_connection()`, and a `cfmb_connected` argument for `WP_Query`. Optional `cfmb_connections` attribute in the REST API. Connections are part of Schema Sync and PHP export.
* Field Loops in every page builder can loop over the items of a Connection, with new "Loop item" entries for their title and link.
* Switch Assistant: import field groups, post types, taxonomies and options pages from ACF or Secure Custom Fields, then convert values in batches, with a full undo. Also `wp cfmb switch scan|run|convert|undo`. Original values are kept in a new table, `{prefix}cfmb_switch_backup`.
* Compatibility Mode: optional ACF function names for themes built for ACF. Off by default, and never active next to ACF or SCF.
* A "Go Pro" page under CFMB that lists what CFMB Pro adds. Hidden when CFMB Pro is active.
* Database version 3. Tables are created on activation and after an update.
* New hooks: `cfmb/connections/registered`, `cfmb/connections/saved`. `cfmb/builders/loop_items` passes null for the field when the loop is over a Connection.

## 0.6.0
* Bricks: `{cfmb_…}` dynamic data tags for every field, with `:plain`, `:url`, `:date=…`, `:size=…` and `:object=…` options, and every loopable field as a query type for Bricks' own query loop.
* Beaver Builder: CFMB field connections (with Beaver Themer) for posts, archives and the site, and a Field Loop module that repeats a saved row.
* Breakdance and Oxygen 6: every CFMB field in dynamic data, grouped by field group, and loopable fields for the Repeater Fields element.
* Oxygen Classic: CFMB Field, URL and Image in Insert Dynamic Data, and a Field Loop element that repeats the elements inside it.
* In Bricks, Breakdance and Oxygen 6, fields of a group shown on one options page read from that page automatically.

## 0.5.0
* Elementor dynamic tags in a new "CFMB" group: Field, URL, Image, Gallery, Number, Color and Date, each with a field list grouped by field group and a "Read from" setting.
* Divi 4 and Divi 5 dynamic content: CFMB Field, Date, URL and Image.
* Field Loop widget for Elementor and module for Divi: repeat a saved template for each Repeater or Stacks row, gallery image, or linked post. Filter Stacks rows by layout, limit and skip items, set columns and gap.
* Inside a Field Loop, `cfmb_get_sub_field()` and the other row functions read the current row, and the global post is the current linked post.
* New hooks: `cfmb/builders/fields`, `cfmb/builders/loop_items`, `cfmb/builders/loop_item`.

## 0.4.0
* Field Blocks: any field group can be a block. Pick its name, title, icon, category, keywords, features (alignment, anchor, colors, spacing, typography), styles and variations in the field group editor. The editor shows a live preview with the fields in the sidebar, or the fields in the block; switch from the toolbar.
* Field Block templates are PHP files in your theme (`cfmb-blocks/{name}.php`) with `<InnerBlocks />` for nested blocks and `cfmb_block_wrapper_attributes()` for the wrapper. CSS and JS files next to a template load only where the block is used. Blocks can also come from a theme `block.json` with a `cfmb` key.
* New "Block" location rule, to add a field group to a Field Block.
* Block Bindings source `cfmb/field` for paragraph, heading, button and image blocks. Text-like fields of the post being edited can be changed in the bound block; the change is saved with the post.
* `[cfmb]` shortcode with `field`, `object`, `format`, `size`, `separator` and `default`. Turn it off under CFMB > Settings.
* Dynamic Data respects visibility: no values from drafts, private or password-protected posts, password fields, or other people's user fields.
* New template functions: `cfmb_block_wrapper_attributes()` and `cfmb_get_block()`.
* New hooks: `cfmb/blocks/register`, `cfmb/blocks/definition`, `cfmb/blocks/template_paths`, `cfmb/blocks/render`, `cfmb/display/value`, `cfmb/display/format`, `cfmb/display/can_read`, `cfmb/shortcode/atts`, `cfmb/bindings/editable`.
* `wp cfmb doctor` reports Field Blocks without a template.

## 0.3.0
* Post types, taxonomies and options pages: create and edit them under CFMB, with every WordPress setting in an Advanced section and labels built from the singular and plural names. Changing a key can move existing posts or terms along.
* Schema Sync: JSON files in `wp-content/uploads/cfmb-json/`, also read from a `cfmb-json` folder in the theme. CFMB > Sync shows what changed, pulls, pushes, merges side by side, and exports JSON or PHP.
* Register field groups, post types, taxonomies and options pages in code with `cfmb_register_group()`, `cfmb_register_post_type()`, `cfmb_register_taxonomy()` and `cfmb_register_options_page()` on the new `cfmb/register` hook.
* New locations: comments, attachments, menus, menu items and widgets (classic widgets and the Legacy Widget block). The media modal links to an attachment's fields.
* Columns, sorting and filters on post, term and user lists, set per field.
* Read-only field values in the REST API, turned on per field group with a per-field opt-out.
* WP-CLI commands: `wp cfmb sync status|push|pull`, `wp cfmb export`, `wp cfmb import`, `wp cfmb group list|get|activate|deactivate|delete`, `wp cfmb field get|update|delete` and `wp cfmb doctor`.
* New hooks: `cfmb/register`, `cfmb/schema/saved`, `cfmb/schema/deleted`, `cfmb/sync/save_path`, `cfmb/sync/load_paths`, `cfmb/post_type/args`, `cfmb/taxonomy/args`, `cfmb/rest/value`, `cfmb/admin/column_value`.
* Change: "Post type is …" rules no longer match attachments. Use the new Attachment rule for media.

## 0.2.1
* Map fields fill in the address when you place or drag the pin. Google maps use Advanced Markers when you add a Map ID in CFMB > Settings.
* User fields: people who can't list users can still pick users on content they can edit. They search by name only.
* Date and date-time fields: earliest and latest date (fixed or relative, like +7 days), and the first day of the week. Dates are picked on the WordPress calendar.
* Relationship fields can filter by category, tag or other taxonomy.
* Rows can be pasted into any Repeater or Stacks field with the same sub-fields, not only the one they were copied from.
* The field group editor warns while you type when nested names make saved keys too long.
* The name check for presets now includes presets inside the source group.
* Errors inside rows now point at the right row after the page reloads, even when rows were reordered. Problems in new rows show on the field itself.

## 0.2.0
* New structure fields: Group, Repeater, Stacks and Presets, nestable at any depth.
* New field types: gallery, oEmbed, link, post, page link, relationship, taxonomy, user, date, time, date and time, color, icon, map and accordion.
* Rows can be collapsed, titled, duplicated, moved to a position, copied between posts and shown in pages.
* Row template functions: `cfmb_have_rows()`, `cfmb_the_row()`, `cfmb_get_sub_field()`, `cfmb_the_sub_field()`, `cfmb_get_row_layout()`, `cfmb_get_row_index()`, `cfmb_reset_rows()`.
* Field group editor: fields inside Groups, Repeaters and Stacks layouts, drag fields between levels, "Move to…", preset groups.
* Settings for map fields (none, OpenStreetMap or Google Maps) and opt-in SVG uploads.
* Fix: hidden tabs could still show their fields.
* Fix: a list saved empty on a new post (for example no checkboxes ticked) now stays empty instead of falling back to its default.

## 0.1.0
* First release: field group editor, 17 field types, location rules, conditional logic, options pages and the template API.
