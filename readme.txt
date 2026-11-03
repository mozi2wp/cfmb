=== CFMB ===
Contributors: mozi2wp
Tags: custom fields, meta box, fields, options page, content modeling
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.16.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom fields, post types, taxonomies and options pages. Clean data, a fast editor, and no locked features.

== Description ==

CFMB lets you add your own fields to WordPress edit screens and show their values in your theme.

Build a field group, choose where it appears, and your editors get the fields on those screens. Values are stored as normal WordPress meta, so they work with `WP_Query`, `meta_query` and other plugins.

= What you get =

* 32 field types. Basic: text, text area, number, range, email, URL, password. Content: rich text editor, image, file, gallery, oEmbed. Choice: select, checkboxes, radio buttons, button group, toggle. Relational: link, post, page link, relationship, taxonomy, user. Pickers: date, time, date and time, color, icon, map. Layout: message, tab, accordion.
* Structure: Group, Repeater, Stacks (rows that each pick a layout, stacked in any order) and Presets (insert the fields of another field group and keep them in sync).
* Long Repeaters stay fast: rows collapse, load their fields only when opened, and can be shown in pages that load and save on their own. Rows can be duplicated, moved and copied between posts.
* Location rules for post types, templates, post status, post format, post terms, taxonomies, user roles, user forms, options pages, comments, attachments, menus, menu items, widgets, and WooCommerce product types and variations. Combine them with "and" and "or". Boxes show or hide as soon as you pick another template, format or term, without saving first.
* Post types, taxonomies and options pages, made in the admin with every WordPress setting available. Labels are filled in from the singular and plural names.
* Schema Sync: field groups, post types, taxonomies and options pages are also saved as JSON files you can keep in version control. Changes made in the files show up for review, with a side-by-side merge when both sides changed.
* Register any of them in code with `cfmb_register_group()` and friends, or export PHP from CFMB > Sync.
* Columns, sorting and filters for any field on post, term and user lists.
* Field values in the REST API, read-only and opt-in per field group.
* Field Blocks: turn a field group into a block for the block editor. Editors fill in the fields in the sidebar or right in the block, and a PHP template in your theme shows them. Inner blocks, colors, spacing, alignment, styles and variations work like core blocks.
* Block Bindings: show a field in a paragraph, heading, button or image block, with no code. Text fields of the post can be edited right in the bound block.
* A `[cfmb field="price"]` shortcode for field values in content.
* Page builders: CFMB fields in the dynamic data of Elementor, Divi 4 and 5, Bricks, Beaver Builder (with Beaver Themer), Breakdance, Oxygen 6 and Oxygen Classic, plus a Field Loop that repeats a template for each Repeater or Stacks row, gallery image, linked post or connected item.
* Connections: link posts, terms and users both ways (books and their authors, events and venues). One to one, one to many or many to many, with a box on each side and links stored in their own indexed table.
* Multilingual sites: per-field translation settings for WPML and Polylang (translate, copy, copy once or ignore). Copied images, posts and terms point at their translations, and options pages keep values per language.
* Yoast SEO and Rank Math count text, rich text and image alt text from your fields in their content analysis.
* WooCommerce: fields on products and on each variation, a tab in the Product data box, and location rules for product types and variations.
* WPGraphQL: show field groups in your GraphQL schema with one switch.
* Icon field libraries: Dashicons, WordPress icons and Font Awesome Free, bundled as SVG, plus your own images.
* Switch Assistant: bring field groups, post types, taxonomies, options pages and values over from ACF or Secure Custom Fields, with undo.
* Compatibility Mode (optional, off by default): themes built for ACF keep working with `get_field()`, `have_rows()` and the other ACF functions.
* WP-CLI: `wp cfmb sync`, `wp cfmb export`, `wp cfmb import`, `wp cfmb group`, `wp cfmb field`, `wp cfmb doctor` and `wp cfmb switch`.
* Conditional logic: show a field only when other fields have certain values.
* Options pages registered with one function call.
* A field group editor with drag-and-drop ordering, undo for deletes, and keyboard support.
* Works in the block editor and the classic editor.

= How data is stored =

* Each value is saved under the field name, for example `price`.
* Values inside a Group or a row are saved under their own keys too: `hero_title`, `items_0_title`. A Repeater saves its row count (`items` = 2); a Stacks field saves each row's layout name as its own meta row, in order, so `meta_query` can find posts that use a layout.
* Links and maps save each part on its own (`cta_url`, `loc_lat`, `loc_lng`), so you can query by them.
* Dates are saved as `2026-10-15`, times as `14:30:00` and date-times as `2026-10-15 14:30:00`, so they sort and compare correctly.
* Lists, such as checked boxes, are saved as one meta row per item. You can match a single item with `meta_query` without slow `LIKE` searches.
* Options page values are saved as `cfmb_{page}_{field}` options and widget values as `cfmb_widget_{widget id}_{field}`. Neither is autoloaded. With WPML or Polylang, translated options page fields keep each extra language's value as `cfmb_{page}_{language}_{field}`; the default language keeps the plain name.
* Comment values are comment meta; menu item and attachment values are post meta; menu values are term meta.
* Field groups, post types, taxonomies, options pages and Connections are saved as JSON in private post types. One query loads all field groups.
* Connection links are rows in the `{prefix}cfmb_connections` table: the connection ID, the two object IDs and each side's order, indexed both ways.
* Schema Sync files go to `wp-content/uploads/cfmb-json/`, one file per item, named after its key. That folder gets an `.htaccess` file so Apache doesn't serve it. CFMB also reads a `cfmb-json` folder in your theme or child theme.

= Show values in your theme =

`<?php cfmb_the_field( 'subtitle' ); ?>`

`<?php $price = cfmb_get_field( 'price' ); ?>`

Loop over a Repeater or Stacks field:

`<?php while ( cfmb_have_rows( 'sections' ) ) : cfmb_the_row(); ?>`
`<?php if ( 'hero' === cfmb_get_row_layout() ) { cfmb_the_sub_field( 'heading' ); } ?>`
`<?php endwhile; ?>`

Values from terms, users and options pages:

`cfmb_get_field( 'color', 'term_12' );`
`cfmb_get_field( 'twitter', 'user_3' );`
`cfmb_get_field( 'phone', 'option' );`
`cfmb_get_field( 'rating', 'comment_5' );`
`cfmb_get_field( 'accent', 'widget:text-3' );`

Field values in the REST API: turn on "Show in REST API" in a field group's settings. Values show under `cfmb` in responses for the posts, terms, users and comments the group is on.

= Field Blocks =

Open a field group, turn on "Use as a Field Block" and give the block a name, for example `hero`. Then add a template to your theme at `cfmb-blocks/hero.php`:

`<section <?php echo cfmb_block_wrapper_attributes(); ?>>`
`<h2><?php cfmb_the_field( 'heading' ); ?></h2>`
`<InnerBlocks />`
`</section>`

Inside the template, `cfmb_get_field()` and the other functions read the block's own values. Pass `get_the_ID()` to read the post instead. The template also gets `$block`, `$fields`, `$content`, `$is_preview`, `$post_id` and `$context`. Put `hero.css` and `hero.js` next to the template and they load only on pages that use the block.

Block values are saved inside the block in the post content, so the same block can appear many times with different values, and blocks work in templates and patterns. They can't be searched with `meta_query`.

A block can also come from a `block.json` in `cfmb-blocks/{name}/` with a `"cfmb": {}` key; add fields to it with the "Block" location rule.

= Block Bindings and the shortcode =

In the block editor (WordPress 6.7 or later), open a paragraph, heading, button or image block, and connect it to "CFMB fields" under Attributes. Or write it in the code editor:

`<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"cfmb/field","args":{"field":"price"}}}}} -->`

The shortcode: `[cfmb field="price"]`, `[cfmb field="logo" object="option:site_settings" size="medium"]`, `[cfmb field="colors" separator=" / "]`, `[cfmb field="website" format="url"]`.

Both read from the current post by default (the post in a Query Loop). `object` can also be `term` (the archive's term), `author` (the post's author), `user` (the logged-in visitor), `option` or `option:{page}`, or one object like `post_12`, `term_12` or `user_3`. Only values the visitor may already see are shown: nothing from drafts, private or password-protected posts, password fields, or other people's profiles. Repeater, Group and Stacks values are not shown this way; use a Field Loop in a page builder, or `cfmb_have_rows()` in a template.

= Page builders =

CFMB adds a "CFMB" group to Elementor's dynamic tags: Field, URL, Image, Gallery, Number, Color and Date. Each tag has a field list grouped by field group, and a "Read from" setting (current post, archive term, post author, logged-in user, an options page, or one post, term or user). Tags show the same values as the shortcode and follow the same visibility rules.

Divi 4 and Divi 5 get CFMB Field, Date, URL and Image entries in the dynamic content list, with the same settings.

Bricks gets `{cfmb_…}` tags for every field, such as `{cfmb_price}`, `{cfmb_items.title}` inside a loop, or `{cfmb_logo:object=option}`. Add `:plain` for text without HTML, `:url` for a link, or `:date=F j, Y` for a date format. Every loopable field and Connection is also a Bricks query type ("CFMB: …"), so Bricks' own query loop repeats any element for it.

Beaver Themer gets CFMB Field, URL, Image, Gallery, Number, Color and Date field connections for posts, archives and the site. Breakdance and Oxygen 6 list every CFMB field under "CFMB" in their dynamic data, by field group, and loopable fields work with the Repeater Fields element. Oxygen Classic (4 and earlier) gets CFMB Field, URL and Image in Insert Dynamic Data.

The Field Loop repeats a template for each item of a field: each row of a Repeater or Stacks field, each image of a Gallery, each post of a Post or Relationship field, or each item linked through a Connection. In Elementor it is a widget that shows a saved template, in Divi a module that shows a Divi Library layout, in Beaver Builder a module that shows a saved row, and in Oxygen Classic an element that repeats the elements you put inside it. Inside the template, pick sub-fields like "Items › Title" in a CFMB tag, or a "Loop item" entry (item number, Stacks layout, image, image caption, title and link of a linked item). In a loop over posts, tags and the builder's own post tags read each linked post. Field Loops can be nested.

= Connections =

Make one under CFMB > Connections: pick what it links "from" and "to" (posts, terms or users, narrowed to some post types, taxonomies or roles) and how many each side may have. Each side gets a box on its edit screen to search, add, reorder and remove items. Links work both ways, so a book lists its authors and an author lists their books.

`foreach ( cfmb_get_connected( 'book_authors' ) as $author ) { echo esc_html( $author->display_name ); }`

`new WP_Query( array( 'post_type' => 'book', 'cfmb_connected' => array( 'connection' => 'book_authors', 'object' => 'user_3' ) ) );`

`cfmb_connect( 'book_authors', $book_id, 'user_3' );` and `cfmb_disconnect()` change links in code. `cfmb_get_connected()` and Field Loops only return items the visitor may see. Turn on "Show in the REST API" to list connected IDs under `cfmb_connections`.

= Switching from ACF or Secure Custom Fields =

Open CFMB > Switch Assistant. It reads ACF or SCF data from the database, from `acf-json` folders and, while ACF is active, from field groups registered in PHP. Pick what to bring over and import it. Then deactivate ACF or SCF and convert the values.

Most values already fit: CFMB stores Repeater rows, Group sub-fields, IDs and text the same way. The assistant rewrites lists (checkboxes, multi-selects, relationships, galleries, flexible content layouts) into one meta row per item, turns date picker values into `2026-10-15`, and moves options page values to CFMB's option names. Flexible Content becomes Stacks, and clone fields become copies of the fields they showed. It only touches rows that ACF marked as its own, and keeps a copy of each one, so "Undo import" puts every value back exactly as it was. On large sites use `wp cfmb switch run`.

Add-ons can offer more plugins to switch from on the same screen. CFMB Pro adds Meta Box, JetEngine, Pods and Toolset Types.

= Compatibility Mode =

Turn it on under CFMB > Settings once ACF or SCF is deactivated. Your theme can then keep calling `get_field()`, `the_field()`, `have_rows()`, `the_row()`, `get_sub_field()`, `get_row_layout()`, `get_fields()`, `update_field()`, `acf_add_options_page()` and the rest. Each one calls its `cfmb_` version. These are the only functions CFMB defines without its prefix, and only while Compatibility Mode is on. It can't be turned on while ACF, ACF Pro or Secure Custom Fields is active, and it turns itself off if one of them is activated later.

= Multilingual sites (WPML and Polylang) =

Each field has a Translation setting under More settings, shown while WPML or Polylang is active:

* Translate: each language has its own value. This is the default for text, text areas, rich text, URLs, oEmbeds and links.
* Copy: the value is the same in every language and stays in sync. This is the default for everything else, such as numbers, choices, dates, images and linked posts.
* Copy once: copied when a translation is made, then edited on its own.
* Ignore: never copied.

A Group, Repeater or Stacks field passes its setting to its sub-fields, unless a sub-field sets its own. When an image, post or term is copied to a translation, CFMB uses the translated image, post or term if there is one. With WPML, CFMB adds these settings to WPML's language configuration, so they show under WPML > Settings > Custom Fields Translation, where a theme's own `wpml-config.xml` still wins. Options pages keep one value per language for translated fields; switch the language in the admin bar to edit another language.

= SEO plugins =

With Yoast SEO or Rank Math active, text, text area, rich text, image and gallery fields count toward the content analysis on the edit screen, at any depth inside Groups and rows. Turn "Include in SEO analysis" on or off per field. Nothing extra is saved.

= WooCommerce =

Use the location rules "Post type is Product", "Product type is …" and "Product category …" for product fields. Set a group's position to "Tab in the WooCommerce Product data box" to show it there instead of as a separate box. For variations, use the rule "Product variation is Any variation" (or "Variations in" a category): the fields show inside each variation and save with it. Read a variation's values with `cfmb_get_field( 'size', $variation_id )`, or with the object `variation` in the shortcode and page builder tags while a variation is the current product.

= WPGraphQL =

Turn on "Show in GraphQL" for a field group. It becomes a field (named after the group, such as `bookDetails`) on every post type, taxonomy and user type it shows on, and options page groups appear under `cfmbOptions`. Text and choices come as strings, numbers as floats, toggles as booleans, and images, files, posts, terms and users as database IDs. Links, maps, Groups, Repeaters and Stacks come as JSON strings; typed GraphQL objects for them are part of CFMB Pro. Values follow the same rules as everywhere else: nothing from drafts or private posts unless the viewer may read them, user fields only for that user and editors, and password fields never. Turn a single field off with its own "Show in GraphQL" switch.

= Free and complete =

Nothing in CFMB is locked, limited or time-limited. CFMB Pro is a separate plugin that adds new features. It never unlocks code inside this plugin.

= Privacy and outside services =

CFMB does not track you and sends nothing anywhere on its own. A few features you turn on contact outside services from the editor's browser:

* Map fields, when you choose OpenStreetMap under CFMB > Settings (or on a field): map tiles load from tile.openstreetmap.org, and address search and the address for a placed pin come from nominatim.openstreetmap.org. See the [OpenStreetMap privacy policy](https://osmfoundation.org/wiki/Privacy_Policy) and [tile usage policy](https://operations.osmfoundation.org/policies/tiles/).
* Map fields, when you choose Google Maps and add your own API key: the map, address search and the address for a placed pin load from maps.googleapis.com. See the [Google privacy policy](https://policies.google.com/privacy) and [Maps terms](https://cloud.google.com/maps-platform/terms).
* oEmbed fields use WordPress's own embed feature, which asks the site you link to (such as YouTube) for the embed.

With no map service chosen, which is the default, map fields show plain address and coordinate inputs and nothing is loaded from outside.

= Source code =

The full JavaScript and SCSS source is in the `assets/src` folder. The `build` folder holds the compiled files. Rebuild them with `npm install` and `npm run build`.

= Third-party code =

* [Leaflet](https://leafletjs.com/) 1.9.4, BSD-2-Clause, for OpenStreetMap maps (bundled in `build/map.js`).
* [svg-sanitizer](https://github.com/darylldoyle/svg-sanitizer) 0.20.0 by Daryll Doyle, GPL-2.0-or-later, for cleaning SVG uploads (in `vendor-prefixed`, namespace prefixed with `CFMB\Vendor`).
* [WordPress icons](https://github.com/WordPress/gutenberg/tree/trunk/packages/icons) (`@wordpress/icons`), GPL-2.0-or-later, bundled as `assets/icons/wordpress.json` for the Icon field.
* [Font Awesome Free](https://fontawesome.com/) icons by Fonticons, Inc., CC BY 4.0, bundled as SVG in `assets/icons/fontawesome.json` for the Icon field (see `assets/icons/fontawesome-LICENSE.txt`). No font files or CDN are loaded.

== Installation ==

1. Install and activate CFMB from Plugins > Add New.
2. Go to CFMB > Field Groups and click "Add field group".
3. Add your fields, choose where they show under Location, and save.
4. Open a matching edit screen to fill in the fields.

To add an options page, put this in your theme or a small plugin:

`add_action( 'init', function () { cfmb_add_options_page( array( 'page_title' => 'Site settings' ) ); } );`

Then create a field group with the location "Options page is Site settings". You can also make options pages under CFMB > Options Pages without code.

== Frequently Asked Questions ==

= Where are my values saved? =

In the normal WordPress meta tables, under the field name. Options page values are saved in the options table as `cfmb_{page}_{field}`.

= What happens to my content if I delete CFMB? =

Values on your posts, terms and users, and Connection links, are always kept. Field groups, other definitions, Switch Assistant backups and settings are removed only if you turn on "Delete field groups and CFMB settings" under CFMB > Settings.

= Why can I save a draft with an empty required field? =

Drafts are work in progress. Required fields must be filled in before a post is published.

= Can I rename a field? =

Yes. Each field has a fixed key, so renaming it in the editor is safe for the field group. Values already saved under the old name stay under the old name.

= Can I upload SVG icons? =

Yes, after you turn on "Allow SVG files in the media library" under CFMB > Settings. It is off by default. Every SVG is cleaned when it is uploaded: scripts, event handlers and outside references are removed, and files that can't be cleaned are refused.

= How do Presets work? =

Build a field group with the fields you want to reuse, and turn on "Use only as a preset" so it doesn't show on its own. In another group, add a Preset field and pick that group. Its fields appear in place and stay in sync with it. With prefixed names, the values are saved as `{preset name}_{field name}`, so you can insert the same preset twice.

= How does Schema Sync work? =

Each time you save a field group, post type, taxonomy or options page, CFMB writes it to `wp-content/uploads/cfmb-json/{key}.json`. Commit that file (or copy it into a `cfmb-json` folder in your theme) and deploy it. On the other site, CFMB > Sync shows what changed: pull a newer file into the database, write a newer database copy to its file, or review a side-by-side diff when both changed. A file that was changed outside CFMB is never overwritten on save. Items that exist only in a file are active straight away and can be imported to edit them.

On servers that don't read `.htaccess` (nginx), block `/wp-content/uploads/cfmb-json/` in the server settings if you don't want the files to be downloadable.

= Can I rename a post type or taxonomy? =

Yes. CFMB asks whether to move existing posts or terms to the new key, and points location rules at the new key either way.

= My Field Block shows "Template missing". =

Add the template file named in the message to your theme, or set its path under "More settings" in the field group's Field Block settings. For safety, CFMB only loads templates from your theme, the plugins folder, or a folder added with the `cfmb/blocks/template_paths` filter. `wp cfmb doctor` lists blocks without a template.

= What happens to blocks if I rename a Field Block? =

Blocks already in your content keep the old name, so the editor no longer recognizes them. The field group editor warns you before you save. Change the name back and they work again.

= Can I turn off the shortcode? =

Yes, under CFMB > Settings > Display.

= Can authors print any field with the shortcode? =

Only fields of things visitors can already see: published posts of public post types, public taxonomies, options pages, and their own or the post author's user fields. Password fields never show. Keep secrets such as API keys in a Password field, or turn the shortcode off if authors should not print field values at all.

= Which page builder versions work? =

Elementor 3.5 or later (free or Pro), Divi 4 and Divi 5, Bricks 1.9 or later, Beaver Builder 2.5 or later (field connections need Beaver Themer), Breakdance 2 or later, Oxygen 6, and Oxygen Classic 4. In Divi 5 the Field Loop module runs through Divi's support for Divi 4 modules. Each integration loads only when its builder is active.

= What happens to Connection links when I delete a post? =

The links stay in the table, but deleted, draft and private items never show on the site or in the REST API. CFMB Pro adds automatic cleanup.

= Can I undo the Switch Assistant? =

Yes. "Undo import" puts every value it changed back exactly as ACF saved it, and moves the field groups, post types, taxonomies and options pages it made to the trash. When you are happy with the result, "Remove from the list" deletes the backup.

= Why did Compatibility Mode turn itself off? =

ACF, ACF Pro or Secure Custom Fields was activated. They define the same functions, and only one plugin may. Deactivate it and turn Compatibility Mode back on.

= Can a Field Loop show its own template inside itself? =

No. A template that is the page being edited is skipped, and loops stop after eight levels of nesting.

= Do my translations keep their own values? =

Yes, for fields set to Translate or Copy once. Fields set to Copy are kept the same in every language, so changing them in one language changes them in all. You can change the setting per field at any time; values already saved stay as they are.

= Why does a field group appear only after I pick a template or category? =

A group whose location rules depend on the template, post format, status, terms or product type waits hidden on the edit screen and appears as soon as the form matches its rules. It is saved only if it still matches when you save.

= Can I edit widget or menu item fields in the Customizer? =

No. The Customizer previews changes before you publish them, while CFMB saves values right away. The Customizer shows a link to the Widgets or Menus screen, where those fields are edited.

= Who can manage field groups? =

Administrators, through the `manage_options` capability. Change it with the `cfmb/admin/capability` filter.

== Screenshots ==

1. The field group list.
2. Editing a field group: fields, location and settings.
3. Fields on a post edit screen.

== Changelog ==

= 0.16.0 =
* Schema Sync: new filter `cfmb/sync/portable_group` and action `cfmb/sync/saving_group`, so add-ons can keep their own group settings in sync files. The sync diff lists those keys too (label filter `cfmb/sync/diff_label`).
* WPGraphQL: new filter `cfmb/graphql/field_config` to change the type and resolver of a field in a group's object type.
* REST API: the `cfmb` attribute is registered per post type, taxonomy, users and comments, and its schema can be filled in with the new filter `cfmb/rest/schema`.

= 0.15.0 =
* Switch Assistant: other plugins can add sources with the new `cfmb/switch/sources` filter and the `CFMB\Switching\Switch_Source` interface. The screen gets a "Switch from" picker when one is registered; runs, backups and undo are shared with the ACF import.
* New `CFMB\Switching\Definition_Importer` for sources: saves field groups, post types, taxonomies, options pages and connections with the same rules as the ACF import.
* Switch Assistant backups can keep values stored in several rows, and keys an import created, so undo removes them.
* `wp cfmb switch scan` and `wp cfmb switch run` take `--source=`.
* Fix: an import undone earlier can be imported again; its field groups and post types come back from the trash instead of being reported as imported.

= 0.14.0 =
* New filter `cfmb/rest/form_access`: lets an add-on open the field control routes (relational search, icons, paged rows) to people filling in a front-end form, including visitors who are not signed in. Off unless an add-on allows a request.
* New filter `cfmb/admin/use_media_library`: skip loading the media library for users who can't use it.

= 0.13.0 =
* Field Blocks: new filters `cfmb/blocks/pre_render`, which lets an add-on render a block without its PHP template, and `cfmb/blocks/editor_block`, which changes what the block editor knows about a block.
* Field Block forms follow `cfmb/field/can_view` and `cfmb/field/can_edit` like edit screens: hidden fields are left out, and fields the user may not change keep their values.
* Fix: a Field Block's form in the block editor stopped with an error after the first change.

= 0.12.0 =
* Connections: new `Connection_Store::link_ids()`, `links()`, `remove_links()` and `restore_links()`, and the action `cfmb/connections/unlinked`, which fires with the IDs of links that were deleted.
* New filters `cfmb/connections/settings`, `cfmb/connections/build_args` and `cfmb/connections/box_config`, and the script filter `cfmb.connections.itemExtra`, so add-ons can add settings to Connections and content under each linked item in the Connection box.

= 0.11.0 =
* New filter `cfmb/admin/client_field`: change a field definition before it goes to the edit screen script, for example to add data a control needs or remove settings the browser must not see.

= 0.10.0 =
* `cfmb/field/validate_value` gets the object being saved as a fourth argument (edit screens and `cfmb_update_field()`; null inside rows), so a check can compare against other items.

= 0.9.0 =
* New hooks for per-field permissions: `cfmb/field/can_view` (Dynamic Data, GraphQL, edit screens) and `cfmb/field/can_edit` (edit screens, saves, bound blocks). A field the user may not edit is left out of the form and keeps its stored value on save. Template functions are not affected.
* `Visibility::can_show_field()` takes the object as an optional second argument.

= 0.8.0 =
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

= 0.7.0 =
* Connections: link posts, terms and users both ways, defined under CFMB > Connections. One to one, one to many, many to one or many to many. A box on each side to search, add, reorder and remove items. Links are stored in a new indexed table, `{prefix}cfmb_connections`.
* Connections in code: `cfmb_get_connected()`, `cfmb_is_connected()`, `cfmb_connect()`, `cfmb_disconnect()`, `cfmb_register_connection()`, and a `cfmb_connected` argument for `WP_Query`. Optional `cfmb_connections` attribute in the REST API. Connections are part of Schema Sync and PHP export.
* Field Loops in every page builder can loop over the items of a Connection, with new "Loop item" entries for their title and link.
* Switch Assistant: import field groups, post types, taxonomies and options pages from ACF or Secure Custom Fields, then convert values in batches, with a full undo. Also `wp cfmb switch scan|run|convert|undo`. Original values are kept in a new table, `{prefix}cfmb_switch_backup`.
* Compatibility Mode: optional ACF function names for themes built for ACF. Off by default, and never active next to ACF or SCF.
* A "Go Pro" page under CFMB that lists what CFMB Pro adds. Hidden when CFMB Pro is active.
* Database version 3. Tables are created on activation and after an update.
* New hooks: `cfmb/connections/registered`, `cfmb/connections/saved`. `cfmb/builders/loop_items` passes null for the field when the loop is over a Connection.

= 0.6.0 =
* Bricks: `{cfmb_…}` dynamic data tags for every field, with `:plain`, `:url`, `:date=…`, `:size=…` and `:object=…` options, and every loopable field as a query type for Bricks' own query loop.
* Beaver Builder: CFMB field connections (with Beaver Themer) for posts, archives and the site, and a Field Loop module that repeats a saved row.
* Breakdance and Oxygen 6: every CFMB field in dynamic data, grouped by field group, and loopable fields for the Repeater Fields element.
* Oxygen Classic: CFMB Field, URL and Image in Insert Dynamic Data, and a Field Loop element that repeats the elements inside it.
* In Bricks, Breakdance and Oxygen 6, fields of a group shown on one options page read from that page automatically.

= 0.5.0 =
* Elementor dynamic tags in a new "CFMB" group: Field, URL, Image, Gallery, Number, Color and Date, each with a field list grouped by field group and a "Read from" setting.
* Divi 4 and Divi 5 dynamic content: CFMB Field, Date, URL and Image.
* Field Loop widget for Elementor and module for Divi: repeat a saved template for each Repeater or Stacks row, gallery image, or linked post. Filter Stacks rows by layout, limit and skip items, set columns and gap.
* Inside a Field Loop, `cfmb_get_sub_field()` and the other row functions read the current row, and the global post is the current linked post.
* New hooks: `cfmb/builders/fields`, `cfmb/builders/loop_items`, `cfmb/builders/loop_item`.

= 0.4.0 =
* Field Blocks: any field group can be a block. Pick its name, title, icon, category, keywords, features (alignment, anchor, colors, spacing, typography), styles and variations in the field group editor. The editor shows a live preview with the fields in the sidebar, or the fields in the block; switch from the toolbar.
* Field Block templates are PHP files in your theme (`cfmb-blocks/{name}.php`) with `<InnerBlocks />` for nested blocks and `cfmb_block_wrapper_attributes()` for the wrapper. CSS and JS files next to a template load only where the block is used. Blocks can also come from a theme `block.json` with a `cfmb` key.
* New "Block" location rule, to add a field group to a Field Block.
* Block Bindings source `cfmb/field` for paragraph, heading, button and image blocks. Text-like fields of the post being edited can be changed in the bound block; the change is saved with the post.
* `[cfmb]` shortcode with `field`, `object`, `format`, `size`, `separator` and `default`. Turn it off under CFMB > Settings.
* Dynamic Data respects visibility: no values from drafts, private or password-protected posts, password fields, or other people's user fields.
* New template functions: `cfmb_block_wrapper_attributes()` and `cfmb_get_block()`.
* New hooks: `cfmb/blocks/register`, `cfmb/blocks/definition`, `cfmb/blocks/template_paths`, `cfmb/blocks/render`, `cfmb/display/value`, `cfmb/display/format`, `cfmb/display/can_read`, `cfmb/shortcode/atts`, `cfmb/bindings/editable`.
* `wp cfmb doctor` reports Field Blocks without a template.

= 0.3.0 =
* Post types, taxonomies and options pages: create and edit them under CFMB, with every WordPress setting in an Advanced section and labels built from the singular and plural names. Changing a key can move existing posts or terms along.
* Schema Sync: JSON files in `wp-content/uploads/cfmb-json/`, also read from a `cfmb-json` folder in the theme. CFMB > Sync shows what changed, pulls, pushes, merges side by side, and exports JSON or PHP.
* Register field groups, post types, taxonomies and options pages in code with `cfmb_register_group()`, `cfmb_register_post_type()`, `cfmb_register_taxonomy()` and `cfmb_register_options_page()` on the new `cfmb/register` hook.
* New locations: comments, attachments, menus, menu items and widgets (classic widgets and the Legacy Widget block). The media modal links to an attachment's fields.
* Columns, sorting and filters on post, term and user lists, set per field.
* Read-only field values in the REST API, turned on per field group with a per-field opt-out.
* WP-CLI commands: `wp cfmb sync status|push|pull`, `wp cfmb export`, `wp cfmb import`, `wp cfmb group list|get|activate|deactivate|delete`, `wp cfmb field get|update|delete` and `wp cfmb doctor`.
* New hooks: `cfmb/register`, `cfmb/schema/saved`, `cfmb/schema/deleted`, `cfmb/sync/save_path`, `cfmb/sync/load_paths`, `cfmb/post_type/args`, `cfmb/taxonomy/args`, `cfmb/rest/value`, `cfmb/admin/column_value`.
* Change: "Post type is …" rules no longer match attachments. Use the new Attachment rule for media.

= 0.2.1 =
* Map fields fill in the address when you place or drag the pin. Google maps use Advanced Markers when you add a Map ID in CFMB > Settings.
* User fields: people who can't list users can still pick users on content they can edit. They search by name only.
* Date and date-time fields: earliest and latest date (fixed or relative, like +7 days), and the first day of the week. Dates are picked on the WordPress calendar.
* Relationship fields can filter by category, tag or other taxonomy.
* Rows can be pasted into any Repeater or Stacks field with the same sub-fields, not only the one they were copied from.
* The field group editor warns while you type when nested names make saved keys too long.
* The name check for presets now includes presets inside the source group.
* Errors inside rows now point at the right row after the page reloads, even when rows were reordered. Problems in new rows show on the field itself.

= 0.2.0 =
* New structure fields: Group, Repeater, Stacks and Presets, nestable at any depth.
* New field types: gallery, oEmbed, link, post, page link, relationship, taxonomy, user, date, time, date and time, color, icon, map and accordion.
* Rows can be collapsed, titled, duplicated, moved to a position, copied between posts and shown in pages.
* Row template functions: `cfmb_have_rows()`, `cfmb_the_row()`, `cfmb_get_sub_field()`, `cfmb_the_sub_field()`, `cfmb_get_row_layout()`, `cfmb_get_row_index()`, `cfmb_reset_rows()`.
* Field group editor: fields inside Groups, Repeaters and Stacks layouts, drag fields between levels, "Move to…", preset groups.
* Settings for map fields (none, OpenStreetMap or Google Maps) and opt-in SVG uploads.
* Fix: hidden tabs could still show their fields.
* Fix: a list saved empty on a new post (for example no checkboxes ticked) now stays empty instead of falling back to its default.

= 0.1.0 =
* First release: field group editor, 17 field types, location rules, conditional logic, options pages and the template API.
