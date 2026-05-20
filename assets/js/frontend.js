jQuery(function($) {
	function findRangeFilterContainer($filter) {
		var $ancestors = $filter.parents();

		for (var i = 0; i < $ancestors.length; i++) {
			var $container = $($ancestors[i]),
				$buttons = $container.find('.wpfFilterButtons, .wpfButtonsFilterWrap, .wpfFilterButtonWrap');

			if ($buttons.length && $buttons.first().find($filter).length === 0) {
				return $container;
			}
		}

		return $filter.parent();
	}

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
			var $filter = $(this),
				orderIndex = parseInt($filter.attr('data-range-order'), 10);

			if (isNaN(orderIndex)) {
				orderIndex = 0;
			}

			var $container = findRangeFilterContainer($filter),
				$buttonBlock = $container.find('.wpfFilterButtons, .wpfButtonsFilterWrap, .wpfFilterButtonWrap').first(),
				$wrappers = $container.find('.wpfFilterWrapper').filter(function() {
					return $(this).closest($container).length;
				}).not($filter);

			if ($buttonBlock.length) {
				$filter.insertBefore($buttonBlock);
			}

			if (!$wrappers.length) {
				return;
			}

			if (orderIndex <= 0) {
				$filter.insertBefore($wrappers.first());
				return;
			}

			if (orderIndex >= $wrappers.length) {
				$filter.insertAfter($wrappers.last());
				if ($buttonBlock.length) {
					$filter.insertBefore($buttonBlock);
				}
				return;
			}

			$filter.insertAfter($wrappers.eq(Math.max(0, orderIndex - 1)));
			if ($buttonBlock.length && $filter.nextAll().filter($buttonBlock).length) {
				return;
			}

			if ($buttonBlock.length) {
				$filter.insertBefore($buttonBlock);
			}
		});
	}

	function observeRangeFilterLayout() {
		if (!window.MutationObserver || window._wcProductRangeLayoutObserver) {
			return;
		}

		window._wcProductRangeLayoutObserver = new MutationObserver(function() {
			positionRangeFilters(document);
		});

		window._wcProductRangeLayoutObserver.observe(document.body, {
			childList: true,
			subtree: true
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
	observeRangeFilterLayout();
	$(document).on('wpfAjaxSuccess', function() {
		patchWpfRangeFilter();
		positionRangeFilters(document);
	});
});
