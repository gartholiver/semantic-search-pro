(function (blocks, blockEditor, components, element, i18n) {
	'use strict';

	var el = element.createElement;
	var TextControl = components.TextControl;
	var RangeControl = components.RangeControl;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;

	blocks.registerBlockType('semantic-search-pro/search', {
		title: __('Semantic Search', 'semantic-search-pro'),
		icon: 'search',
		category: 'widgets',
		attributes: {
			placeholder: {
				type: 'string',
				default: __('Search by meaning...', 'semantic-search-pro'),
			},
			perPage: {
				type: 'number',
				default: 8,
			},
		},
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'ssp-search-editor-preview' });

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __('Search settings', 'semantic-search-pro') },
						el(TextControl, {
							label: __('Placeholder', 'semantic-search-pro'),
							value: attributes.placeholder,
							onChange: function (value) {
								setAttributes({ placeholder: value });
							},
						}),
						el(RangeControl, {
							label: __('Results', 'semantic-search-pro'),
							value: attributes.perPage,
							min: 1,
							max: 20,
							onChange: function (value) {
								setAttributes({ perPage: value });
							},
						})
					)
				),
				el('div', { className: 'ssp-search__form' },
					el('input', {
						className: 'ssp-search__input',
						type: 'search',
						placeholder: attributes.placeholder,
						disabled: true,
					}),
					el('button', {
						className: 'ssp-search__button',
						type: 'button',
						disabled: true,
					}, __('Search', 'semantic-search-pro'))
				)
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
