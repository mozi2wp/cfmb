<?php
/**
 * Plugin bootstrap and service wiring.
 *
 * @package CFMB
 */

namespace CFMB\Core;

defined( 'ABSPATH' ) || exit;

use CFMB\Admin\Assets;
use CFMB\Admin\Builder_Screen;
use CFMB\Admin\Edit_Screens;
use CFMB\Admin\Form_Saver;
use CFMB\Admin\Go_Pro_Screen;
use CFMB\Admin\Group_Renderer;
use CFMB\Admin\List_Columns;
use CFMB\Admin\Menu;
use CFMB\Admin\Options_Screen;
use CFMB\Admin\Settings_Screen;
use CFMB\Blocks\Block_Assets;
use CFMB\Blocks\Block_Editor;
use CFMB\Blocks\Block_Registry;
use CFMB\Blocks\Block_Renderer;
use CFMB\Blocks\Template_Locator;
use CFMB\Cli\Cli;
use CFMB\Connections\Connection_Panels;
use CFMB\Connections\Connection_Registry;
use CFMB\Connections\Connection_Repository;
use CFMB\Connections\Connection_Saver;
use CFMB\Connections\Connection_Store;
use CFMB\Connections\Connections;
use CFMB\Content\Content_Mover;
use CFMB\Content\Options_Page_Repository;
use CFMB\Content\Options_Pages;
use CFMB\Content\Post_Type_Repository;
use CFMB\Content\Registrar;
use CFMB\Content\Taxonomy_Repository;
use CFMB\Display\Formatter;
use CFMB\Display\Resolver;
use CFMB\Display\Visibility;
use CFMB\Fields\Registry;
use CFMB\Fields\Term_Sync;
use CFMB\Icons\Icon_Library_Registry;
use CFMB\Integrations\Bindings\Editor_Assets as Bindings_Editor_Assets;
use CFMB\Integrations\Bindings\Editor_Values as Bindings_Editor_Values;
use CFMB\Integrations\Beaver\Beaver;
use CFMB\Integrations\Bindings\Source as Bindings_Source;
use CFMB\Integrations\Breakdance\Breakdance;
use CFMB\Integrations\Bricks\Bricks;
use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Field_Source;
use CFMB\Integrations\Builders\Loop_Items;
use CFMB\Integrations\Builders\Tag_Values;
use CFMB\Integrations\Divi\Divi;
use CFMB\Integrations\Elementor\Elementor;
use CFMB\Integrations\Graphql\Graphql;
use CFMB\Integrations\Oxygen\Oxygen;
use CFMB\Integrations\Seo\Seo_Analysis;
use CFMB\Integrations\Shortcode\Shortcode;
use CFMB\Integrations\Translation\Field_Keys;
use CFMB\Integrations\Translation\Id_Mapper;
use CFMB\Integrations\Translation\Translation;
use CFMB\Integrations\WooCommerce\WooCommerce;
use CFMB\Location\Matcher;
use CFMB\Location\Rule_Registry;
use CFMB\Media\Svg_Uploads;
use CFMB\Rest\Bindings_Controller;
use CFMB\Rest\Connections_Controller;
use CFMB\Rest\Block_Preview_Controller;
use CFMB\Rest\Content_Controller;
use CFMB\Rest\Field_Values_Rest;
use CFMB\Rest\Groups_Controller;
use CFMB\Rest\Icons_Controller;
use CFMB\Rest\Location_Controller;
use CFMB\Rest\Location_Match_Controller;
use CFMB\Rest\Object_Access;
use CFMB\Rest\Rows_Controller;
use CFMB\Rest\Search_Controller;
use CFMB\Rest\Switch_Controller;
use CFMB\Rest\Sync_Controller;
use CFMB\Schema\Field_Locator;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;
use CFMB\Schema\Local_Definitions;
use CFMB\Storage\Block_Store;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Store_Resolver;
use CFMB\Switching\Acf_Reader;
use CFMB\Switching\Backup;
use CFMB\Switching\Definition_Importer;
use CFMB\Switching\Switch_Assistant;
use CFMB\Switching\Value_Converter;
use CFMB\Sync\Sync_Files;
use CFMB\Sync\Sync_Manager;


/**
 * Entry point. Builds the container and registers hooks.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Get the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor. Use instance().
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Get the service container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Shortcut for container()->get().
	 *
	 * @template T of object
	 * @param class-string<T> $id Service id.
	 * @return T
	 *
	 * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint, Generic.Commenting.DocComment.MissingShort
	 */
	public function get( string $id ): object {
		/** @var T */
		return $this->container->get( $id );
	}
	// phpcs:enable

	/**
	 * Wire services and register hooks. Safe to call more than once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		$this->define_services();

		$hooked = array(
			Migrations::class,
			Registry::class,
			Rule_Registry::class,
			Group_Repository::class,
			Post_Type_Repository::class,
			Taxonomy_Repository::class,
			Options_Page_Repository::class,
			Connection_Repository::class,
			Registrar::class,
			Sync_Manager::class,
			Groups_Controller::class,
			Content_Controller::class,
			Sync_Controller::class,
			Field_Values_Rest::class,
			Location_Controller::class,
			Rows_Controller::class,
			Search_Controller::class,
			Icons_Controller::class,
			Term_Sync::class,
			Svg_Uploads::class,
			Block_Registry::class,
			Block_Preview_Controller::class,
			Bindings_Source::class,
			Bindings_Editor_Values::class,
			Bindings_Controller::class,
			Shortcode::class,
			Elementor::class,
			Divi::class,
			Bricks::class,
			Beaver::class,
			Breakdance::class,
			Oxygen::class,
			Connections::class,
			Connection_Saver::class,
			Connections_Controller::class,
			Switch_Controller::class,
			Location_Match_Controller::class,
			Translation::class,
			WooCommerce::class,
			Graphql::class,
		);

		if ( is_admin() ) {
			$hooked = array_merge(
				$hooked,
				array(
					Assets::class,
					Menu::class,
					Settings_Screen::class,
					Edit_Screens::class,
					Options_Screen::class,
					Form_Saver::class,
					List_Columns::class,
					Block_Editor::class,
					Bindings_Editor_Assets::class,
					Connection_Panels::class,
					Seo_Analysis::class,
				)
			);
		}

		foreach ( $hooked as $id ) {
			$this->get( $id )->register();
		}

		add_action( 'plugins_loaded', array( $this, 'announce' ), 5 );
		add_action( 'init', array( $this, 'collect_definitions' ), 1 );

		Cli::register( $this );
	}

	/**
	 * Let themes and plugins register definitions in code before CFMB
	 * registers post types and taxonomies (init, priority 5).
	 *
	 * @return void
	 */
	public function collect_definitions(): void {
		/**
		 * Fires when definitions can be registered in code with
		 * cfmb_register_group(), cfmb_register_post_type(),
		 * cfmb_register_taxonomy() and cfmb_register_options_page().
		 */
		do_action( 'cfmb/register' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Let add-ons such as CFMB Pro hook in once every plugin file is loaded.
	 *
	 * @return void
	 */
	public function announce(): void {
		/**
		 * Fires when CFMB is ready to be extended.
		 *
		 * @param Plugin $plugin CFMB instance.
		 */
		do_action( 'cfmb/loaded', $this );
	}

	/**
	 * Register service factories.
	 *
	 * @return void
	 */
	private function define_services(): void {
		$c = $this->container;

		$c->set( Registry::class, static fn() => new Registry() );
		$c->set( Rule_Registry::class, static fn() => new Rule_Registry() );
		$c->set( Sync_Files::class, static fn() => new Sync_Files() );
		$c->set( Local_Definitions::class, static fn( Container $c ) => new Local_Definitions( $c->get( Sync_Files::class ) ) );
		$c->set( Group_Repository::class, static fn( Container $c ) => new Group_Repository( $c->get( Local_Definitions::class ) ) );
		$c->set( Post_Type_Repository::class, static fn( Container $c ) => new Post_Type_Repository( $c->get( Local_Definitions::class ) ) );
		$c->set( Taxonomy_Repository::class, static fn( Container $c ) => new Taxonomy_Repository( $c->get( Local_Definitions::class ) ) );
		$c->set( Options_Page_Repository::class, static fn( Container $c ) => new Options_Page_Repository( $c->get( Local_Definitions::class ) ) );
		$c->set( Connection_Repository::class, static fn( Container $c ) => new Connection_Repository( $c->get( Local_Definitions::class ) ) );
		$c->set( Connection_Registry::class, static fn( Container $c ) => new Connection_Registry( $c->get( Connection_Repository::class ) ) );
		$c->set( Connection_Store::class, static fn() => new Connection_Store() );
		$c->set( Migrations::class, static fn() => new Migrations() );
		$c->set(
			Group_Sanitizer::class,
			static fn( Container $c ) => new Group_Sanitizer( $c->get( Registry::class ), $c->get( Rule_Registry::class ), $c->get( Group_Repository::class ) )
		);
		$c->set(
			Matcher::class,
			static fn( Container $c ) => new Matcher( $c->get( Group_Repository::class ), $c->get( Rule_Registry::class ) )
		);
		$c->set( Block_Store::class, static fn() => new Block_Store() );
		$c->set( Store_Resolver::class, static fn( Container $c ) => new Store_Resolver( $c->get( Block_Store::class ) ) );
		$c->set(
			Field_Values::class,
			static fn( Container $c ) => new Field_Values( $c->get( Registry::class ), $c->get( Store_Resolver::class ) )
		);
		$c->set(
			Field_Locator::class,
			static fn( Container $c ) => new Field_Locator( $c->get( Group_Repository::class ), $c->get( Matcher::class ) )
		);
		$c->set( Options_Pages::class, static fn() => new Options_Pages() );
		$c->set(
			Registrar::class,
			static fn( Container $c ) => new Registrar( $c->get( Post_Type_Repository::class ), $c->get( Taxonomy_Repository::class ), $c->get( Options_Page_Repository::class ), $c->get( Options_Pages::class ) )
		);
		$c->set(
			Content_Mover::class,
			static fn( Container $c ) => new Content_Mover( $c->get( Group_Repository::class ), $c->get( Post_Type_Repository::class ), $c->get( Taxonomy_Repository::class ) )
		);
		$c->set(
			Sync_Manager::class,
			static fn( Container $c ) => new Sync_Manager(
				$c->get( Sync_Files::class ),
				$c->get( Local_Definitions::class ),
				$c->get( Group_Repository::class ),
				$c->get( Group_Sanitizer::class ),
				$c->get( Post_Type_Repository::class ),
				$c->get( Taxonomy_Repository::class ),
				$c->get( Options_Page_Repository::class ),
				$c->get( Connection_Repository::class )
			)
		);
		$c->set(
			Groups_Controller::class,
			static fn( Container $c ) => new Groups_Controller( $c->get( Group_Repository::class ), $c->get( Group_Sanitizer::class ), $c->get( Sync_Manager::class ) )
		);
		$c->set(
			Content_Controller::class,
			static fn( Container $c ) => new Content_Controller( $c->get( Post_Type_Repository::class ), $c->get( Taxonomy_Repository::class ), $c->get( Options_Page_Repository::class ), $c->get( Content_Mover::class ), $c->get( Sync_Manager::class ), $c->get( Connection_Repository::class ), $c->get( Connection_Store::class ) )
		);
		$c->set( Sync_Controller::class, static fn( Container $c ) => new Sync_Controller( $c->get( Sync_Manager::class ) ) );
		$c->set(
			Field_Values_Rest::class,
			static fn( Container $c ) => new Field_Values_Rest( $c->get( Matcher::class ), $c->get( Field_Values::class ), $c->get( Group_Repository::class ) )
		);
		$c->set( List_Columns::class, static fn( Container $c ) => new List_Columns( $c->get( Matcher::class ), $c->get( Field_Values::class ) ) );
		$c->set( Location_Controller::class, static fn( Container $c ) => new Location_Controller( $c->get( Rule_Registry::class ) ) );
		$c->set( Object_Access::class, static fn( Container $c ) => new Object_Access( $c->get( Options_Pages::class ) ) );
		$c->set(
			Rows_Controller::class,
			static fn( Container $c ) => new Rows_Controller( $c->get( Matcher::class ), $c->get( Field_Values::class ), $c->get( Object_Access::class ) )
		);
		$c->set(
			Search_Controller::class,
			static fn( Container $c ) => new Search_Controller( $c->get( Group_Repository::class ), $c->get( Field_Values::class ), $c->get( Matcher::class ), $c->get( Object_Access::class ) )
		);
		$c->set( Icon_Library_Registry::class, static fn() => new Icon_Library_Registry() );
		$c->set( Icons_Controller::class, static fn( Container $c ) => new Icons_Controller( $c->get( Icon_Library_Registry::class ) ) );
		$c->set( Term_Sync::class, static fn() => new Term_Sync() );

		$c->set( Template_Locator::class, static fn() => new Template_Locator() );
		$c->set( Block_Assets::class, static fn( Container $c ) => new Block_Assets( $c->get( Template_Locator::class ) ) );
		$c->set(
			Block_Renderer::class,
			static fn( Container $c ) => new Block_Renderer( $c->get( Matcher::class ), $c->get( Field_Values::class ), $c->get( Store_Resolver::class ), $c->get( Template_Locator::class ) )
		);
		$c->set(
			Block_Registry::class,
			static fn( Container $c ) => new Block_Registry( $c->get( Group_Repository::class ), $c->get( Template_Locator::class ), $c->get( Block_Assets::class ), $c->get( Block_Renderer::class ) )
		);
		$c->set(
			Block_Preview_Controller::class,
			static fn( Container $c ) => new Block_Preview_Controller( $c->get( Block_Registry::class ), $c->get( Block_Renderer::class ), $c->get( Field_Values::class ), $c->get( Store_Resolver::class ), $c->get( Form_Saver::class ) )
		);
		$c->set(
			Block_Editor::class,
			static fn( Container $c ) => new Block_Editor( $c->get( Block_Registry::class ), $c->get( Block_Renderer::class ), $c->get( Group_Renderer::class ), $c->get( Assets::class ) )
		);

		$c->set( Visibility::class, static fn() => new Visibility() );
		$c->set( Resolver::class, static fn( Container $c ) => new Resolver( $c->get( Field_Locator::class ), $c->get( Visibility::class ) ) );
		$c->set( Formatter::class, static fn( Container $c ) => new Formatter( $c->get( Field_Values::class ), $c->get( Visibility::class ) ) );
		$c->set( Shortcode::class, static fn( Container $c ) => new Shortcode( $c->get( Resolver::class ), $c->get( Formatter::class ) ) );
		$c->set( Bindings_Source::class, static fn( Container $c ) => new Bindings_Source( $c->get( Resolver::class ), $c->get( Formatter::class ) ) );
		$c->set( Bindings_Editor_Values::class, static fn( Container $c ) => new Bindings_Editor_Values( $c->get( Matcher::class ), $c->get( Field_Values::class ) ) );
		$c->set( Bindings_Controller::class, static fn( Container $c ) => new Bindings_Controller( $c->get( Resolver::class ), $c->get( Formatter::class ) ) );
		$c->set(
			Bindings_Editor_Assets::class,
			static fn( Container $c ) => new Bindings_Editor_Assets( $c->get( Matcher::class ), $c->get( Formatter::class ), $c->get( Options_Pages::class ), $c->get( Assets::class ) )
		);
		$c->set( Field_Source::class, static fn( Container $c ) => new Field_Source( $c->get( Resolver::class ) ) );
		$c->set( Connections::class, static fn( Container $c ) => new Connections( $c->get( Connection_Registry::class ), $c->get( Connection_Store::class ), $c->get( Visibility::class ) ) );
		$c->set( Connection_Saver::class, static fn( Container $c ) => new Connection_Saver( $c->get( Connection_Registry::class ), $c->get( Connection_Store::class ) ) );
		$c->set( Connection_Panels::class, static fn( Container $c ) => new Connection_Panels( $c->get( Connection_Registry::class ), $c->get( Connection_Store::class ), $c->get( Assets::class ) ) );
		$c->set( Connections_Controller::class, static fn( Container $c ) => new Connections_Controller( $c->get( Connection_Registry::class ) ) );
		$c->set( Loop_Items::class, static fn( Container $c ) => new Loop_Items( $c->get( Field_Source::class ), $c->get( Field_Values::class ), $c->get( Formatter::class ), $c->get( Connections::class ) ) );
		$c->set( Tag_Values::class, static fn( Container $c ) => new Tag_Values( $c->get( Field_Source::class ), $c->get( Formatter::class ) ) );
		$c->set(
			Field_Catalog::class,
			static fn( Container $c ) => new Field_Catalog( $c->get( Group_Repository::class ), $c->get( Field_Values::class ), $c->get( Formatter::class ), $c->get( Loop_Items::class ), $c->get( Connection_Registry::class ) )
		);
		$c->set( Elementor::class, static fn() => new Elementor() );
		$c->set( Divi::class, static fn( Container $c ) => new Divi( $c->get( Field_Catalog::class ), $c->get( Tag_Values::class ) ) );
		$c->set( Bricks::class, static fn( Container $c ) => new Bricks( $c->get( Field_Catalog::class ), $c->get( Tag_Values::class ), $c->get( Loop_Items::class ) ) );
		$c->set( Beaver::class, static fn( Container $c ) => new Beaver( $c->get( Field_Catalog::class ), $c->get( Tag_Values::class ) ) );
		$c->set( Breakdance::class, static fn( Container $c ) => new Breakdance( $c->get( Field_Catalog::class ), $c->get( Tag_Values::class ) ) );
		$c->set( Oxygen::class, static fn( Container $c ) => new Oxygen( $c->get( Field_Catalog::class ), $c->get( Tag_Values::class ) ) );
		$c->set( Svg_Uploads::class, static fn() => new Svg_Uploads() );

		$c->set( Location_Match_Controller::class, static fn( Container $c ) => new Location_Match_Controller( $c->get( Matcher::class ) ) );
		$c->set( Field_Keys::class, static fn( Container $c ) => new Field_Keys( $c->get( Group_Repository::class ), $c->get( Registry::class ) ) );
		$c->set( Id_Mapper::class, static fn() => new Id_Mapper() );
		$c->set( Translation::class, static fn( Container $c ) => new Translation( $c->get( Field_Keys::class ), $c->get( Id_Mapper::class ) ) );
		$c->set( Seo_Analysis::class, static fn( Container $c ) => new Seo_Analysis( $c->get( Assets::class ) ) );
		$c->set(
			WooCommerce::class,
			static fn( Container $c ) => new WooCommerce( $c->get( Matcher::class ), $c->get( Group_Renderer::class ), $c->get( Form_Saver::class ) )
		);
		$c->set(
			Graphql::class,
			static fn( Container $c ) => new Graphql( $c->get( Group_Repository::class ), $c->get( Matcher::class ), $c->get( Field_Values::class ), $c->get( Visibility::class ), $c->get( Options_Pages::class ) )
		);

		$c->set( Acf_Reader::class, static fn() => new Acf_Reader() );
		$c->set( Backup::class, static fn() => new Backup() );
		$c->set(
			Definition_Importer::class,
			static fn( Container $c ) => new Definition_Importer(
				$c->get( Group_Repository::class ),
				$c->get( Group_Sanitizer::class ),
				$c->get( Post_Type_Repository::class ),
				$c->get( Taxonomy_Repository::class ),
				$c->get( Options_Page_Repository::class ),
				$c->get( Connection_Repository::class )
			)
		);
		$c->set( Value_Converter::class, static fn( Container $c ) => new Value_Converter( $c->get( Backup::class ) ) );
		$c->set(
			Switch_Assistant::class,
			static fn( Container $c ) => new Switch_Assistant(
				$c->get( Acf_Reader::class ),
				$c->get( Group_Repository::class ),
				$c->get( Group_Sanitizer::class ),
				$c->get( Post_Type_Repository::class ),
				$c->get( Taxonomy_Repository::class ),
				$c->get( Options_Page_Repository::class ),
				$c->get( Value_Converter::class ),
				$c->get( Backup::class ),
				$c->get( Definition_Importer::class )
			)
		);
		$c->set( Switch_Controller::class, static fn( Container $c ) => new Switch_Controller( $c->get( Switch_Assistant::class ) ) );

		$c->set( Assets::class, static fn() => new Assets() );
		$c->set(
			Menu::class,
			static fn( Container $c ) => new Menu( $c->get( Builder_Screen::class ), $c->get( Settings_Screen::class ), new Go_Pro_Screen(), $c->get( Assets::class ) )
		);
		$c->set(
			Builder_Screen::class,
			static fn( Container $c ) => new Builder_Screen(
				$c->get( Assets::class ),
				$c->get( Registry::class ),
				$c->get( Rule_Registry::class ),
				array(
					$c->get( Post_Type_Repository::class ),
					$c->get( Taxonomy_Repository::class ),
					$c->get( Options_Page_Repository::class ),
					$c->get( Connection_Repository::class ),
				)
			)
		);
		$c->set( Settings_Screen::class, static fn() => new Settings_Screen() );
		$c->set(
			Group_Renderer::class,
			static fn( Container $c ) => new Group_Renderer( $c->get( Field_Values::class ), $c->get( Assets::class ) )
		);
		$c->set(
			Edit_Screens::class,
			static fn( Container $c ) => new Edit_Screens( $c->get( Matcher::class ), $c->get( Group_Renderer::class ), $c->get( Assets::class ) )
		);
		$c->set(
			Options_Screen::class,
			static fn( Container $c ) => new Options_Screen( $c->get( Options_Pages::class ), $c->get( Matcher::class ), $c->get( Group_Renderer::class ) )
		);
		$c->set(
			Form_Saver::class,
			static fn( Container $c ) => new Form_Saver( $c->get( Matcher::class ), $c->get( Field_Values::class ), $c->get( Options_Pages::class ) )
		);
	}
}
