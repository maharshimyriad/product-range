jQuery(function($) {
	function resolveFilterWrapper(filterWrapper) {
		var $node;

		if (typeof filterWrapper === 'string') {
			if (filterWrapper.charAt(0) === '.' || filterWrapper.charAt(0) === '#') {
				$node = $(filterWrapper);
			} else {
				$node = $('.wpfFilterWrapper[data-uniq-id="' + filterWrapper + '"]').first();

				if (!$node.length) {
					$node = $('[data-uniq-id="' + filterWrapper + '"]').first();
				}

				if (!$node.length) {
					$node = $('.wpfFilterWrapper.wc-product-range-filter').first();
				}
			}
		} else {
			$node = $(filterWrapper);
		}

		if (!$node.length) {
			return $();
		}

		if ($node.hasClass('wpfFilterWrapper')) {
			return $node.first();
		}

		return $node.closest('.wpfFilterWrapper');
	}

	function patchWpfRangeFilter() {
		var wpf = window.wpfFrontendPage;

		if (!wpf || wpf._wcProductRangePatched) {
			return;
		}

		wpf._wcProductRangePatched = true;

		var originalGetFilterParam = wpf.getFilterParam,
			originalGetSearchNumberFilterOptions = wpf.getSearchNumberFilterOptions,
			originalChangeUrlByFilterParamsPro = wpf.changeUrlByFilterParamsPro;

		wpf.getFilterParam = function(filterWrapper) {
			var $filter = resolveFilterWrapper(filterWrapper);

			if ($filter.length && $filter.attr('data-get-attribute') === 'wpf_range_value') {
				var filterData = {
					id: 'wpfSearchNumber',
					slug: 'wpfSearchNumber',
					name: 'wpf_range_value',
					settings: this.getSearchNumberFilterOptions($filter).backend || {}
				};

				if (!filterData.settings.value) {
					filterData.settings.value = {};
				}

				return filterData;
			}

			if (typeof originalGetFilterParam === 'function') {
				return originalGetFilterParam.call(this, $filter.length ? $filter : filterWrapper);
			}

			return {};
		};

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
			var $filter     = $(this),
				$wrapper    = $filter.closest('.wpfMainWrapper'),
				$buttonTop  = $wrapper.children('.wpfFilterButtonsTop').first(),
				$buttonBot  = $wrapper.children('.wpfFilterButtonsBottom').first(),
				prevUniqId  = $filter.attr('data-range-prev-uniq-id') || '',
				nextUniqId  = $filter.attr('data-range-next-uniq-id') || '',
				$prevFilter = prevUniqId ? $wrapper.children('.wpfFilterWrapper[data-uniq-id="' + prevUniqId + '"]').first() : $(),
				$nextFilter = nextUniqId ? $wrapper.children('.wpfFilterWrapper[data-uniq-id="' + nextUniqId + '"]').first() : $();

			if (!$wrapper.length) {
				return;
			}

			if ($prevFilter.length && $prevFilter[0] !== $filter[0]) {
				$filter.insertAfter($prevFilter);
			} else if ($nextFilter.length && $nextFilter[0] !== $filter[0]) {
				$filter.insertBefore($nextFilter);
			} else if ($buttonTop.length) {
				$filter.insertAfter($buttonTop);
			} else if ($buttonBot.length) {
				$filter.insertBefore($buttonBot);
			} else {
				var $last = $wrapper.children().last();
				if ($last.length && $last[0] !== $filter[0]) {
					$filter.insertBefore($last);
				}
			}
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
