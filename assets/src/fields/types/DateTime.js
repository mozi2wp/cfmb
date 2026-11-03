/**
 * Date, time and date-time fields.
 *
 * Values are stored as 2026-10-15, 14:30:00 and 2026-10-15 14:30:00. Dates
 * open the WordPress calendar, which honours the field's week start and
 * earliest/latest dates; times use the browser's own time input.
 */
import { Button, DatePicker, DateTimePicker, Dropdown } from '@wordpress/components';
import { dateI18n, getSettings } from '@wordpress/date';
import { __ } from '@wordpress/i18n';

const pad = ( number ) => String( number ).padStart( 2, '0' );

/**
 * Day of a Date object in YYYY-MM-DD form, in local calendar terms.
 *
 * @param {Date} date Date.
 * @return {string} Day.
 */
const dayOf = ( date ) => `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }`;

/**
 * Whether a calendar day is outside the field's limits.
 *
 * @param {Date}   date     Day shown in the calendar.
 * @param {Object} calendar { min, max } in the storage format.
 * @return {boolean} Outside.
 */
export function outsideLimits( date, calendar ) {
	const day = dayOf( date );
	const min = ( calendar?.min || '' ).slice( 0, 10 );
	const max = ( calendar?.max || '' ).slice( 0, 10 );
	return ( min !== '' && day < min ) || ( max !== '' && day > max );
}

function TimeInput( { field, id, value, onChange, describedBy } ) {
	return (
		<div className="cfmb-datetime">
			<input
				id={ id }
				className="cfmb-input cfmb-datetime__input"
				type="time"
				step={ 1 }
				value={ value || '' }
				aria-describedby={ describedBy }
				aria-required={ field.required || undefined }
				onChange={ ( event ) => onChange( /^\d\d:\d\d$/.test( event.target.value ) ? `${ event.target.value }:00` : event.target.value ) }
			/>
			{ value && (
				<Button variant="tertiary" size="compact" onClick={ () => onChange( '' ) }>
					{ __( 'Clear', 'custom-fields-meta-box' ) }
				</Button>
			) }
		</div>
	);
}

export default function DateTime( props ) {
	const { field, id, value, onChange, describedBy } = props;

	if ( field.type === 'time' ) {
		return <TimeInput { ...props } />;
	}

	const settings = field.settings || {};
	const calendar = settings.calendar || {};
	const withTime = field.type === 'datetime';
	const formats = getSettings()?.formats || {};
	const siteFormat = withTime ? `${ formats.date || 'F j, Y' } ${ formats.time || 'g:i a' }` : formats.date || 'F j, Y';
	const shown = value ? dateI18n( settings.display_format || siteFormat, value ) : '';

	const pick = ( picked ) => {
		if ( ! picked ) {
			return;
		}
		// The pickers answer with an ISO string like 2026-10-15T14:30:00.
		const text = String( picked ).replace( 'T', ' ' ).slice( 0, 19 );
		onChange( withTime ? ( text.length === 16 ? `${ text }:00` : text ) : text.slice( 0, 10 ) );
	};

	return (
		<div className="cfmb-datetime">
			<Dropdown
				className="cfmb-datetime__dropdown"
				contentClassName="cfmb-datetime__popover"
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						id={ id }
						className="cfmb-datetime__toggle"
						icon="calendar-alt"
						aria-expanded={ isOpen }
						aria-describedby={ describedBy }
						onClick={ onToggle }
					>
						{ shown || <span className="cfmb-datetime__placeholder">{ withTime ? __( 'Choose a date and time', 'custom-fields-meta-box' ) : __( 'Choose a date', 'custom-fields-meta-box' ) }</span> }
					</Button>
				) }
				renderContent={ ( { onClose } ) =>
					withTime ? (
						<DateTimePicker
							currentDate={ value ? value.replace( ' ', 'T' ) : null }
							startOfWeek={ calendar.firstDay ?? 1 }
							isInvalidDate={ ( date ) => outsideLimits( date, calendar ) }
							is12Hour={ /a|A|g|h/.test( formats.time || '' ) }
							onChange={ pick }
						/>
					) : (
						<DatePicker
							currentDate={ value ? `${ value }T00:00:00` : null }
							startOfWeek={ calendar.firstDay ?? 1 }
							isInvalidDate={ ( date ) => outsideLimits( date, calendar ) }
							onChange={ ( picked ) => {
								pick( picked );
								onClose();
							} }
						/>
					)
				}
			/>
			{ value && (
				<Button variant="tertiary" size="compact" onClick={ () => onChange( '' ) }>
					{ __( 'Clear', 'custom-fields-meta-box' ) }
				</Button>
			) }
		</div>
	);
}
