jQuery(function($) {
	function toggleRangeFields($scope) {
		var prefix = wcProductRangeFields.enabledPrefix;
		var $checkbox = $scope.find('input[type="checkbox"][id^="' + prefix + '"]');
		var $fields = $scope.find('.range-fields-group');

		if (!$checkbox.length || !$fields.length) {
			return;
		}

		$fields.toggle($checkbox.is(':checked'));
	}

	function initRangeFields(context) {
		$(context).find('.options_group, .woocommerce_variation').each(function() {
			toggleRangeFields($(this));
		});
	}

	initRangeFields(document);

	$(document).on('change', 'input[type="checkbox"][id^="' + wcProductRangeFields.enabledPrefix + '"]', function() {
		toggleRangeFields($(this).closest('.options_group, .woocommerce_variation'));
	});

	$(document).on('woocommerce_variations_loaded woocommerce_variations_added', function() {
		initRangeFields(document);
	});

	function isFilterEditorReady() {
		return !!($('#wpfFiltersEditForm').length && $('#wpfAddFilterButton').length && window.wpfAdminPage);
	}

	function getExistingAttributeFilters() {
		var existingAttributes = {};

		$('.wpfFiltersBlock .wpfFilter[data-filter="wpfAttribute"] select[name="f_list"]').each(function() {
			var value = $(this).val();
			if (value && value !== '0' && value !== 'custom_meta_field_check') {
				existingAttributes[value] = true;
			}
		});

		return existingAttributes;
	}

	function syncAttributesPickerDialog() {
		var existingAttributes = getExistingAttributeFilters(),
			$dialog = $('#wpfAttributesPickerDialog');

		$dialog.find('input[type="checkbox"]').each(function() {
			var $checkbox = $(this),
				value = $checkbox.val(),
				$item = $checkbox.closest('.wpfAttributesPickerItem'),
				used = !!existingAttributes[value];

			$checkbox.prop('disabled', used).prop('checked', false);
			$item.toggleClass('wpfDisabled', used);
		});
	}

	function positionAttributesPickerDialog() {
		var $dialog = $('#wpfAttributesPickerDialog');
		if (!$dialog.length || !$dialog.dialog('isOpen')) {
			return;
		}

		var maxHeight = Math.min($(window).height() - 40, 640);
		$dialog.dialog('option', 'maxHeight', maxHeight);
		$dialog.dialog('option', 'position', { my: 'center', at: 'center', of: window });
		$dialog.closest('.ui-dialog').css('position', 'fixed');
	}

	function addSelectedAttributesFromPopup() {
		var existingAttributes = getExistingAttributeFilters(),
			$dialog = $('#wpfAttributesPickerDialog'),
			added = 0;

		$dialog.find('input[type="checkbox"]:checked:enabled').each(function() {
			var value = $(this).val();

			if (!value || existingAttributes[value]) {
				return true;
			}

			window.wpfAdminPage.wpfAddFilter('wpfAttribute', false, { f_list: value, f_enable: 1, f_show_count: 1 }, { skipAttributeTermsLoad: true });
			existingAttributes[value] = true;
			added++;
		});

		if (!added) {
			return;
		}

		$dialog.find('input[type="checkbox"]').prop('checked', false);
		$('.wpfFiltersBlock').removeClass('wpfHidden');
		$('#wpfChooseFilters').trigger('change');
		window.wpfAdminPage.saveFilters();
		window.wpfAdminPage.getPreviewAjax();
	}

	function initAttributePicker() {
		var $button = $('#wpfAddAllAttributesButton'),
			$target = $('#wpfAddFilterButton'),
			$dialog = $('#wpfAttributesPickerDialog');

		if (!isFilterEditorReady() || !$button.length || !$target.length || !$dialog.length) {
			return;
		}

		if (!$button.parent().is('#wpfChooseFiltersBlock')) {
			$button.insertAfter($target).show();
		}

		if (!$dialog.data('wpf-initialized')) {
			$dialog.data('wpf-initialized', 1).dialog({
				autoOpen: false,
				modal: true,
				width: 520,
				maxHeight: Math.min($(window).height() - 40, 640),
				dialogClass: 'wpfAttributesPickerDialogShell',
				position: { my: 'center', at: 'center', of: window },
				create: function() {
					$(this).closest('.ui-dialog').addClass('woobewoo-plugin');
				},
				buttons: {
					'Add selected': function() {
						addSelectedAttributesFromPopup();
						$(this).dialog('close');
					},
					Cancel: function() {
						$(this).dialog('close');
					}
				},
				open: function() {
					syncAttributesPickerDialog();
					positionAttributesPickerDialog();
					$(window)
						.off('resize.wpfAttributesPicker scroll.wpfAttributesPicker')
						.on('resize.wpfAttributesPicker scroll.wpfAttributesPicker', function() {
							positionAttributesPickerDialog();
						});
				},
				close: function() {
					$(window).off('resize.wpfAttributesPicker scroll.wpfAttributesPicker');
				}
			});
		}

		$button.off('click.wpfProductRange').on('click.wpfProductRange', function(e) {
			e.preventDefault();
			syncAttributesPickerDialog();
			$dialog.dialog('open');
		});

		$dialog.off('click.wpfProductRange', '[data-action="select-all"]').on('click.wpfProductRange', '[data-action="select-all"]', function(e) {
			e.preventDefault();
			$dialog.find('input[type="checkbox"]:enabled').prop('checked', true);
		});

		$dialog.off('click.wpfProductRange', '[data-action="clear-all"]').on('click.wpfProductRange', '[data-action="clear-all"]', function(e) {
			e.preventDefault();
			$dialog.find('input[type="checkbox"]').prop('checked', false);
		});
	}

	initAttributePicker();
	$(document).on('click', '.wpfFiltersBlock .wpfDelete', function() {
		setTimeout(initAttributePicker, 0);
	});
});
