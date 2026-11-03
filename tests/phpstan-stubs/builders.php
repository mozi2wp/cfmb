<?php
/**
 * Empty parent classes from page builders, so PHPStan can read CFMB's
 * integrations without the builders installed. Never loaded at runtime.
 *
 * @package CFMB
 */

// phpcs:disable

namespace Elementor {
	abstract class Widget_Base {}
	class Controls_Manager {}
	class Plugin {}
}

namespace Elementor\Core\DynamicTags {
	abstract class Tag {}
	abstract class Data_Tag {}
}

namespace Breakdance\DynamicData {
	abstract class StringField {}
	abstract class ImageField {}
	abstract class GalleryField {}
	abstract class RepeaterField {}
	class StringData {}
	class ImageData {}
	class GalleryData {}
	class RepeaterData {}
}

namespace {
	class FLBuilderModule {}
	class FLBuilderModel {}
	class ET_Builder_Module {}
	class OxyEl {}
}
