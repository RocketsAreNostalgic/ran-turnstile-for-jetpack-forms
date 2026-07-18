( function () {
	'use strict';

	const widgetSelector = '.ran-turnstile-for-jetpack-forms > .cf-turnstile[id]';
	const submittingSelector = '[data-wp-class--is-submitting].is-submitting';

	function resetWidget( widget ) {
		if ( window.turnstile && widget.id ) {
			window.turnstile.reset( '#' + widget.id );
		}
	}

	document.querySelectorAll( widgetSelector ).forEach( function ( widget ) {
		const form = widget.closest( 'form' );

		if ( ! form ) {
			return;
		}

		let wasSubmitting = Boolean( form.querySelector( submittingSelector ) );
		const observer = new MutationObserver( function () {
			const isSubmitting = Boolean( form.querySelector( submittingSelector ) );

			if ( wasSubmitting && ! isSubmitting && ! form.classList.contains( 'submission-success' ) ) {
				resetWidget( widget );
			}

			wasSubmitting = isSubmitting;
		} );

		observer.observe( form, {
			attributes: true,
			attributeFilter: [ 'class' ],
			subtree: true,
		} );

		form.addEventListener( 'reset', function () {
			window.requestAnimationFrame( function () {
				resetWidget( widget );
			} );
		} );
	} );
} )();
