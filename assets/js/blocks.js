(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor) {
        return;
    }

    var createElement = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var RawHTML = wp.element.RawHTML;
    var wpShortcode = wp.shortcode || null;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var ToggleControl = wp.components.ToggleControl;
    var blockEditorDispatch = wp.data && wp.data.dispatch
        ? wp.data.dispatch(wp.blockEditor.store || 'core/block-editor')
        : null;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (text) { return text; };
    var root = window.MillionDollarScript = window.MillionDollarScript || {};
    root.extensions = root.extensions || {};
    var extension = root.extensions.sponsorBoard = root.extensions.sponsorBoard || {};
    var config = extension.blocks || {};
    var boards = config.boards || [];
    var blockApiVersion = Number(config.blockApiVersion || 2);
    var blockCategory = config.blockCategory || 'million-dollar-script';

    function blockProps(props) {
        return useBlockProps ? useBlockProps(props) : props;
    }

    function shortcodeValue(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function shortcode(attrs) {
        var pairs = [];
        Object.keys(attrs || {}).forEach(function (key) {
            var value = attrs[key];
            if (value === undefined || value === null || value === '') {
                return;
            }
            pairs.push(key + '="' + shortcodeValue(value) + '"');
        });

        return '[mds_sponsorboard' + (pairs.length ? ' ' + pairs.join(' ') : '') + ']';
    }

    function shortcodeMarkup(attrs) {
        var value = shortcode(attrs);

        return RawHTML ? createElement(RawHTML, null, value) : value;
    }

    function shortcodeNamed(attrs, key, fallback) {
        var named = attrs && attrs.named ? attrs.named : {};
        var value = named[key];

        return value === undefined || value === null || value === '' ? fallback : value;
    }

    function shortcodeNumber(attrs, key, fallback) {
        var value = parseInt(shortcodeNamed(attrs, key, fallback), 10);

        return Number.isNaN(value) ? fallback : value;
    }

    function shortcodeBoolean(attrs, key, fallback) {
        var value = String(shortcodeNamed(attrs, key, fallback ? 'true' : 'false')).toLowerCase();

        return ['1', 'true', 'yes', 'y', 'on'].indexOf(value) !== -1;
    }

    function rawShortcode(node) {
        var text = node && node.textContent ? String(node.textContent).trim() : '';
        var next = null;

        if (!text || !wpShortcode || typeof wpShortcode.next !== 'function') {
            return null;
        }

        next = wpShortcode.next('mds_sponsorboard', text);
        if (!next || !next.content || String(next.content).trim() !== text) {
            return null;
        }

        return (next.shortcode && next.shortcode.attrs) || { named: {} };
    }

    function rawShortcodeTransform() {
        return {
            type: 'raw',
            selector: 'p',
            priority: 5,
            isMatch: function (node) {
                return !!rawShortcode(node);
            },
            transform: function (node) {
                var attrs = rawShortcode(node);
                if (!attrs) {
                    return null;
                }

                return wp.blocks.createBlock('mds/sponsorboard', {
                    id: shortcodeNumber(attrs, 'id', 0),
                    showAvailable: shortcodeBoolean(attrs, 'show_available', true),
                    preview: shortcodeBoolean(attrs, 'preview', false)
                });
            }
        };
    }

    function shortcodeAttributes(attributes) {
        return {
            id: Number(attributes.id || 0),
            show_available: attributes.showAvailable ? 'true' : 'false',
            preview: attributes.preview ? 'true' : 'false'
        };
    }

    function findBoard(id) {
        return boards.find(function (board) {
            return Number(board.id) === Number(id || 0);
        }) || null;
    }

    function boardOptions() {
        var options = [{ label: __('First active sponsor board', 'mds-sponsorboard'), value: '0' }];
        boards.forEach(function (board) {
            options.push({
                label: '#' + board.id + ' ' + board.title,
                value: String(board.id)
            });
        });
        return options;
    }

    function boardSelectionValue(id) {
        var idString = String(id || 0);
        if (idString !== '0' && !findBoard(idString)) {
            return '__custom';
        }

        return idString;
    }

    function boardSelectionOptions() {
        return boardOptions().concat([
            { label: __('Custom board ID', 'mds-sponsorboard'), value: '__custom' }
        ]);
    }

    function shouldIgnoreSelectEvent(event) {
        return event.target && event.target.closest && event.target.closest('input, select, textarea, button, a');
    }

    function selectBlock(clientId) {
        if (blockEditorDispatch && blockEditorDispatch.selectBlock && clientId) {
            blockEditorDispatch.selectBlock(clientId);
        }
    }

    function selectBlockOnPointer(clientId) {
        return function (event) {
            if (shouldIgnoreSelectEvent(event)) {
                return;
            }
            selectBlock(clientId);
        };
    }

    function selectBlockOnKey(clientId) {
        return function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            selectBlock(clientId);
        };
    }

    function selectedBoard(attributes) {
        if (attributes.id) {
            return findBoard(attributes.id);
        }

        return boards.find(function (board) {
            return board.status === 'active';
        }) || boards[0] || null;
    }

    function statusLabel(status) {
        var labels = {
            draft: __('Draft', 'mds-sponsorboard'),
            active: __('Active', 'mds-sponsorboard'),
            paused: __('Paused', 'mds-sponsorboard'),
            archived: __('Archived', 'mds-sponsorboard')
        };
        return labels[status] || status || __('Unknown', 'mds-sponsorboard');
    }

    function boardMeta(board, attributes) {
        if (!board) {
            return __('Create a sponsor board before this block can render.', 'mds-sponsorboard');
        }

        return statusLabel(board.status) + ' · ' + board.layout + ' · ' + board.slots + ' ' + __('slots', 'mds-sponsorboard') + ' · ' + (attributes.showAvailable ? __('available visible', 'mds-sponsorboard') : __('committed only', 'mds-sponsorboard'));
    }

    function previewSlots(board) {
        var total = board ? Math.max(1, Math.min(8, Number(board.slots || 1))) : 4;
        var sold = board ? Number(board.sold || 0) + Number(board.reserved || 0) : 0;
        var nodes = [];
        for (var index = 0; index < total; index++) {
            nodes.push(createElement('span', {
                key: index,
                className: index < sold ? 'is-committed' : 'is-available'
            }));
        }
        return nodes;
    }

    wp.blocks.registerBlockType('mds/sponsorboard', {
        apiVersion: blockApiVersion,
        title: __('SponsorBoard', 'mds-sponsorboard'),
        description: __('Display a SponsorBoard from Million Dollar Script.', 'mds-sponsorboard'),
        icon: 'megaphone',
        category: blockCategory,
        keywords: [
            __('MDS', 'mds-sponsorboard'),
            __('Million Dollar Script', 'mds-sponsorboard'),
            __('SponsorBoard', 'mds-sponsorboard'),
            __('sponsors', 'mds-sponsorboard')
        ],
        attributes: {
            id: { type: 'number', default: 0 },
            showAvailable: { type: 'boolean', default: true },
            preview: { type: 'boolean', default: false }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var board = selectedBoard(attributes);
            var boardSelection = boardSelectionValue(attributes.id);

            return createElement(Fragment, null,
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: __('SponsorBoard settings', 'mds-sponsorboard'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Board', 'mds-sponsorboard'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            help: __('Choose a saved sponsor board by numeric ID. Leave this on First active sponsor board to let the block pick automatically, or use Custom board ID only when you already know the ID.', 'mds-sponsorboard'),
                            value: boardSelection,
                            options: boardSelectionOptions(),
                            onChange: function (value) {
                                if (value === '__custom') {
                                    setAttributes({ id: attributes.id ? attributes.id : '' });
                                    return;
                                }
                                setAttributes({ id: parseInt(value, 10) || 0 });
                            }
                        }),
                        boardSelection === '__custom' ? createElement(TextControl, {
                            label: __('Custom board ID', 'mds-sponsorboard'),
                            help: __('Enter the numeric database ID for a sponsor board that is not shown in the list.', 'mds-sponsorboard'),
                            type: 'number',
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: attributes.id ? String(attributes.id) : '',
                            onChange: function (value) {
                                setAttributes({ id: value === '' ? '' : Number(value) });
                            }
                        }) : null,
                        createElement(ToggleControl, {
                            label: __('Show available slots', 'mds-sponsorboard'),
                            __nextHasNoMarginBottom: true,
                            checked: !!attributes.showAvailable,
                            onChange: function (value) {
                                setAttributes({ showAvailable: !!value });
                            }
                        }),
                        createElement(ToggleControl, {
                            label: __('Preview inactive board for admins', 'mds-sponsorboard'),
                            __nextHasNoMarginBottom: true,
                            checked: !!attributes.preview,
                            onChange: function (value) {
                                setAttributes({ preview: !!value });
                            }
                        })
                    )
                ),
                createElement('div', blockProps({
                    className: 'mds-sponsorboard-editor-block',
                    tabIndex: 0,
                    role: 'button',
                    'aria-label': __('Select SponsorBoard block', 'mds-sponsorboard'),
                    onMouseDown: selectBlockOnPointer(props.clientId),
                    onClick: selectBlockOnPointer(props.clientId),
                    onKeyDown: selectBlockOnKey(props.clientId)
                }),
                    createElement('div', { className: 'mds-sponsorboard-editor-preview' }, previewSlots(board)),
                    createElement('div', { className: 'mds-sponsorboard-editor-meta' },
                        createElement('strong', null, board ? board.title : __('SponsorBoard', 'mds-sponsorboard')),
                        createElement('span', null, boardMeta(board, attributes)),
                        createElement('span', null, __('Preview only. The live page renders the selected sponsor board and current slot status.', 'mds-sponsorboard'))
                    )
                )
            );
        },
        save: function (props) {
            return shortcodeMarkup(shortcodeAttributes(props.attributes || {}));
        },
        deprecated: [
            {
                attributes: {
                    id: { type: 'number', default: 0 },
                    showAvailable: { type: 'boolean', default: true },
                    preview: { type: 'boolean', default: false }
                },
                save: function () {
                    return null;
                }
            }
        ],
        transforms: {
            from: [
                {
                    type: 'shortcode',
                    tag: 'mds_sponsorboard',
                    attributes: {
                        id: { type: 'number', shortcode: function (attrs) { return shortcodeNumber(attrs, 'id', 0); } },
                        showAvailable: { type: 'boolean', shortcode: function (attrs) { return shortcodeBoolean(attrs, 'show_available', true); } },
                        preview: { type: 'boolean', shortcode: function (attrs) { return shortcodeBoolean(attrs, 'preview', false); } }
                    }
                },
                rawShortcodeTransform()
            ]
        }
    });
}(window.wp));
