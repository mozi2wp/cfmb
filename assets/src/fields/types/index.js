/**
 * Field type -> edit screen component. Add-ons register more through
 * window.cfmb.fieldComponents before groups render.
 */
import TextInput from './TextInput';
import Textarea from './Textarea';
import Range from './Range';
import Wysiwyg from './Wysiwyg';
import Media from './Media';
import Select from './Select';
import CheckboxList from './CheckboxList';
import RadioList from './RadioList';
import ButtonGroup from './ButtonGroup';
import Toggle from './Toggle';
import GroupField from './GroupField';
import Rows from './Rows';
import Gallery from './Gallery';
import Link from './Link';
import Oembed from './Oembed';
import Relational from './Relational';
import Relationship from './Relationship';
import DateTime from './DateTime';
import Color from './Color';
import Icon from './Icon';
import Map from './Map';

export const components = {
	text: TextInput,
	email: TextInput,
	url: TextInput,
	password: TextInput,
	number: TextInput,
	textarea: Textarea,
	range: Range,
	wysiwyg: Wysiwyg,
	image: Media,
	file: Media,
	select: Select,
	checkbox: CheckboxList,
	radio: RadioList,
	button_group: ButtonGroup,
	toggle: Toggle,
	group: GroupField,
	repeater: Rows,
	stacks: Rows,
	gallery: Gallery,
	link: Link,
	oembed: Oembed,
	post: Relational,
	page_link: Relational,
	user: Relational,
	taxonomy: Relational,
	relationship: Relationship,
	date: DateTime,
	time: DateTime,
	datetime: DateTime,
	color: Color,
	icon: Icon,
	map: Map,
};

// Rendered as a fieldset with a legend instead of a label.
export const groupedTypes = new Set( [
	'checkbox',
	'radio',
	'button_group',
	'image',
	'file',
	'group',
	'repeater',
	'stacks',
	'gallery',
	'link',
	'relationship',
	'icon',
	'map',
	'taxonomy',
] );
