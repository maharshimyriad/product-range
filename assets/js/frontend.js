jQuery(function($) {
	function rangeDebug(stage, payload) {
		if (!window.wcProductRangeDebug || !window.wcProductRangeDebug.enabled || !window.console || typeof window.console.log !== 'function') {
			return;
		}

		window.console.log('[wc-product-range-fields]', stage, payload || {});
	}

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

		rangeDebug('bootstrap', {
			url: window.location.href,
			hasWpfFrontendPage: !!wpf,
			filterCount: $('.wc-product-range-filter').length
		});

		if (!wpf || wpf._wcProductRangePatched) {
			rangeDebug('patchWpfRangeFilter:skipped', {
				hasWpf: !!wpf,
				alreadyPatched: !!(wpf && wpf._wcProductRangePatched)
			});
			return;
		}

		wpf._wcProductRangePatched = true;
		rangeDebug('patchWpfRangeFilter:attached', {
			hasGetFilterParam: typeof wpf.getFilterParam === 'function',
			hasGetSearchNumberFilterOptions: typeof wpf.getSearchNumberFilterOptions === 'function',
			hasChangeUrlByFilterParamsPro: typeof wpf.changeUrlByFilterParamsPro === 'function'
		});

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

				rangeDebug('getFilterParam:custom', filterData);
				return filterData;
			}

			if (typeof originalGetFilterParam === 'function') {
				try {
					return originalGetFilterParam.call(this, $filter.length ? $filter : filterWrapper);
				} catch (error) {
					rangeDebug('getFilterParam:fallback-error', {
						message: error && error.message ? error.message : String(error),
						hasResolvedWrapper: $filter.length > 0,
						originalInputType: filterWrapper && filterWrapper.nodeType ? filterWrapper.nodeName : typeof filterWrapper
					});
					throw error;
				}
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

			rangeDebug('getSearchNumberFilterOptions:collected', {
				values: values,
				uniqId: $filter.attr('data-uniq-id') || ''
			});

			if ($.isEmptyObject(values)) {
				rangeDebug('getSearchNumberFilterOptions:empty', {});
				return optionsArray;
			}

			optionsArray.backend.value = values;
			optionsArray.frontend.value = values;
			optionsArray.selected.list[0] = values;
			optionsArray.stats = $.map(values, function(item) {
				return item;
			});

			rangeDebug('getSearchNumberFilterOptions:resolved', optionsArray);

			return optionsArray;
		};

		wpf.changeUrlByFilterParamsPro = function(filterData, noWooPage, filterWrapper) {
			rangeDebug('changeUrlByFilterParamsPro:input', {
				filterId: filterData && filterData.id ? filterData.id : '',
				filterName: filterData && filterData.name ? filterData.name : '',
				settings: filterData && filterData.settings ? filterData.settings : {}
			});

			if (filterData.id === 'wpfSearchNumber' && filterData.name === 'wpf_range_value') {
				var value = filterData.settings && filterData.settings.value ? filterData.settings.value : {},
					$wrapper = $(filterWrapper);

				$wrapper.find('.wc-product-range-filter__input').each($.proxy(function(index, input) {
					var type = $(input).data('range-type'),
						paramName = 'wpf_range_value[' + type + ']',
						paramValue = value[type] ? value[type] : '';

					rangeDebug('changeUrlByFilterParamsPro:param', {
						paramName: paramName,
						paramValue: paramValue
					});

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
				$prevFilter = prevUniqId ? $container.children('.wpfFilterWrapper[data-uniq-id="' + prevUniqId + '"]').first() : $(),
				$nextFilter = nextUniqId ? $container.children('.wpfFilterWrapper[data-uniq-id="' + nextUniqId + '"]').first() : $();

			if (!$buttonBlock.length) {
				$buttonBlock = $container.find('.wpfFilterButton, .wpfClearButton').first().parent();
			}

			if ($prevFilter.length && $prevFilter[0] !== $filter[0]) {
				$filter.insertAfter($prevFilter);
			} else if ($nextFilter.length && $nextFilter[0] !== $filter[0]) {
				$filter.insertBefore($nextFilter);
			} else if ($buttonBlock.length) {
				$filter.insertBefore($buttonBlock);
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
				rangeDebug('input', {
					type: $(this).data('range-type') || '',
					value: $.trim($(this).val()),
					hasValue: hasValue
				});
			});

		$(document)
			.off('keydown.wcProductRangeFilter', '.wc-product-range-filter input')
			.on('keydown.wcProductRangeFilter', '.wc-product-range-filter input', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					rangeDebug('enter', {
						type: $(this).data('range-type') || '',
						value: $.trim($(this).val())
					});
					$(this).trigger('change');
				}
			});
	}

	patchWpfRangeFilter();
	bindRangeFilterEvents();
	positionRangeFilters(document);
	$(document).on('wpfAjaxSuccess', function() {
		rangeDebug('wpfAjaxSuccess', {});
		patchWpfRangeFilter();
		positionRangeFilters(document);
	});
});
