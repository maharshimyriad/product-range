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
});
