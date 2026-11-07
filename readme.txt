=== CFMB - Custom Fields Meta Box ===
Contributors: mozi2wp
Tags: custom fields, meta box, custom post types, repeater, options page
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free custom fields and meta boxes with repeater, flexible content, gallery, custom post types, taxonomies and options pages. Nothing locked.

== Description ==

**CFMB - Custom Fields Meta Box** is a free WordPress custom fields plugin. Add meta boxes with your own fields to posts, pages, custom post types, taxonomies, users, comments, menus, widgets and options pages, then show the values in your theme, in Gutenberg blocks and in page builders like Elementor and Bricks.

Fields that other plugins sell in a Pro version are free here: the **Repeater field**, **flexible content layouts**, the **Gallery field**, **options pages**, **custom blocks** and **custom post types and taxonomies**. Nothing is locked, limited or time-limited.

Values are saved as normal WordPress post meta, term meta and user meta under the field name, so they work with `get_post_meta()`, `WP_Query`, `meta_query` and other plugins.

= Why choose CFMB =

* **One plugin for content modeling.** Custom fields, meta boxes, custom post types, custom taxonomies, options pages and two-way relationships, all made in the admin.
* **A free ACF alternative.** Repeater, flexible content (Stacks), gallery, clone (Presets), options pages and blocks are included at no cost.
* **Easy switch from ACF or Secure Custom Fields.** The Switch Assistant imports field groups and values, and can undo the import.
* **Clean, queryable data.** Dates, lists and links are saved so `meta_query` can sort and filter them.
* **Fast with large repeaters.** Rows load only when opened and can be split into pages.
* **Works with your stack.** Gutenberg, the classic editor, Elementor, Bricks, Divi, Beaver Builder, Breakdance, Oxygen, WooCommerce, WPML, Polylang, Yoast SEO, Rank Math, the REST API and WPGraphQL.

= 35+ custom field types =

* **Basic:** text, text area, number, range, email, URL, password.
* **Content:** WYSIWYG rich text editor, image, file, gallery, oEmbed.
* **Choice:** select, checkboxes, radio buttons, button group, true/false toggle.
* **Relational:** link, post object, page link, relationship, taxonomy, user.
* **Pickers:** date picker, time picker, date and time picker, color picker, icon picker, Google Maps and OpenStreetMap.
* **Layout:** message, tab, accordion.
* **Structure:** Group, Repeater, Stacks (flexible content: rows that each pick a layout, in any order) and Presets (reuse the fields of another group and keep them in sync, like a clone field).

Repeater rows collapse, load their fields only when opened, and can be split into pages. Rows can be duplicated, moved and copied between posts.

= Show meta boxes exactly where you need them =

Location rules cover post types, page templates, post status, post format, post categories and terms, taxonomies, user roles, user forms, options pages, comments, attachments, menus, menu items, widgets, blocks, and WooCommerce product types and variations. Combine rules with "and" and "or".

Meta boxes show or hide as soon as an editor picks another template, format or category, without saving first. Conditional logic shows a field only when other fields have certain values.

= Custom post types, taxonomies and options pages =

* **Custom post types and custom taxonomies** made in the admin, with every WordPress setting. Labels fill in from the singular and plural names, so you don't need a separate CPT plugin.
* **Options pages** for site-wide settings, made in the admin or with one function call.
* **Connections:** two-way relationships between posts, terms and users, such as books and their authors. One to one, one to many or many to many, with a meta box on each side. Links live in their own indexed table.

= Display custom fields anywhere =

* **Template functions:** `cfmb_get_field()`, `cfmb_the_field()`, `cfmb_have_rows()` and more.
* **Field Blocks:** turn a field group into a custom Gutenberg block. Editors fill it in the sidebar or in the block, and a PHP template in your theme renders it. Inner blocks, colors, spacing and styles work like core blocks.
* **Block Bindings:** show a custom field in a paragraph, heading, button or image block, with no code.
* **Shortcode:** `[cfmb field="price"]`, which you can turn off.
* **Page builder dynamic data:** CFMB fields appear as dynamic tags in Elementor, Divi 4 and 5, Bricks, Beaver Builder with Beaver Themer, Breakdance, Oxygen 6 and Oxygen Classic. A Field Loop repeats a template for each repeater row, gallery image, related post or connected item.
* **REST API and WPGraphQL:** opt in per field group, for headless WordPress.
* **Admin columns:** show, sort and filter any field on post, term and user lists.

Only values a visitor may already see are shown. Nothing comes from drafts, private or password-protected posts, password fields, or other people's profiles.

= Integrations =

* **WooCommerce:** custom fields on products and on each variation, and a tab in the Product data box.
* **WPML and Polylang:** per-field translation settings (translate, copy, copy once or ignore). Copied images, posts and terms point at their translations.
* **Yoast SEO and Rank Math:** text, rich text and image fields count in their content analysis.
* **Block editor and classic editor.**

= For developers and agencies =

* **Schema Sync:** field groups, post types, taxonomies and options pages are saved as JSON files you can keep in Git. Changes show up for review, with a side-by-side merge when both sides changed.
* **Register fields in PHP** with `cfmb_register_group()`, or export PHP code from CFMB > Sync.
* **WP-CLI:** `wp cfmb sync`, `export`, `import`, `group`, `field`, `doctor` and `switch`.
* **Hooks** for every step, all under `cfmb/`.

= Migrate from ACF or Secure Custom Fields =

The Switch Assistant moves field groups, post types, taxonomies, options pages and values from Advanced Custom Fields (ACF) or Secure Custom Fields (SCF) to CFMB. It keeps a copy of every value it changes, so "Undo import" puts everything back exactly as it was.

Does your theme call `get_field()` and `have_rows()`? Install the free [CFMB Compatibility Mode](https://wordpress.org/plugins/cfmb-compatibility-mode/) plugin and those calls keep working while you update your templates.

= CFMB Pro =

Everything above is free and stays free. [CFMB Pro](https://mozi2wp.com/cfmb-pro) is a separate plugin that adds new features, such as custom table storage, safe field renames, field history, front-end forms, member pages and a query builder. It never unlocks code inside this plugin.

= Privacy and outside services =

CFMB does not track you and sends nothing anywhere on its own. These features contact outside services from the editor's browser, and only after you turn them on:

* Map fields with OpenStreetMap, chosen under CFMB > Settings or on a field. Map tiles load from tile.openstreetmap.org. Address search, and the address for a placed pin, come from nominatim.openstreetmap.org. See the [OpenStreetMap privacy policy](https://osmfoundation.org/wiki/Privacy_Policy) and [tile usage policy](https://operations.osmfoundation.org/policies/tiles/).
* Map fields with Google Maps and your own API key. The map, address search and the address for a placed pin load from maps.googleapis.com. See the [Google privacy policy](https://policies.google.com/privacy) and the [Google Maps terms](https://cloud.google.com/maps-platform/terms).
* oEmbed fields use WordPress's own embed feature, which asks the linked site (such as YouTube) for the embed.

No map service is chosen by default. In that case map fields show plain address and coordinate inputs, and nothing loads from outside.

= Source code =

The full JavaScript and SCSS source is in the `assets/src` folder, and the `build` folder holds the compiled files. The repository is at [github.com/mozi2wp/cfmb](https://github.com/mozi2wp/cfmb). To rebuild, run `npm install` and `npm run build`.

= Third-party code =

* [Leaflet](https://leafletjs.com/) 1.9.4, BSD-2-Clause, for OpenStreetMap maps (bundled in `build/map.js`).
* [svg-sanitizer](https://github.com/darylldoyle/svg-sanitizer) 0.20.0 by Daryll Doyle, GPL-2.0-or-later, for cleaning SVG uploads (in `vendor-prefixed`, under the `CFMB\Vendor` namespace).
* [WordPress icons](https://github.com/WordPress/gutenberg/tree/trunk/packages/icons) (`@wordpress/icons`), GPL-2.0-or-later, for the admin screens and the Icon field.
* [Font Awesome Free](https://fontawesome.com/) icons by Fonticons, Inc., CC BY 4.0, bundled as SVG in `assets/icons/fontawesome.json` for the Icon field (see `assets/icons/fontawesome-LICENSE.txt`). No font files or CDN are loaded.

== Installation ==

1. In your WordPress admin, go to Plugins > Add New, search for "CFMB Custom Fields Meta Box", then install and activate it.
2. Go to CFMB > Field Groups and click "Add field group".
3. Add your fields, choose where the meta box shows under Location, and save.
4. Open a matching edit screen to fill in the fields.

To show a custom field value in your theme:

`<?php cfmb_the_field( 'subtitle' ); ?>`

== Frequently Asked Questions ==

= Is CFMB really free? =

Yes. Every feature in this plugin is free, with no locked fields, no limits and no trial period. CFMB Pro is a separate plugin with extra features.

= Is CFMB a free alternative to ACF Pro? =

CFMB includes free versions of the features people usually buy ACF Pro for: the Repeater field, flexible content layouts (Stacks), the Gallery field, options pages, reusable fields (Presets, like a clone field) and custom blocks. It also makes custom post types and taxonomies. CFMB is a separate plugin and is not affiliated with ACF.

= Does CFMB have a free Repeater field? =

Yes. The Repeater field is free, with collapsible rows, drag and drop, duplicate, paging for long lists and copying rows between posts. Loop over it with `cfmb_have_rows()`.

= Does CFMB have flexible content? =

Yes. The Stacks field works like flexible content: each row picks a layout, such as hero, gallery or quote, in any order. In templates, `cfmb_get_row_layout()` tells you which layout a row uses.

= How do I migrate from ACF to CFMB? =

Open CFMB > Switch Assistant. It imports field groups, post types, taxonomies, options pages and values from ACF or Secure Custom Fields, and "Undo import" reverses it. On large sites use `wp cfmb switch run`. To keep `get_field()` calls in your theme working, install CFMB Compatibility Mode.

= Can CFMB replace a custom post type plugin? =

Yes. CFMB makes custom post types and custom taxonomies in the admin, with every WordPress setting. If you rename one later, CFMB offers to move existing posts or terms to the new key.

= Where are custom field values saved? =

In the normal WordPress meta tables, under the field name, for example `price`.

* Values inside a Group or a row get their own keys too: `hero_title`, `items_0_title`. A Repeater saves its row count, and a Stacks field saves each row's layout name, so `meta_query` can find posts that use a layout.
* Links and maps save each part on its own (`cta_url`, `loc_lat`, `loc_lng`).
* Dates are saved as `2026-10-15`, times as `14:30:00` and date-times as `2026-10-15 14:30:00`, so they sort and compare correctly.
* Lists, such as checked boxes, are saved as one meta row per item, so `meta_query` matches one item without slow `LIKE` searches.
* Options page values are saved as `cfmb_{page}_{field}` options and widget values as `cfmb_widget_{widget id}_{field}`. Neither is autoloaded.
* Comment values are comment meta. Menu item and attachment values are post meta. Menu values are term meta.
* Field groups and other definitions are JSON in private post types, and one query loads them all.
* Connection links are rows in the `{prefix}cfmb_connections` table, indexed both ways.

= How do I display custom fields in my theme? =

`<?php $price = cfmb_get_field( 'price' ); ?>`

Loop over a Repeater or Stacks field:

`<?php while ( cfmb_have_rows( 'sections' ) ) : cfmb_the_row(); ?>`
`<?php if ( 'hero' === cfmb_get_row_layout() ) { cfmb_the_sub_field( 'heading' ); } ?>`
`<?php endwhile; ?>`

Values from terms, users, comments, widgets and options pages:

`cfmb_get_field( 'color', 'term_12' );`
`cfmb_get_field( 'twitter', 'user_3' );`
`cfmb_get_field( 'rating', 'comment_5' );`
`cfmb_get_field( 'accent', 'widget:text-3' );`
`cfmb_get_field( 'phone', 'option' );`

= Can I show custom fields without code? =

Yes. Use Block Bindings in the block editor, the `[cfmb field="price"]` shortcode, or the dynamic tags in Elementor, Bricks, Divi, Beaver Builder, Breakdance and Oxygen.

= How do I create a custom Gutenberg block with fields? =

Open a field group, turn on "Use as a Field Block" and give the block a name, for example `hero`. Then add a template to your theme at `cfmb-blocks/hero.php`:

`<section <?php echo cfmb_block_wrapper_attributes(); ?>>`
`<h2><?php cfmb_the_field( 'heading' ); ?></h2>`
`<InnerBlocks />`
`</section>`

Inside the template, `cfmb_get_field()` reads the block's own values. Pass `get_the_ID()` to read the post instead. The template also gets `$block`, `$fields`, `$content`, `$is_preview`, `$post_id` and `$context`. Put `hero.css` and `hero.js` next to it and they load only on pages that use the block.

Block values are saved inside the block, so the same block can appear many times with different values. They can't be searched with `meta_query`.

= My Field Block shows "Template missing". =

Add the template file named in the message to your theme, or set its path under "More settings" in the field group's Field Block settings. CFMB only loads templates from your theme, the plugins folder, or a folder added with the `cfmb/blocks/template_paths` filter. `wp cfmb doctor` lists blocks without a template.

= How do Block Bindings and the shortcode pick the object? =

Both read the current post by default (the post in a Query Loop). The shortcode's `object` attribute can also be `term` (the archive's term), `author` (the post's author), `user` (the logged-in visitor), `option` or `option:{page}`, or one object like `post_12`, `term_12` or `user_3`.

`[cfmb field="logo" object="option:site_settings" size="medium"]`

Repeater, Group and Stacks values don't show this way. Use a Field Loop in a page builder, or `cfmb_have_rows()` in a template.

= Can authors print any field with the shortcode? =

Only fields of things visitors can already see: published posts of public post types, public taxonomies, options pages, and their own or the post author's user fields. Password fields never show. Keep secrets such as API keys in a Password field, or turn the shortcode off under CFMB > Settings > Display.

= Does CFMB work with Elementor, Bricks and other page builders? =

Yes. Elementor gets a "CFMB" group of dynamic tags: Field, URL, Image, Gallery, Number, Color and Date. Divi gets CFMB Field, Date, URL and Image entries. Bricks gets `{cfmb_…}` tags such as `{cfmb_price}` or `{cfmb_items.title}` inside a loop, and every loopable field is a Bricks query type. Beaver Themer gets field connections. Breakdance and Oxygen 6 list every field under "CFMB" in their dynamic data. Oxygen Classic gets CFMB Field, URL and Image.

Each tag has a "Read from" setting: current post, archive term, post author, logged-in user, an options page, or one post, term or user.

= Which page builder versions work? =

Elementor 3.5 or later (free or Pro), Divi 4 and Divi 5, Bricks 1.9 or later, Beaver Builder 2.5 or later (field connections need Beaver Themer), Breakdance 2 or later, Oxygen 6, and Oxygen Classic 4. Each integration loads only when its builder is active.

= Can a Field Loop show its own template inside itself? =

No. A template that is the page being edited is skipped, and loops stop after eight levels of nesting.

= How do I create relationships between posts, users and terms? =

Use a Connection. It links posts, terms and users both ways, with a meta box on each side. In code:

`foreach ( cfmb_get_connected( 'book_authors' ) as $author ) { echo esc_html( $author->display_name ); }`

`new WP_Query( array( 'post_type' => 'book', 'cfmb_connected' => array( 'connection' => 'book_authors', 'object' => 'user_3' ) ) );`

`cfmb_connect()` and `cfmb_disconnect()` change links in code. Results only include items the visitor may see.

= What happens to Connection links when I delete a post? =

The links stay in the table, but deleted, draft and private items never show on the site or in the REST API.

= Can I keep field groups in Git? =

Yes, with Schema Sync. Each time you save a field group, post type, taxonomy or options page, CFMB writes it to `wp-content/uploads/cfmb-json/{key}.json`. Commit that file, or copy it into a `cfmb-json` folder in your theme, and deploy it. On the other site, CFMB > Sync shows what changed. Pull a newer file into the database, write a newer database copy to its file, or review a side-by-side diff when both changed. A file changed outside CFMB is never overwritten on save.

The folder gets an `.htaccess` file so Apache doesn't serve it. On nginx, block `/wp-content/uploads/cfmb-json/` in the server settings.

= How do I reuse the same fields in several field groups? =

Use Presets. Build a field group with the fields you want to reuse, and turn on "Use only as a preset" so it doesn't show on its own. In another group, add a Preset field and pick that group. Its fields appear in place and stay in sync. With prefixed names, values are saved as `{preset name}_{field name}`, so the same preset can be used twice.

= Can I rename a field? =

Yes. Each field has a fixed key, so renaming it is safe for the field group. Values already saved under the old name stay under the old name.

= Can I rename a custom post type or taxonomy? =

Yes. CFMB asks whether to move existing posts or terms to the new key, and points location rules at the new key either way.

= What happens to blocks if I rename a Field Block? =

Blocks already in your content keep the old name, so the editor no longer recognizes them. The field group editor warns you before you save. Change the name back and they work again.

= Does CFMB work with WooCommerce? =

Yes. Add custom fields to products and to each product variation, and show them in a tab in the Product data box. Location rules can target product types.

= Does CFMB work with WPML and Polylang? =

Yes. Each field has a translation setting. Fields set to Translate or Copy once keep their own value per language. Fields set to Copy stay the same in every language. Options pages keep one value per language for translated fields. Switch the language in the admin bar to edit another one.

= Why does a meta box appear only after I pick a template or category? =

A group whose rules depend on the template, post format, status, terms or product type waits hidden on the edit screen. It appears as soon as the form matches its rules, and it is saved only if it still matches when you save.

= Why can I save a draft with an empty required field? =

Drafts are work in progress. Required fields must be filled in before a post is published.

= Can I upload SVG icons? =

Yes, after you turn on "Allow SVG files in the media library" under CFMB > Settings. It is off by default. Every SVG is cleaned on upload: scripts, event handlers and outside references are removed, and files that can't be cleaned are refused.

= Can I edit widget or menu item fields in the Customizer? =

No. The Customizer previews changes before you publish, while CFMB saves values right away. The Customizer links to the Widgets or Menus screen, where those fields are edited.

= What happens to my content if I delete CFMB? =

Values on your posts, terms and users, and Connection links, are always kept. Field groups, other definitions, Switch Assistant backups and settings are removed only if you turn on "Delete field groups and CFMB settings" under CFMB > Settings.

= Who can manage field groups? =

Administrators, through the `manage_options` capability. Change it with the `cfmb/admin/capability` filter.

== Screenshots ==

1. All field groups at a glance, with where each meta box shows and how many custom fields it has.
2. The field group editor: add custom fields, drag them into order and open one to change its settings.
3. Location rules decide which post types, templates, taxonomies or users get the meta box.
4. Custom fields in a meta box on the post edit screen, organized with tabs.
5. Plugin settings, each section on its own card.
6. Schema Sync compares the database with your JSON files for version control.
7. Create custom post types in the admin, with every WordPress setting.
8. 36 custom field types, all free: text, image, gallery, relationship, date, color, icon, map, Repeater, Stacks and more.
9. Free Repeater field and flexible content (Stacks) layouts, with simple template loops.
10. Custom Gutenberg blocks from field groups, and Block Bindings for core blocks.
11. Custom fields as dynamic tags in Elementor, Bricks, Divi, Beaver Builder, Breakdance and Oxygen.
12. Location rules show meta boxes on posts, taxonomies, users, options pages, menus, widgets and WooCommerce products.
13. Switch from ACF or Secure Custom Fields with the Switch Assistant, with undo.
14. Integrations with WooCommerce, WPML, Polylang, Yoast SEO, Rank Math, the REST API, WPGraphQL and WP-CLI.

== Changelog ==

= 1.0.0 =
* First release on WordPress.org.
* New name: "CFMB - Custom Fields Meta Box", slug `custom-fields-meta-box`. The `cfmb_` functions, `cfmb/` hooks, options and tables are unchanged.
* Compatibility Mode moved into its own plugin, CFMB Compatibility Mode.
* Admin screens: icons from the WordPress icon set, a type icon on each field row, colours that follow the admin colour scheme, and settings on cards.
* Date fields show the saved date in every time zone, instead of the day before for editors east of the site's time zone.
* Font Awesome Free 6.6 and later work in the Icon field.
* Database queries name tables and columns through `%i` placeholders.
* Release builds ship only the production autoloader.

== Upgrade Notice ==

= 1.0.0 =
First release on WordPress.org. If you used Compatibility Mode, install the CFMB Compatibility Mode plugin.
