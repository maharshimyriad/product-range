jQuery(function($) {
	function isDebugEnabled() {
		return !!(window.wcProductRangeFrontend && window.wcProductRangeFrontend.debug && window.console && typeof window.console.log === 'function');
	}

	function debugLog(message, payload) {
		if (!isDebugEnabled()) {
			return;
		}

		window.console.log('[WC Product Range]', message, payload || {});
	}

	function getContainerOrder($container) {
		return $container.children('.wpfFilterWrapper').map(function() {
			var $item = $(this);

			return {
				uniqId: $item.attr('data-uniq-id') || '',
				filterType: $item.attr('data-filter-type') || '',
				classes: $item.attr('class') || ''
			};
		}).get();
	}

	function getExpectedOrder($filter) {
		var raw = $filter.attr('data-range-expected-order') || '';

		if (!raw.length) {
			return [];
		}

		try {
			return JSON.parse(raw);
		} catch (e) {
			debugLog('expectedOrderParseFailed', {
				raw: raw
			});
			return [];
		}
	}

	function compareExpectedVsRendered($filter) {
		var $container = $filter.parent(),
			expectedOrder = getExpectedOrder($filter),
			renderedOrder = getContainerOrder($container),
			filterUniqId = $filter.attr('data-uniq-id') || '',
			expectedIndex = -1,
			renderedIndex = -1;

		$.each(expectedOrder, function(index, item) {
			if (item && item.uniqId === filterUniqId) {
				expectedIndex = index;
				return false;
			}
		});

		$.each(renderedOrder, function(index, item) {
			if (item && item.uniqId === filterUniqId) {
				renderedIndex = index;
				return false;
			}
		});

		debugLog('compareExpectedVsRendered', {
			filterUniqId: filterUniqId,
			expectedIndex: expectedIndex,
			renderedIndex: renderedIndex,
			expectedOrder: expectedOrder,
			renderedOrder: renderedOrder
		});
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
				$container = $filter.parent(),
				$buttonBlock = $container.children('.wpfFilterButtons, .wpfButtonsFilterWrap, .wpfFilterButtonWrap').first(),
				prevUniqId = $filter.attr('data-range-prev-uniq-id') || '',
				nextUniqId = $filter.attr('data-range-next-uniq-id') || '',
				filterUniqId = $filter.attr('data-uniq-id') || '',
				$prevFilter = prevUniqId ? $container.children('.wpfFilterWrapper[data-uniq-id="' + prevUniqId + '"]').first() : $(),
				$nextFilter = nextUniqId ? $container.children('.wpfFilterWrapper[data-uniq-id="' + nextUniqId + '"]').first() : $(),
				beforeOrder = getContainerOrder($container),
				action = 'noop';

			if (!$buttonBlock.length) {
				$buttonBlock = $container.find('.wpfFilterButton, .wpfClearButton').first().parent();
			}

			if ($prevFilter.length && $prevFilter[0] !== $filter[0]) {
				$filter.insertAfter($prevFilter);
				action = 'insertAfterPrev';
			} else if ($nextFilter.length && $nextFilter[0] !== $filter[0]) {
				$filter.insertBefore($nextFilter);
				action = 'insertBeforeNext';
			} else if ($buttonBlock.length) {
				$filter.insertBefore($buttonBlock);
				action = 'insertBeforeButtons';
			}

			debugLog('positionRangeFilters', {
				filterUniqId: filterUniqId,
				orderIndex: $filter.attr('data-range-order-index') || '',
				prevUniqId: prevUniqId,
				nextUniqId: nextUniqId,
				action: action,
				beforeOrder: beforeOrder,
				afterOrder: getContainerOrder($container)
			});

			compareExpectedVsRendered($filter);
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
