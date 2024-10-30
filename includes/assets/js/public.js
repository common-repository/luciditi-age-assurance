(function ($, localizedVars) {
	'use strict';

	const luciditi_AA = {
		started: null, // indicates if the verification started at least once
		type: null, // Indicates the visitor type (first time, returning ..etc)
		sessionId: null, // The visitor session id
		sessionType: null, // The visitor session type
		/**
		 * Function to initialize all necessary actions associated with current object.
		 */
		init: () => {
			// Initialize any actions that need to run on page load
			luciditi_AA.initActions();

			// Initialize event listeners (controling all the different action, eg: click events)
			luciditi_AA.initEvents();
		},
		/**
		 * Initilization Functions.
		 */

		initActions: async () => {
			// Define visitor type
			luciditi_AA.type = $('#luciditi-box').attr('data-type');
			// Set session ID and session type
			luciditi_AA.setSessionId($('#luciditi-box').attr('data-id'));


			// Reset the session if `resetagecheck` query parameter exist in the URL.
			if (new URLSearchParams(window.location.search).has('resetagecheck')) {
				let url = new URL(window.location.href),
					params = new URLSearchParams(url.search);

				// Reset internal session
				await luciditi_AA.ajaxResetSession();
				// Reset external session ( luciditi server )
				await window.LuciditiSdk.deleteCompletedFlows(localizedVars.api_url);

				// Remove `resetagecheck` query param from the URL
				params.delete('resetagecheck');
				url.search = params.toString();
				// Replace the current URL in the history without reloading
				window.history.replaceState({}, '', url);
				// Now reload the page or redirect depending on the enabled mode
				if (localizedVars.mode === 'woocommerce' && localizedVars.shop_url !== '') {
					window.location.href = localizedVars.shop_url;
				} else {
					window.location.reload();
				}

			}


			// Auto-validate the user if they have completed flows.
			if (luciditi_AA.type !== 'disallowed' && luciditi_AA.type !== 'failed-validation') {
				// Check if the user have already completed the age assurance flow using the SDK
				let callbackURL = new URL(localizedVars.callback_url),
					minAge = $('#luciditi-box').attr('data-min-age') || localizedVars.min_age;
				window.LuciditiSdk.completedAgeAssuranceFlow(minAge, localizedVars.api_url, {
					url: callbackURL.searchParams.append('luciditi_aa_precheck', true),
					id: luciditi_AA.sessionId
				}).then((isVerified) => {

					// TODO: verify the user age meets the minimum first since we might have different min_age requirements depending on the restriction type and admin selections.

					if (isVerified) {
						// User already completed age assurance, call the relevant AJAX function to validate their session.
						// a `pending` session should be created from the callback to give them access.
						luciditi_AA.ajaxValidateExternalSession(minAge).then((message) => {
							location.reload();
						}).catch((error) => {
							console.error(error);
							$('#luciditi-loader').removeClass('active'); // Hide loader to proceed with verification process
						});
					} else {
						$('#luciditi-loader').removeClass('active'); // Hide loader to proceed with verification process
					}
				}).catch((error) => {
					console.error(error);
					$('#luciditi-loader').removeClass('active'); // Hide loader to proceed with verification process
				});
			}
		},
		initEvents: () => {
			// Start the verification process when the user clicks "start" button
			$('body').find(".luciditi-init-button").click(luciditi_AA.startVerification);
			// Set the user session to "failed" if they self-declare as underage
			$('body').find(".luciditi-self-declare-default").click(luciditi_AA.setFailedVerification);
		},

		/**
		 * Event callbacks.
		 */
		startVerification: async (e) => {
			e.preventDefault();

			let button = $(e.currentTarget);
			// Show loading spinner on the button
			$(button).addClass('active');
			$(button).attr('disabled', 'disabled');

			try {

				if (luciditi_AA.started !== true) {

					// Authenticate if necessary based the current visitor session ID
					let session = await luciditi_AA.maybeAuthenticate();

					console.log("%cStartVerification => session", "color:red;font-family:system-ui;-webkit-text-stroke: 1px black;font-weight:bold");
					console.log(session);
					// Initialize the SDK using the given session object
					await window.LuciditiSdk.initializeSdk(localizedVars.api_url, session);

					// Verify the session
					let isSessionValid = await window.LuciditiSdk.sessionIsValid();


					console.log("%cStartVerification => isSessionValid", "color:red;font-family:system-ui;-webkit-text-stroke: 1px black;font-weight:bold");
					console.log(isSessionValid);

					if (isSessionValid == false) {
						// If the session is not valid, re-authenticate and initialize the SDK using the new session
						session = await luciditi_AA.maybeAuthenticate(true);
						await window.LuciditiSdk.initializeSdk(localizedVars.api_url, session);
					}

					// Get a startup token
					let startupToken = await luciditi_AA.getStartupToken();

					console.log("%cStartupToken => startupToken", "color:red;font-family:system-ui;-webkit-text-stroke: 1px black;font-weight:bold");
					console.log(startupToken);

					// Set the startup token
					$('meta[name="luciditi-startup-token"]').attr('content', startupToken);

					// Set  `luciditi_AA.started` variable to `true`
					luciditi_AA.started = true;

				}
				// Create a modal with the given HTML and show it
				luciditi_AA.initVerificationModal();

				// Remove the loading spinner on the button
				$(button).removeClass('active');
				$(button).removeAttr('disabled');

			} catch (error) {
				if (typeof error == "object" && 'message' in error) {
					alert(error.message)
				} else {
					alert(error)
				}
				console.error(error);
				// Remove the loading spinner on the button
				$(button).removeClass('active');
				$(button).removeAttr('disabled');
			}

		},
		setFailedVerification: async (e) => {
			e.preventDefault();

			// Enable the loader
			$('#luciditi-loader').addClass('active');

			// Make an AJAX request to create a failed session, or set an existing session to failed for the user
			await luciditi_AA.ajaxSetTmpSessionStateToFailed();

			// Reload the page
			location.reload();
		},
		/**
		 * Helper functions.
		 */
		setSessionId: (sessionId) => {
			// set session id
			luciditi_AA.sessionId = sessionId;
			// set session type
			if (luciditi_AA.sessionId.startsWith('tmp_')) {
				luciditi_AA.sessionType = 'tmp_session';
			} else if (luciditi_AA.sessionId.includes('_')) {
				luciditi_AA.sessionType = 'failed_session';
			} else if (luciditi_AA.sessionId.includes('-')) {
				luciditi_AA.sessionType = 'valid_session';
			}
		},
		maybeAuthenticate: (force = false) => {
			// If there is a temporary session, use it. 
			if (
				(
					luciditi_AA.sessionType == 'tmp_session' ||
					luciditi_AA.sessionType == 'failed_session'
				)
				&& force == false
			) {
				console.log('ajaxGetTempSession trigger');
				return luciditi_AA.ajaxGetTempSession();
			}
			// Otherwise, create a new session by making an auth request.
			else {
				console.log('ajaxAuth trigger');
				return luciditi_AA.ajaxAuth();
			}
		},
		getStartupToken: async () => {

			// Get sign up details
			let signupData = await luciditi_AA.ajaxGetSignUpData(),
				callbackURL = new URL(localizedVars.callback_url);


			console.log("%cGetStartupToken => signupData", "color:green;font-family:system-ui;-webkit-text-stroke: 1px black;font-weight:bold");
			console.log(signupData);

			// Add a custom query arg to the url
			callbackURL.searchParams.append('luciditi_aa', luciditi_AA.sessionId);

			// Now get the sign up code
			let startupToken = await window.LuciditiSdk.addSignupCode({
				callbackUrl: callbackURL.href,
				signupType: window.LuciditiSdk.SignupType, // luciditiSdk.SignupType.ageEstimation is not defined in the compiled version
				stepUpWithData: signupData.stepup_with_id == 'yes' ? true : false,
				stepUpWithIdDocument: signupData.stepup_with_data == 'yes' ? true : false,
				requiredAge: signupData.min_age,
				callerUserName: signupData.caller_username,
			}, true)


			console.log("%cGetStartupToken => startupToken", "color:green;font-family:system-ui;-webkit-text-stroke: 1px black;font-weight:bold");
			console.log(startupToken);

			// Save the code ID to use for extra validation when the callback url is triggered
			let startupCodeId = startupToken.Signup.CodeId || false;
			let minAge = signupData.min_age || false;

			await luciditi_AA.ajaxSaveTmpSessionCodeId(startupCodeId, minAge);

			// Return the startup token
			return new Promise((resolve, reject) => {
				if (startupToken.StartupToken) {
					resolve(startupToken.StartupToken);
				} else {
					reject(localizedVars.startup_token_error);
				}
			});
		},
		initVerificationModal: () => {

			let modal = document.getElementById('luciditi-age-assurance-modal');

			// Track the user journey ( respond to different events )
			window.LuciditiSdk.registerVerificationEventCallbacks(
				// Started
				() => {
					console.log('Verification started');
				},
				// Completed
				() => {
					console.log('Verification completed');
					$('.luciditi-init-button').replaceWith('<div class="loading"><div class="loading__bar"></div ></div>');
					$('.luciditi-self-declare').hide();
					$('.luciditi-modal').hide();
					$('body').removeClass('luciditi-modal-opened');

					location.reload();
				},
				// Failed
				() => {
					console.log('Verification failed');
					$('.luciditi-modal').hide();
					$('body').removeClass('luciditi-modal-opened');

					alert(localizedVars.verification_failed);
				},
				// Aborted
				async () => {
					console.log('Verification aborted');
					// Hide the SDK modal
					$('.luciditi-modal').hide();
					$('body').removeClass('luciditi-modal-opened');

					// Enable the loader
					$('#luciditi-loader').addClass('active');

					// Treat "abort" action as "self-declare".
					if ($('.luciditi-self-declare-default').length > 0) {
						$('.luciditi-self-declare-default').trigger('click');
					} else if ($('.luciditi-self-declare').length > 0) {
						window.location.href = $('.luciditi-self-declare').attr('href');
					} else {
						// Update user session state to 'failed'
						await luciditi_AA.ajaxSetTmpSessionStateToFailed();
						// Reload the page
						location.reload();
					}

				},
				// SDK error
				(error) => {
					console.log('Verification error');
					console.log(error);
				},

			);

			// Start the verification ( initialize the verification UI )
			window.LuciditiSdkUI.startVerification();

			// Show the modal
			document.body.classList.add('luciditi-modal-opened');
			modal.style.display = "block";

			// Add onClick listener to remove the modal when closed ( when user clicks close button )
			let closeBtns = modal.getElementsByClassName('close-reveal-modal');
			for (let i = 0; i < closeBtns.length; i++) {
				closeBtns[i].onclick = function (e) {
					e.currentTarget.closest('.luciditi-modal').style.display = "none";
					document.body.classList.remove('luciditi-modal-opened');
				};
			}
		},
		/**
		 * AJAX
		 */

		ajaxResetSession: () => {
			return new Promise((resolve, reject) => {
				$.ajax({
					data: {
						action: 'luciditi_aa_reset_session',
						session_id: luciditi_AA.sessionId,
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							resolve(result.message);
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
		ajaxValidateExternalSession: (verifiedAge) => {
			return new Promise((resolve, reject) => {
				$.ajax({
					data: {
						action: 'luciditi_aa_validate_external_session',
						session_id: luciditi_AA.sessionId,
						verified_age: verifiedAge,
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							resolve(result.message);
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
		ajaxAuth: () => {
			return new Promise((resolve, reject) => {
				$.ajax({
					data: {
						action: 'luciditi_aa_api_auth',
						session_id: luciditi_AA.sessionId,
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							// Store the new session id, only if the user doesn't have a failed session id already
							if (luciditi_AA.sessionType !== 'session_failed' && 'sessionId' in result.session) {
								luciditi_AA.setSessionId(result.session.sessionId);
							}
							// return the session data
							resolve(result.session);
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
		ajaxGetTempSession: () => {
			return new Promise((resolve, reject) => {
				$.ajax({
					data: {
						action: 'luciditi_aa_get_tmp_session',
						session_id: luciditi_AA.sessionId,
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							// If a database session was created as part of this call, save the new session id
							// Only set the new ID if the user doesn't already have a failed session id
							if (luciditi_AA.sessionType !== 'session_failed' && 'sessionId' in result.session) {
								luciditi_AA.setSessionId(result.session.sessionId);
							}
							// Return the session details
							resolve(result.session);
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
		ajaxGetSignUpData: () => {
			return new Promise((resolve, reject) => {
				$.ajax({
					data: {
						action: 'luciditi_aa_get_signup_data',
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							resolve(result.data);
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
		ajaxSaveTmpSessionCodeId: (codeId, minAge) => {
			return new Promise((resolve, reject) => {
				$.ajax({
					data: {
						action: 'luciditi_aa_save_temp_session_codeid',
						session_id: luciditi_AA.sessionId,
						code_id: codeId,
						min_age: minAge,
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							resolve();
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
		ajaxSetTmpSessionStateToFailed: () => {
			return new Promise((resolve, reject) => {
				// If this is triggered by a user who already failed validation
				// We don't need to do anything since there is already a persistent
				// session for them with the state `failed`.
				if (luciditi_AA.sessionType == 'session_failed') {
					resolve();
				}

				// Otherwise, change the tmp session state to failed so that
				// a persistent session with the state `failed` can be created
				// for the user on the next page load/refresh.
				$.ajax({
					data: {
						action: 'luciditi_aa_set_session_state_failed',
						session_id: luciditi_AA.sessionId,
					},
					type: 'POST',
					url: localizedVars.ajax_url,
					success: function (result) {
						if (result.success) {
							resolve();
						} else {
							reject(result.message);
						}
					},
					error: function (xhr, textStatus, errorThrown) {
						reject(localizedVars.server_error);
					},
				});
			});
		},
	}

	/**
	 * Only initialize after page load and if the luciditi html container exists
	 */
	$(document).ready(() => {
		if ($('#luciditi-age-assurance').length > 0) {
			luciditi_AA.init();
		}
	});

}(jQuery, luciditi_aa_strings));
