/**
 * Picks the right control for Post, Page link, User and Taxonomy fields.
 */
import AsyncPicker from './AsyncPicker';
import TermChoices from './TermChoices';

export default function Relational( props ) {
	const { field } = props;
	const settings = field.settings || {};

	if ( field.type === 'taxonomy' ) {
		const appearance = settings.appearance || 'checkbox';
		if ( appearance === 'checkbox' || appearance === 'radio' ) {
			return (
				<TermChoices
					{ ...props }
					multiple={ appearance === 'checkbox' }
				/>
			);
		}
		return (
			<AsyncPicker
				{ ...props }
				multiple={ appearance === 'multi_select' }
			/>
		);
	}

	return <AsyncPicker { ...props } multiple={ !! settings.multiple } />;
}
