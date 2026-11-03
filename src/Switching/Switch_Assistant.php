<?php
/**
 * Switch Assistant: moves an ACF or SCF site to CFMB.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

use CFMB\Content\Content_Repository;
use CFMB\Content\Options_Page_Repository;
use CFMB\Content\Post_Type_Repository;
use CFMB\Content\Taxonomy_Repository;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Three steps, each safe to repeat:
 *
 * 1. scan(): what ACF or SCF has, and what CFMB already imported
 * 2. import(): create CFMB Field Groups, post types, taxonomies and options
 *    pages. Existing CFMB definitions with the same key or name are skipped.
 *    While ACF or SCF is still active, groups are imported inactive (both
 *    plugins would edit the same values) and post types they register wait.
 * 3. convert(): change stored values where CFMB stores them differently,
 *    in batches, after ACF and SCF are deactivated. Turns the imported
 *    groups on when done.
 *
 * Every changed row is kept in Backup; undo() puts them back and moves the
 * imported definitions to the trash.
 *
 * Runs are kept in the cfmb_switch_runs option (not autoloaded).
 *
 * Other plugins plug in as Switch_Source objects (`cfmb/switch/sources`).
 * Their runs share the backup, the run list and undo; the source does the
 * reading, the definitions and the value batches.
 */
class Switch_Assistant {

	public const RUNS_OPTION = 'cfmb_switch_runs';

	/**
	 * ACF storage ID (usually "options") => CFMB options page IDs whose
	 * values came from it. Compatibility Mode reads it.
	 */
	public const OPTION_IDS = 'cfmb_switch_option_ids';

	/**
	 * ACF and SCF definitions.
	 *
	 * @var Acf_Reader
	 */
	private Acf_Reader $reader;

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Field Group sanitizer.
	 *
	 * @var Group_Sanitizer
	 */
	private Group_Sanitizer $sanitizer;

	/**
	 * Content repositories by kind.
	 *
	 * @var array<string, Content_Repository>
	 */
	private array $content;

	/**
	 * Value conversion.
	 *
	 * @var Value_Converter
	 */
	private Value_Converter $converter;

	/**
	 * Original values.
	 *
	 * @var Backup
	 */
	private Backup $backup;

	/**
	 * Definition saving for other sources.
	 *
	 * @var Definition_Importer|null
	 */
	private ?Definition_Importer $definitions;

	/**
	 * Registered sources by ID, once read.
	 *
	 * @var array<string, Switch_Source>|null
	 */
	private ?array $sources = null;

	/**
	 * Constructor.
	 *
	 * @param Acf_Reader              $reader        ACF and SCF definitions.
	 * @param Group_Repository        $groups        Field Groups.
	 * @param Group_Sanitizer         $sanitizer     Field Group sanitizer.
	 * @param Post_Type_Repository    $post_types    Post types.
	 * @param Taxonomy_Repository     $taxonomies    Taxonomies.
	 * @param Options_Page_Repository $options_pages Options pages.
	 * @param Value_Converter         $converter     Value conversion.
	 * @param Backup                  $backup        Original values.
	 * @param Definition_Importer     $definitions   Definition saving for other sources.
	 */
	public function __construct( Acf_Reader $reader, Group_Repository $groups, Group_Sanitizer $sanitizer, Post_Type_Repository $post_types, Taxonomy_Repository $taxonomies, Options_Page_Repository $options_pages, Value_Converter $converter, Backup $backup, ?Definition_Importer $definitions = null ) {
		$this->reader      = $reader;
		$this->groups      = $groups;
		$this->sanitizer   = $sanitizer;
		$this->content     = array(
			'post_type'    => $post_types,
			'taxonomy'     => $taxonomies,
			'options_page' => $options_pages,
		);
		$this->converter   = $converter;
		$this->backup      = $backup;
		$this->definitions = $definitions;
	}

	/**
	 * Other plugins the assistant can switch from, by ID.
	 *
	 * @since 0.15.0
	 *
	 * @return array<string, Switch_Source>
	 */
	public function sources(): array {
		if ( null !== $this->sources ) {
			return $this->sources;
		}

		/**
		 * Filters the plugins, besides ACF and SCF, that the Switch Assistant can move a site from.
		 *
		 * @since 0.15.0
		 *
		 * @param Switch_Source[] $sources Sources.
		 */
		$list = apply_filters( 'cfmb/switch/sources', array() ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->sources = array();

		foreach ( is_array( $list ) ? $list : array() as $source ) {
			if ( $source instanceof Switch_Source && 1 === preg_match( '/^[a-z0-9-]{1,20}$/', $source->id() ) && ! in_array( $source->id(), array( 'acf', 'scf' ), true ) ) {
				$this->sources[ $source->id() ] = $source;
			}
		}

		return $this->sources;
	}

	/**
	 * One source.
	 *
	 * @param string $id Source ID.
	 * @return Switch_Source|null
	 */
	public function source( string $id ): ?Switch_Source {
		return $this->sources()[ $id ] ?? null;
	}

	/**
	 * Definition saving shared with other sources.
	 *
	 * @since 0.15.0
	 *
	 * @return Definition_Importer|null
	 */
	public function definitions(): ?Definition_Importer {
		return $this->definitions;
	}

	/**
	 * Backup shared with other sources.
	 *
	 * @since 0.15.0
	 *
	 * @return Backup
	 */
	public function backup(): Backup {
		return $this->backup;
	}

	/**
	 * Whether ACF or SCF is active.
	 *
	 * @return bool
	 */
	public static function source_active(): bool {
		return '' !== Acf_Reader::active_plugin();
	}

	/**
	 * What can be imported.
	 *
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		$acf_groups = $this->reader->groups();
		$mapper     = new Acf_Mapper( $acf_groups, $this->page_names() );
		$groups     = array();

		foreach ( $acf_groups as $key => $acf ) {
			$mapped   = $mapper->group( $acf );
			$groups[] = array(
				'key'      => (string) $key,
				'title'    => (string) ( $acf['title'] ?? $key ),
				'source'   => (string) ( $acf['source'] ?? 'db' ),
				'fields'   => self::count_fields( (array) ( $acf['fields'] ?? array() ) ),
				'imported' => null !== $this->groups->find( (string) $mapped['group']['key'] ),
				'notes'    => $mapped['notes'],
				'converts' => count( array_filter( $mapped['rules'], static fn( array $rule ): bool => 'value' !== $rule['kind'] ) ),
			);
		}

		$acf_runs = array_filter( $this->runs(), static fn( array $run ): bool => in_array( (string) ( $run['source'] ?? 'acf' ), array( 'acf', 'scf' ), true ) );

		return array(
			'plugin'       => Acf_Reader::active_plugin(),
			'sourceActive' => self::source_active(),
			'groups'       => $groups,
			'postTypes'    => $this->scan_content( 'post_type', $this->reader->post_types(), 'post_type' ),
			'taxonomies'   => $this->scan_content( 'taxonomy', $this->reader->taxonomies(), 'taxonomy' ),
			'optionsPages' => $this->scan_pages(),
			'runs'         => array_map( array( $this, 'summary' ), array_values( $acf_runs ) ),
			'sources'      => $this->source_list(),
		);
	}

	/**
	 * Other sources for the screen's switcher.
	 *
	 * @return array<int, array{id: string, label: string, found: bool, active: bool}>
	 */
	private function source_list(): array {
		$list = array();

		foreach ( $this->sources() as $source ) {
			$list[] = array(
				'id'     => $source->id(),
				'label'  => $source->label(),
				'found'  => $source->found(),
				'active' => $source->active(),
			);
		}

		return $list;
	}

	/**
	 * What another source can import, in the same shape as scan() plus `sections`.
	 *
	 * @since 0.15.0
	 *
	 * @param string $id Source ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function scan_source( string $id ) {
		$source = $this->source( $id );

		if ( ! $source ) {
			return self::unknown_source();
		}

		$found    = $source->scan();
		$sections = array();

		foreach ( (array) ( $found['sections'] ?? array() ) as $section ) {
			$sections[] = array(
				'id'    => (string) $section['id'],
				'title' => (string) $section['title'],
				'items' => array_values( (array) ( $section['items'] ?? array() ) ),
			);
		}

		$runs = array_filter( $this->runs(), static fn( array $run ): bool => ( $run['source'] ?? '' ) === $id );

		return array(
			'source'       => $id,
			'label'        => $source->label(),
			'sourceActive' => $source->active(),
			'sections'     => $sections,
			'notes'        => array_values( array_map( 'strval', (array) ( $found['notes'] ?? array() ) ) ),
			'runs'         => array_map( array( $this, 'summary' ), array_values( $runs ) ),
			'sources'      => $this->source_list(),
		);
	}

	/**
	 * Import picked definitions from another source and start a run.
	 *
	 * @since 0.15.0
	 *
	 * @param string                  $id     Source ID.
	 * @param array<string, string[]> $picked Item keys by section ID.
	 * @return array<string, mixed>|WP_Error Run summary.
	 */
	public function import_source( string $id, array $picked ) {
		$source = $this->source( $id );

		if ( ! $source ) {
			return self::unknown_source();
		}

		$clean = array();
		foreach ( $picked as $section => $keys ) {
			$clean[ sanitize_key( (string) $section ) ] = self::picked( $picked, (string) $section );
		}

		$run = $source->import( $clean, self::new_run( $id ) );

		if ( is_wp_error( $run ) ) {
			return $run;
		}

		if ( $source->active() && ! empty( $run['activate'] ) ) {
			/* translators: %s: plugin name */
			$run['notes'][] = sprintf( __( '%s is still active, so the imported field groups are off for now. Deactivate it, then convert the values here; the groups turn on when that is done.', 'custom-fields-meta-box' ), $source->label() );
		}

		$this->save_run( $run );

		return $this->summary( $run );
	}

	/**
	 * A fresh run record.
	 *
	 * @param string $source Source ID.
	 * @return array<string, mixed>
	 */
	private static function new_run( string $source ): array {
		return array(
			'id'       => 'run_' . gmdate( 'YmdHis' ) . '_' . bin2hex( random_bytes( 3 ) ),
			'source'   => $source,
			'started'  => time(),
			'status'   => 'imported',
			'created'  => array(),
			'activate' => array(),
			'rules'    => array(),
			'options'  => array(),
			'cursor'   => array(),
			'counts'   => array(),
			'notes'    => array(),
			'done'     => false,
		);
	}

	/**
	 * Import definitions and start a run.
	 *
	 * @param array<string, string[]> $picked Keys to import: groups, post_types, taxonomies, options_pages.
	 * @return array<string, mixed>|WP_Error Run summary.
	 */
	public function import( array $picked ) {
		$active = self::source_active();
		$run    = self::new_run( '' !== Acf_Reader::active_plugin() ? Acf_Reader::active_plugin() : 'acf' );

		$acf_groups = $this->reader->groups();
		$acf_pages  = $this->reader->options_pages();
		$names      = $this->page_names();
		$mapper     = new Acf_Mapper( $acf_groups, $names );
		$pages_used = array();

		foreach ( self::picked( $picked, 'options_pages' ) as $slug ) {
			$pages_used[ $slug ] = true;
		}

		foreach ( self::picked( $picked, 'groups' ) as $key ) {
			if ( ! isset( $acf_groups[ $key ] ) ) {
				continue;
			}

			$mapped = $mapper->group( $acf_groups[ $key ] );
			$title  = (string) $mapped['group']['title'];

			foreach ( $mapped['notes'] as $note ) {
				$run['notes'][] = $title . ': ' . $note;
			}

			foreach ( $mapped['rules'] as $rule ) {
				$run['rules'][] = $rule;
			}

			foreach ( $mapped['pages'] as $page ) {
				$slug = (string) ( array_search( $page, $names, true ) ?: $page );

				$pages_used[ $slug ] = true;
				$run['options'][]    = array(
					'page'   => $page,
					'acf_id' => self::acf_post_id( $acf_pages[ $slug ] ?? array() ),
					'rules'  => $mapped['rules'],
				);
			}

			if ( $this->groups->find( (string) $mapped['group']['key'] ) ) {
				/* translators: %s: field group title */
				$run['notes'][] = sprintf( __( '%s was imported before, so it was left as it is.', 'custom-fields-meta-box' ), $title );
				continue;
			}

			$wanted = ! empty( $mapped['group']['active'] );

			if ( $active ) {
				$mapped['group']['active'] = false;
			}

			$clean = $this->sanitizer->sanitize( $mapped['group'] );
			$saved = is_wp_error( $clean ) ? $clean : $this->groups->save( $clean );

			if ( is_wp_error( $saved ) ) {
				/* translators: 1: field group title, 2: error message */
				$run['notes'][] = sprintf( __( '%1$s could not be imported: %2$s', 'custom-fields-meta-box' ), $title, $saved->get_error_message() );
				continue;
			}

			$run['created'][] = array(
				'kind' => 'group',
				'key'  => (string) $clean['key'],
			);

			if ( $active && $wanted ) {
				$run['activate'][] = (string) $clean['key'];
			}
		}

		$this->import_content( $run, 'post_type', $this->reader->post_types(), self::picked( $picked, 'post_types' ), $mapper, $active );
		$this->import_content( $run, 'taxonomy', $this->reader->taxonomies(), self::picked( $picked, 'taxonomies' ), $mapper, $active );
		$this->import_pages( $run, $acf_pages, array_keys( $pages_used ), $mapper, $names );

		$this->remember_option_ids( (array) $run['options'] );

		if ( $active && $run['activate'] ) {
			$run['notes'][] = __( 'ACF or SCF is still active, so the imported field groups are off for now. Deactivate it, then convert the values here; the groups turn on when that is done.', 'custom-fields-meta-box' );
		}

		$this->save_run( $run );

		return $this->summary( $run );
	}

	/**
	 * Convert the next batch of values.
	 *
	 * @param string $id Run ID.
	 * @return array<string, mixed>|WP_Error Run summary.
	 */
	public function convert( string $id ) {
		$run = $this->runs()[ $id ] ?? null;

		if ( ! $run ) {
			return self::missing();
		}

		$source = $this->run_source( $run );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		if ( $source ? $source->active() : self::source_active() ) {
			return new WP_Error(
				'cfmb_switch_source_active',
				$source
					/* translators: %s: plugin name */
					? sprintf( __( 'Deactivate %s first. While it is active it would keep saving values in its own format.', 'custom-fields-meta-box' ), $source->label() )
					: __( 'Deactivate ACF or SCF first. While it is active it would keep saving values in its own format.', 'custom-fields-meta-box' ),
				array( 'status' => 409 )
			);
		}

		if ( in_array( $run['status'], array( 'done', 'undone', 'undoing' ), true ) ) {
			return $this->summary( $run );
		}

		$run           = $source ? $source->convert( $run ) : $this->converter->step( $run );
		$run['status'] = $run['done'] ? 'done' : 'converting';

		if ( $run['done'] ) {
			foreach ( (array) $run['activate'] as $key ) {
				$this->activate_group( (string) $key );
			}

			$this->definitions_flush();

			$run['activate'] = array();
			$run['finished'] = time();
		}

		$this->save_run( $run );

		return $this->summary( $run );
	}

	/**
	 * Undo the next batch: put values back, then trash what the run created.
	 *
	 * @param string $id Run ID.
	 * @return array<string, mixed>|WP_Error Run summary.
	 */
	public function undo( string $id ) {
		$run = $this->runs()[ $id ] ?? null;

		if ( ! $run ) {
			return self::missing();
		}

		if ( 'undone' === $run['status'] ) {
			return $this->summary( $run );
		}

		$source = $this->run_source( $run );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$run['status'] = 'undoing';

		if ( 0 === $this->backup->restore( (string) $run['id'] ) ) {
			if ( $source && ! $source->undo( $run ) ) {
				$this->save_run( $run );

				return $this->summary( $run );
			}

			foreach ( array_reverse( (array) $run['created'] ) as $item ) {
				if ( 'group' === $item['kind'] ) {
					$this->groups->trash( (string) $item['key'] );
				} elseif ( isset( $this->content[ $item['kind'] ] ) ) {
					$this->content[ $item['kind'] ]->trash( (string) $item['key'] );
				} elseif ( $this->definitions ) {
					$this->definitions->trash( (string) $item['kind'], (string) $item['key'] );
				}
			}

			$run['status'] = 'undone';
			$run['undone'] = time();
		}

		$this->save_run( $run );

		return $this->summary( $run );
	}

	/**
	 * Forget a finished run and its backup, once the user is happy.
	 *
	 * @param string $id Run ID.
	 * @return bool
	 */
	public function forget( string $id ): bool {
		$runs = $this->runs();

		if ( ! isset( $runs[ $id ] ) ) {
			return false;
		}

		$this->backup->forget( $id );
		unset( $runs[ $id ] );
		update_option( self::RUNS_OPTION, $runs, false );

		return true;
	}

	/**
	 * Every run by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function runs(): array {
		$runs = get_option( self::RUNS_OPTION, array() );

		return is_array( $runs ) ? $runs : array();
	}

	/**
	 * What the screen and WP-CLI show about a run.
	 *
	 * @param array<string, mixed> $run Run.
	 * @return array<string, mixed>
	 */
	public function summary( array $run ): array {
		return array(
			'id'       => (string) $run['id'],
			'source'   => (string) $run['source'],
			'started'  => (int) $run['started'],
			'status'   => (string) $run['status'],
			'created'  => count( (array) $run['created'] ),
			'pending'  => count( (array) $run['activate'] ),
			'store'    => (string) ( $run['cursor']['store'] ?? '' ),
			'counts'   => array(
				'converted' => (int) ( $run['counts']['converted'] ?? 0 ),
				'moved'     => (int) ( $run['counts']['moved'] ?? 0 ),
				'kept'      => (int) ( $run['counts']['kept'] ?? 0 ),
				'items'     => (int) ( $run['counts']['items'] ?? 0 ),
			),
			'backedUp' => in_array( $run['status'], array( 'undone' ), true ) ? 0 : $this->backup->count( (string) $run['id'] ),
			'notes'    => array_values( array_unique( (array) $run['notes'] ) ),
		);
	}

	/**
	 * Import post types or taxonomies.
	 *
	 * @param array<string, mixed>                $run    Run, updated.
	 * @param string                              $kind   post_type or taxonomy.
	 * @param array<string, array<string, mixed>> $items  ACF definitions by key.
	 * @param string[]                            $keys   Picked keys.
	 * @param Acf_Mapper                          $mapper Mapper.
	 * @param bool                                $active Whether ACF or SCF is active.
	 * @return void
	 */
	private function import_content( array &$run, string $kind, array $items, array $keys, Acf_Mapper $mapper, bool $active ): void {
		$repo = $this->content[ $kind ];

		foreach ( $keys as $key ) {
			if ( ! isset( $items[ $key ] ) ) {
				continue;
			}

			$raw   = 'post_type' === $kind ? $mapper->post_type( $items[ $key ] ) : $mapper->taxonomy( $items[ $key ] );
			$name  = (string) $raw['name'];
			$label = '' !== (string) $raw['title'] ? (string) $raw['title'] : $name;

			if ( $repo->find( (string) $raw['key'] ) || in_array( $name, $repo->names_taken(), true ) ) {
				/* translators: %s: post type or taxonomy label */
				$run['notes'][] = sprintf( __( '%s was imported before, so it was left as it is.', 'custom-fields-meta-box' ), $label );
				continue;
			}

			$exists = 'post_type' === $kind ? post_type_exists( $name ) : taxonomy_exists( $name );

			if ( $active && $exists ) {
				/* translators: %s: post type or taxonomy label */
				$run['notes'][] = sprintf( __( '%s is still registered by ACF or SCF. Deactivate it and run the import again to bring it over.', 'custom-fields-meta-box' ), $label );
				continue;
			}

			$clean = $repo->schema()->sanitize( $raw, null, $repo->names_taken() );
			$saved = is_wp_error( $clean ) ? $clean : $repo->save( $clean );

			if ( is_wp_error( $saved ) ) {
				/* translators: 1: post type or taxonomy label, 2: error message */
				$run['notes'][] = sprintf( __( '%1$s could not be imported: %2$s', 'custom-fields-meta-box' ), $label, $saved->get_error_message() );
				continue;
			}

			$run['created'][] = array(
				'kind' => $kind,
				'key'  => (string) $saved['key'],
			);
		}
	}

	/**
	 * Import the options pages that were picked or that imported groups use.
	 *
	 * @param array<string, mixed>                $run    Run, updated.
	 * @param array<string, array<string, mixed>> $pages  ACF pages by slug.
	 * @param string[]                            $slugs  Slugs to import.
	 * @param Acf_Mapper                          $mapper Mapper.
	 * @param array<string, string>               $names  Slug => CFMB page ID.
	 * @return void
	 */
	private function import_pages( array &$run, array $pages, array $slugs, Acf_Mapper $mapper, array $names ): void {
		$repo = $this->content['options_page'];

		foreach ( $slugs as $slug ) {
			$name = $names[ $slug ] ?? Acf_Mapper::page_name( $slug );
			$acf  = $pages[ $slug ] ?? array(
				'page_title' => ucwords( str_replace( array( '-', '_' ), ' ', $name ) ),
				'menu_slug'  => $slug,
			);

			if ( in_array( $name, $repo->names_taken(), true ) ) {
				continue;
			}

			$raw   = $mapper->options_page( $acf, $name );
			$clean = $repo->schema()->sanitize( $raw, null, $repo->names_taken() );
			$saved = is_wp_error( $clean ) ? $clean : $repo->save( $clean );

			if ( is_wp_error( $saved ) ) {
				/* translators: 1: options page title, 2: error message */
				$run['notes'][] = sprintf( __( 'The options page %1$s could not be imported: %2$s', 'custom-fields-meta-box' ), (string) $raw['title'], $saved->get_error_message() );
				continue;
			}

			$run['created'][] = array(
				'kind' => 'options_page',
				'key'  => (string) $saved['key'],
			);
		}
	}

	/**
	 * Post type or taxonomy rows for scan().
	 *
	 * @param string                              $kind  post_type or taxonomy.
	 * @param array<string, array<string, mixed>> $items ACF definitions.
	 * @param string                              $field Setting holding the name.
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_content( string $kind, array $items, string $field ): array {
		$repo = $this->content[ $kind ];
		$rows = array();

		foreach ( $items as $key => $item ) {
			$name   = (string) ( $item[ $field ] ?? '' );
			$rows[] = array(
				'key'      => (string) $key,
				'name'     => $name,
				'title'    => (string) ( $item['labels']['name'] ?? ( $item['title'] ?? $name ) ),
				'imported' => in_array( $name, $repo->names_taken(), true ),
			);
		}

		return $rows;
	}

	/**
	 * Options page rows for scan().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_pages(): array {
		$repo = $this->content['options_page'];
		$rows = array();

		foreach ( $this->reader->options_pages() as $slug => $page ) {
			$name   = Acf_Mapper::page_name( (string) $slug );
			$rows[] = array(
				'key'      => (string) $slug,
				'name'     => $name,
				'title'    => (string) ( $page['page_title'] ?? $slug ),
				'imported' => in_array( $name, $repo->names_taken(), true ),
			);
		}

		return $rows;
	}

	/**
	 * ACF options page slug => CFMB page ID.
	 *
	 * @return array<string, string>
	 */
	private function page_names(): array {
		$names = array();

		foreach ( array_keys( $this->reader->options_pages() ) as $slug ) {
			$names[ (string) $slug ] = Acf_Mapper::page_name( (string) $slug );
		}

		return $names;
	}

	/**
	 * Where ACF saved a page's values: "options" unless the page set its own post_id.
	 *
	 * @param array<string, mixed> $page ACF page.
	 * @return string
	 */
	private static function acf_post_id( array $page ): string {
		$id = (string) ( $page['post_id'] ?? 'options' );

		return in_array( $id, array( '', 'option', 'options' ), true ) || ! preg_match( '/^[a-z0-9_-]+$/i', $id ) ? 'options' : $id;
	}

	/**
	 * Remember which CFMB pages hold values from which ACF storage ID.
	 *
	 * @param array<int, array<string, mixed>> $entries Run options entries.
	 * @return void
	 */
	private function remember_option_ids( array $entries ): void {
		$ids = get_option( self::OPTION_IDS, array() );
		$ids = is_array( $ids ) ? $ids : array();

		foreach ( $entries as $entry ) {
			$acf_id = (string) $entry['acf_id'];
			$page   = (string) $entry['page'];

			if ( ! in_array( $page, (array) ( $ids[ $acf_id ] ?? array() ), true ) ) {
				$ids[ $acf_id ][] = $page;
			}
		}

		update_option( self::OPTION_IDS, $ids, false );
	}

	/**
	 * Turn on a group imported inactive.
	 *
	 * @param string $key Group key.
	 * @return void
	 */
	private function activate_group( string $key ): void {
		$group = $this->groups->find( $key );

		if ( $group && ! $group->active() ) {
			$data           = $group->to_array();
			$data['active'] = true;
			$this->groups->save( $data );
		}
	}

	/**
	 * Store a run.
	 *
	 * @param array<string, mixed> $run Run.
	 * @return void
	 */
	private function save_run( array $run ): void {
		$runs                        = $this->runs();
		$runs[ (string) $run['id'] ] = $run;

		update_option( self::RUNS_OPTION, $runs, false );
	}

	/**
	 * Picked keys of one kind.
	 *
	 * @param array<string, mixed> $picked Selection.
	 * @param string               $kind   Kind.
	 * @return string[]
	 */
	private static function picked( array $picked, string $kind ): array {
		return array_values( array_filter( array_map( 'strval', (array) ( $picked[ $kind ] ?? array() ) ) ) );
	}

	/**
	 * Count fields in a tree, layouts included.
	 *
	 * @param array<int, mixed> $fields Fields.
	 * @return int
	 */
	private static function count_fields( array $fields ): int {
		$count = 0;

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			++$count;
			$count += self::count_fields( (array) ( $field['sub_fields'] ?? array() ) );

			foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
				$count += self::count_fields( (array) ( $layout['sub_fields'] ?? array() ) );
			}
		}

		return $count;
	}

	/**
	 * The source that made a run: null for ACF and SCF.
	 *
	 * @param array<string, mixed> $run Run.
	 * @return Switch_Source|WP_Error|null
	 */
	private function run_source( array $run ) {
		$id = (string) ( $run['source'] ?? 'acf' );

		if ( in_array( $id, array( 'acf', 'scf' ), true ) ) {
			return null;
		}

		return $this->source( $id ) ?? new WP_Error( 'cfmb_switch_source_missing', __( 'The plugin that made this import is not active, so it can\'t continue. Activate CFMB Pro again.', 'custom-fields-meta-box' ), array( 'status' => 409 ) );
	}

	/**
	 * Re-register post types and rewrite rules after a run finished, since
	 * imported post types may have been waiting.
	 *
	 * @return void
	 */
	private function definitions_flush(): void {
		\CFMB\Content\Registrar::request_rewrite_flush();
	}

	/**
	 * Unknown source.
	 *
	 * @return WP_Error
	 */
	private static function unknown_source(): WP_Error {
		return new WP_Error( 'cfmb_switch_source', __( 'That plugin can\'t be switched from here. Reload the page.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
	}

	/**
	 * Run not found.
	 *
	 * @return WP_Error
	 */
	private static function missing(): WP_Error {
		return new WP_Error( 'cfmb_switch_run', __( 'That import was not found. Reload the page.', 'custom-fields-meta-box' ), array( 'status' => 404 ) );
	}
}
