import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { SnackbarList } from '@wordpress/components';
import { store as noticesStore } from '@wordpress/notices';
import config from '../config';
import GroupList from './GroupList';
import GroupEditor from './GroupEditor';
import ContentList from './content/ContentList';
import ContentEditor from './content/ContentEditor';
import SyncScreen from './sync/SyncScreen';
import SwitchScreen from './switch/SwitchScreen';

// Field Groups keep their ?group= links from earlier versions; other views use ?item=.
const PARAM = config.view === 'groups' ? 'group' : 'item';

const readParam = () =>
	new URL( window.location.href ).searchParams.get( PARAM ) || '';

function Snackbars() {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( notice ) => notice.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<SnackbarList
			className="cfmb-snackbars"
			notices={ notices }
			onRemove={ removeNotice }
		/>
	);
}

function View( { item, session, navigate } ) {
	if ( config.view === 'sync' ) {
		return <SyncScreen key={ session } />;
	}

	if ( config.view === 'switch' ) {
		return <SwitchScreen key={ session } />;
	}

	if ( config.contentKinds[ config.view ] ) {
		const kind = config.contentKinds[ config.view ];
		return item ? (
			<ContentEditor
				key={ session }
				kind={ kind }
				itemKey={ item }
				navigate={ navigate }
			/>
		) : (
			<ContentList key={ session } kind={ kind } navigate={ navigate } />
		);
	}

	return item ? (
		<GroupEditor key={ session } groupKey={ item } navigate={ navigate } />
	) : (
		<GroupList key={ session } navigate={ navigate } />
	);
}

export default function App() {
	const [ item, setItem ] = useState( readParam );
	// Changes only when the user moves between screens, so saving a new
	// item (which swaps "new" for its key in the URL) keeps the editor state.
	const [ session, setSession ] = useState( 0 );

	useEffect( () => {
		const onPopState = () => {
			setItem( readParam() );
			setSession( ( current ) => current + 1 );
		};
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [] );

	const navigate = ( next, { replace = false, keepState = false } = {} ) => {
		const url = new URL( window.location.href );
		if ( next ) {
			url.searchParams.set( PARAM, next );
		} else {
			url.searchParams.delete( PARAM );
			url.searchParams.delete( 'location' );
		}
		window.history[ replace ? 'replaceState' : 'pushState' ]( {}, '', url );
		setItem( next || '' );
		if ( ! keepState ) {
			setSession( ( current ) => current + 1 );
			window.scrollTo( 0, 0 );
		}
	};

	return (
		<>
			<View item={ item } session={ session } navigate={ navigate } />
			<Snackbars />
		</>
	);
}
