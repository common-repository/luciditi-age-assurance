(function ($) {
	'use strict';

	const luciditi_AA = {
		/**
		 * Function to initialize all necessary actions associated with current object.
		 */
		init: function () {
			// Initialize event listeners (controling all the different action, eg: click events)
			luciditi_AA.initEvents();
			// Initialize any actions that need to run on page load
			luciditi_AA.initActions();
		},
		/**
		 * Initilization Functions.
		 */
		initEvents: function () {
			// Handle logo uploader
			if ($('#luciditi_aa_logo_upload').length > 0) {
				$('#luciditi_aa_logo_upload').click(function () {
					wp.media.editor.send.attachment = function (props, attachment) {
						$('#luciditi_aa_logo_display').attr("src", attachment.url);
						$('#luciditi_aa_logo_display').show();
						$('.luciditi_aa_logo').val(attachment.url);
					}
					wp.media.editor.open(this);
					return false;
				});
				$('#luciditi_aa_logo_clear').click(function () {
					$('#luciditi_aa_logo_display').attr("src", "");
					$('#luciditi_aa_logo_display').hide();
					$('.luciditi_aa_logo').val("");
					return false;
				});
			}


			// Handle repeatable access rule fields
			if ($('#luciditi_aa_access_rules').length > 0) {
				let accessRules = $('#luciditi_aa_access_rules');


				// Add Rule button click handler
				$('#luciditi_aa_add_rule').on('click', this.addAccessRule);

				// Reset rules button click handler
				$('#luciditi_aa_reset_rules').on('click', this.resetAccessRules);

				// Country select change handler to show/hide state select
				$(accessRules).on('change', '.luciditi_country_select', this.updateStateAndRegionVisibility);

				// Call the updateCountryAndStateAvailability function whenever a country or state is selected
				$(accessRules).on('change', '.luciditi_country_select, .luciditi_state_select, .luciditi_region_select', this.updateCountryAndStateAndRegionAvailability);

				// Remove Rule button click handler
				$(accessRules).on('click', '.luciditi_aa_remove_rule', function () {
					// Remove the target rule
					$(this).closest('.luciditi_aa_access_rule').remove();
					// Update availbility of countries, regions and states.
					luciditi_AA.updateCountryAndStateAndRegionAvailability();
				});

				// Validate before saving
				$('#luciditi_aa_options_save').on('click', this.validateAccessRules);

				// Trigger change event on page load to ensure correct initial display
				$('.luciditi_country_select').change();
			}
		},
		initActions: function () {
			// Initialize color Picker
			if ($('.luciditi_aa_color').length > 0) {
				$('.luciditi_aa_color').wpColorPicker()
			}

			// Initialize MF Conditional Fields library
			if (typeof (mfConditionalFields) !== 'undefined' && $('[data-conditional-rules]').length > 0) {
				// Initialize for settings page/form
				if ($('#luciditi_aa_options_form').length > 0) {
					mfConditionalFields('#luciditi_aa_options_form', {
						rules: 'inline', // accepts `inline`, `block` and array of objects ( see below for examples ).
						dynamic: true, // If set to `true` the library will handle elements added after the DOM is loaded ( see below for examples ).
						unsetHidden: false, // If set to `true` the library will unset the value of any hidden fields.
						disableHidden: true, // If set to `true`, any hidden fields will be set to `disabled`.
						debug: false, // If set to `true` the library will show hints in the console when things aren't working as expected.
						depth: 3 // This allows you to set how deep should the library go in showing/hiding dependent fields.
					});
				}

				// Initialize for product edit page/form
				if ($('#post').length > 0) {
					mfConditionalFields('form#post', {
						rules: 'inline', // accepts `inline`, `block` and array of objects ( see below for examples ).
						dynamic: true, // If set to `true` the library will handle elements added after the DOM is loaded ( see below for examples ).
						unsetHidden: false, // If set to `true` the library will unset the value of any hidden fields.
						disableHidden: true, // If set to `true`, any hidden fields will be set to `disabled`.
						debug: false, // If set to `true` the library will show hints in the console when things aren't working as expected.
						depth: 3 // This allows you to set how deep should the library go in showing/hiding dependent fields.
					});
				}

				// Initialize for product category edit page
				if ($('#edittag').length > 0) {
					mfConditionalFields('form#edittag', {
						rules: 'inline', // accepts `inline`, `block` and array of objects ( see below for examples ).
						dynamic: true, // If set to `true` the library will handle elements added after the DOM is loaded ( see below for examples ).
						unsetHidden: false, // If set to `true` the library will unset the value of any hidden fields.
						disableHidden: true, // If set to `true`, any hidden fields will be set to `disabled`.
						debug: false, // If set to `true` the library will show hints in the console when things aren't working as expected.
						depth: 3 // This allows you to set how deep should the library go in showing/hiding dependent fields.
					});
				}

			}
		},

		/**
		 * Event callbacks.
		 */
		addAccessRule: function () {

			let accessRules = $('#luciditi_aa_access_rules'),
				removeButtonTemplate = $(this).closest('td').find('template').html(),
				ruleIndex = $(accessRules).find('.luciditi_aa_access_rule').length,
				existingRules = $(accessRules).find('.luciditi_aa_access_rule'),
				newRule = $(existingRules).first().clone(),
				canAddRule = true;

			// Check if there is an empty rule ( to prevent user from adding more until they fill the existing rules )
			$(existingRules).each(function () {
				let selectedCountry = $(this).find('.luciditi_country_select').val();
				if (typeof selectedCountry == "undefined" || selectedCountry == '') {
					canAddRule = false;
					return;
				}
			});

			// Check if the user is allowed to add a rule
			if (canAddRule === false) {
				alert('Please select a country for the existing rule(s) before adding new ones.');
				return;
			}

			// Now prepare the rule to be added.
			luciditi_AA.updateAccessRuleIndexes(newRule, ruleIndex);
			$(newRule).find('.luciditi_state_select').hide();
			$(newRule).find('.luciditi_state_select').val('');
			$(newRule).find('.luciditi_region_select').hide();
			$(newRule).find('.luciditi_region_select').val('');
			$(newRule).find('.luciditi_country_select').val('');
			$(newRule).find('.luciditi_rule_select').val($(newRule).find('.luciditi_rule_select option:first').val());
			// Append the remove button from the template
			$(newRule).append(removeButtonTemplate);
			// Now append the new rule to the list of access rules
			$(newRule).appendTo(accessRules);

			// Update availbility of countries, regions and states.
			// This means disabling previously selected options.
			luciditi_AA.updateCountryAndStateAndRegionAvailability();

			// Show the Reset button if more than one rule exists
			if ($(accessRules).find('.luciditi_aa_access_rule').length > 1) {
				$('#luciditi_aa_reset_rules').show();
			}
		},
		resetAccessRules: function () {
			let accessRules = $('#luciditi_aa_access_rules');
			let firstRule = $(accessRules).find('.luciditi_aa_access_rule').first();

			// Remove all rules except the first
			$(accessRules).find('.luciditi_aa_access_rule:not(:first)').remove();

			// Reset fields of the first rule
			firstRule.find('.luciditi_country_select').val('');
			firstRule.find('.luciditi_state_select').hide().val('');
			firstRule.find('.luciditi_region_select').hide().val('');
			firstRule.find('.luciditi_rule_select').val(firstRule.find('.luciditi_rule_select option:first').val());

			// Hide the Reset button
			$('#luciditi_aa_reset_rules').hide();

			// Trigger change event to run any associated events.
			// This will update fields visibility, availability, and run conditional logic.
			let event = new Event('change', { bubbles: true });
			document.querySelectorAll('.luciditi_country_select').forEach(select => select.dispatchEvent(event));
		},
		validateAccessRules: function (e) {
			let accessRules = $('#luciditi_aa_access_rules'),
				existingRules = $(accessRules).find('.luciditi_aa_access_rule'),
				geolocationEnabled = $('input[name="luciditi_aa_geolocation_enabled"]').is(':checked'),
				canSave = true;

			// If geolocation is not enabled, skip validation
			if (!geolocationEnabled) {
				return;
			}

			// Check if there is an empty rule
			$(existingRules).each(function (index) {
				let selectedCountry = $(this).find('.luciditi_country_select').val();
				if (selectedCountry === '' || typeof selectedCountry === "undefined") {
					canSave = false;
					return false; // Break the loop
				}
			});

			// Validation message for empty rule
			if (!canSave) {
				e.preventDefault();
				alert('Please select a country for the existing access rules before saving.');
				return;
			}
		},
		updateStateAndRegionVisibility: function () {

			let country = $(this).val(),
				stateSelect = $(this).closest('.luciditi_aa_access_rule').find('.luciditi_state_select'),
				regionSelect = $(this).closest('.luciditi_aa_access_rule').find('.luciditi_region_select');
			$(stateSelect).hide();
			$(regionSelect).hide();

			if (country === 'US' || country === 'GB') {
				if (country === 'US') {
					$(stateSelect).show();
				} else {
					$(regionSelect).show();
				}
			}
		},
		updateCountryAndStateAndRegionAvailability: function () {
			let allCountries = {}; // Object to store the availability of countries
			let allStates = {}; // Object to store the availability of states (for US)
			let allRegions = {}; // Object to store the availability of regions (for GB)
			let stateCountUS = 0; // Counter for selected US states
			let regionCountGB = 0; // Counter for selected GB regions

			// Reset all options to enabled before applying new disabling logic
			$('.luciditi_country_select option, .luciditi_state_select option, .luciditi_region_select option').prop('disabled', false);

			// Iterate over each access rule to mark selected countries, states (US), and regions (GB)
			$('#luciditi_aa_access_rules .luciditi_aa_access_rule').each(function () {
				let countrySelect = $(this).find('.luciditi_country_select');
				let selectedCountry = countrySelect.val();
				let stateSelect = $(this).find('.luciditi_state_select');
				let selectedState = stateSelect.val();
				let regionSelect = $(this).find('.luciditi_region_select'); // Assuming this is the class for GB regions
				let selectedRegion = regionSelect.val();

				if (selectedCountry === 'US' && selectedState) {
					allStates[selectedState] = true;
				} else if (selectedCountry === 'GB' && selectedRegion) {
					allRegions[selectedRegion] = true;
				} else if (selectedCountry) {
					allCountries[selectedCountry] = true;
				}
			});

			// Check if all states/regions are selected for US/GB
			let totalStatesUS = $('.luciditi_state_select:first option').length - 1; // Assuming first select has all options
			let totalRegionsGB = $('.luciditi_region_select:first option').length - 1; // Assuming first select has all options
			if (stateCountUS === totalStatesUS) {
				allCountries['US'] = true;
			}
			if (regionCountGB === totalRegionsGB) {
				allCountries['GB'] = true;
			}

			// Update the availability of countries, states, and regions in each select box
			$('#luciditi_aa_access_rules .luciditi_aa_access_rule').each(function () {
				let countrySelect = $(this).find('.luciditi_country_select');
				let selectedCountry = countrySelect.val();
				let stateSelect = $(this).find('.luciditi_state_select');
				let selectedState = stateSelect.val();
				let regionSelect = $(this).find('.luciditi_region_select'); // Assuming this is the class for GB regions
				let selectedRegion = regionSelect.val();


				// Update state options for US
				if (selectedCountry === 'US') {
					let hasSelectedStates = Object.keys(allStates).length > 0;
					stateSelect.find('option').each(function () {
						let stateOption = $(this);
						let stateValue = stateOption.val();
						let isDisabled = (allStates[stateValue]) || (stateValue === '' && hasSelectedStates) || (stateValue === '' && allCountries['US']);

						// Only disable the current state option if its value is not equal to the selected state of this access rules group 
						if (stateValue !== selectedState) {
							stateOption.prop('disabled', isDisabled);
						}
					});
				}

				// Update region options for GB
				if (selectedCountry === 'GB') {
					let hasSelectedRegions = Object.keys(allRegions).length > 0;
					regionSelect.find('option').each(function () {
						let regionOption = $(this);
						let regionValue = regionOption.val();
						let isDisabled = (allRegions[regionValue]) || (regionValue === '' && hasSelectedRegions) || (regionValue === '' && allCountries['GB']);

						// Only disable the current option if its value is not equal to the selected region of this access rules group
						if (regionValue !== selectedRegion) {
							regionOption.prop('disabled', isDisabled);
						}
					});
				}

				// Update country options
				countrySelect.find('option').each(function () {
					let countryOption = $(this);
					let countryValue = countryOption.val();
					let isDisabled = false;

					if (countryValue === 'US') {
						// We'll disable the US country field if `US` is selected somewhere with a state value of an empty string '',
						// or if all states are selected individually. We're are checking if `allCountries['US']` evaluates to true + `selectedState === ''`
						// because the `selectedRegion` value is empty by default, so we need to make sure US is actually selected somewhere else.
						if ((selectedState === '' && allCountries['US']) || Object.keys(allStates).length === totalStatesUS) {
							isDisabled = true;
						}
					} else if (countryValue === 'GB') {
						// We'll disable the GB country field if `GB` is selected somewhere with a region value of an empty string '',
						// or if all regions are selected individually. We're are checking `allCountries['GB']` evaluates to true + `selectedRegion === ''`
						// because the `selectedRegion` value is empty by default, so we need to make sure GB is actually selected somewhere else.
						if ((selectedRegion === '' && allCountries['GB']) || Object.keys(allRegions).length === totalRegionsGB) {
							isDisabled = true;
						}
					} else {
						isDisabled = allCountries[countryValue];
					}

					// Only disable the current option if its value is not equal to the current field (country) selected value 
					if (countryValue !== selectedCountry) {
						countryOption.prop('disabled', isDisabled);
					}

				});
			});
		},
		/**
		 * Helpers
		 */
		updateAccessRuleIndexes: function (rule, newIndex) {
			rule.find('select, input').each(function () {
				var name = $(this).attr('name');
				if (name) {
					name = name.replace(/\[\d+\]/, '[' + newIndex + ']');
					$(this).attr('name', name);
				}
			});
		},
	};
	/**
	 * Only initialize after page load
	 */
	jQuery(window).ready(function ($) {
		luciditi_AA.init();
	});

}(jQuery));
