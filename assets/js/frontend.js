jQuery(function($) {
	function patchWpfRangeFilter() {
		var wpf = window.wpfFrontendPage;

		if (!wpf || wpf._wcProductRangePatched) {
			return;
		}

		wpf._wcProductRangePatched = true;

		var originalGetSearchNumberFilterOptions = wpf.getSearchNumberFilterOptions,
			originalChangeUrlByFilterParamsPro = wpf.changeUrlByFilterParamsPro;

		wpf.getSearchNumberFilterOptions = function($filter) {
			if ($filter.attr('data-get-attribute') !== 'wpf_range_value') {
				if (typeof originalGetSearchNumberFilterOptions === 'function') {
					return originalGetSearchNumberFilterOptions.call(this, $filter);
				}

				return {
					backend: {},
					frontend: {},
					selected: { is_one: true, list: [] },
					stats: []
				};
			}

			var values = {},
				optionsArray = {
					backend: {},
					frontend: {},
					selected: { is_one: true, list: [] },
					stats: []
				};

			$filter.find('.wc-product-range-filter__input').each(function() {
				var value = $.trim($(this).val()),
					type = $(this).data('range-type');

				if (!value.length || !type) {
					return;
				}

				values[type] = value;
			});

			if ($.isEmptyObject(values)) {
				return optionsArray;
			}

			optionsArray.backend.value = values;
			optionsArray.frontend.value = values;
			optionsArray.selected.list[0] = values;
			optionsArray.stats = $.map(values, function(item) {
				return item;
			});

			return optionsArray;
		};

		wpf.changeUrlByFilterParamsPro = function(filterData, noWooPage, filterWrapper) {
			if (filterData.id === 'wpfSearchNumber' && filterData.name === 'wpf_range_value') {
				var value = filterData.settings && filterData.settings.value ? filterData.settings.value : {},
					$wrapper = $(filterWrapper);

				$wrapper.find('.wc-product-range-filter__input').each($.proxy(function(index, input) {
					var type = $(input).data('range-type'),
						paramName = 'wpf_range_value[' + type + ']',
						paramValue = value[type] ? value[type] : '';

					this.QStringWork(paramName, paramValue, noWooPage, filterWrapper, paramValue ? 'change' : 'remove');
				}, this));

				return;
			}

			if (typeof originalChangeUrlByFilterParamsPro === 'function') {
				originalChangeUrlByFilterParamsPro.call(this, filterData, noWooPage, filterWrapper);
			}
		};
	}

	function positionRangeFilters(context) {
		$(context).find('.wc-product-range-filter').each(function() {
			var $container = $(this).parent(),
				$rangeFilters = $container.children('.wc-product-range-filter').sort(function(a, b) {
					return (parseInt($(a).attr('data-range-order'), 10) || 0) - (parseInt($(b).attr('data-range-order'), 10) || 0);
				}),
				$buttonBlock = $container.children('.wpfFilterButtons, .wpfButtonsFilterWrap, .wpfFilterButtonWrap').first();

			$rangeFilters.each(function() {
				var $filter = $(this),
					targetIndex = parseInt($filter.attr('data-range-order'), 10) || 0,
					$otherWrappers = $container.children('.wpfFilterWrapper').not($filter);

				if (!$otherWrappers.length || targetIndex <= 0) {
					$filter.prependTo($container);
					return;
				}

				if (targetIndex >= $otherWrappers.length) {
					if ($buttonBlock.length) {
						$filter.insertBefore($buttonBlock);
					} else {
						$filter.appendTo($container);
					}
					return;
				}

				$filter.insertBefore($otherWrappers.eq(targetIndex));
			});
		});
	}

	function bindRangeFilterEvents() {
		$(document)
			.off('input.wcProductRangeFilter', '.wc-product-range-filter input')
			.on('input.wcProductRangeFilter', '.wc-product-range-filter input', function() {
				var $filter = $(this).closest('.wpfFilterWrapper'),
					hasValue = false;

				$filter.find('.wc-product-range-filter__input').each(function() {
					if ($.trim($(this).val()).length) {
						hasValue = true;
						return false;
					}
				});

				$filter.toggleClass('wpfNotActive', !hasValue);
			});

		$(document)
			.off('keydown.wcProductRangeFilter', '.wc-product-range-filter input')
			.on('keydown.wcProductRangeFilter', '.wc-product-range-filter input', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					$(this).trigger('change');
				}
			});
	}

	patchWpfRangeFilter();
	bindRangeFilterEvents();
	positionRangeFilters(document);
	$(document).on('wpfAjaxSuccess', function() {
		patchWpfRangeFilter();
		positionRangeFilters(document);
	});
});
