(function () {
	'use strict';

	function config() {
		return window.sspSearchPro || {
			restUrl: '',
			defaultPerPage: 8,
			labels: {
				searching: 'Searching...',
				noResults: 'No matching results found.',
				error: 'Search is temporarily unavailable.',
			},
		};
	}

	function renderResults(container, payload) {
		var results = container.querySelector('.ssp-search__results');
		var status = container.querySelector('.ssp-search__status');

		results.innerHTML = '';
		status.textContent = '';

		if (!payload.results || payload.results.length === 0) {
			status.textContent = config().labels.noResults;
			return;
		}

		if (payload.source === 'fallback' && payload.message) {
			status.textContent = payload.message;
		}

		payload.results.forEach(function (result) {
			var item = document.createElement('li');
			var link = document.createElement('a');
			var excerpt = document.createElement('p');

			item.className = 'ssp-search__result';
			link.className = 'ssp-search__result-title';
			link.href = result.url || '#';
			link.textContent = result.title || result.url || 'Untitled';
			excerpt.className = 'ssp-search__excerpt';
			excerpt.innerHTML = result.excerpt || '';

			item.appendChild(link);
			if (result.excerpt) {
				item.appendChild(excerpt);
			}
			results.appendChild(item);
		});
	}

	function bindSearch(container) {
		var form = container.querySelector('.ssp-search__form');
		var input = container.querySelector('.ssp-search__input');
		var status = container.querySelector('.ssp-search__status');

		if (!form || !input) {
			return;
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var query = input.value.trim();
			var perPage = container.getAttribute('data-per-page') || config().defaultPerPage;

			if (!query) {
				return;
			}

			status.textContent = config().labels.searching;

			fetch(config().restUrl + '?q=' + encodeURIComponent(query) + '&per_page=' + encodeURIComponent(perPage), {
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
				},
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('Search request failed.');
					}
					return response.json();
				})
				.then(function (payload) {
					renderResults(container, payload);
				})
				.catch(function () {
					status.textContent = config().labels.error;
				});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.ssp-search').forEach(bindSearch);
	});
})();
