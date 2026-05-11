(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor) {
        return;
    }

    var createElement = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var blockEditorDispatch = wp.data && wp.data.dispatch
        ? wp.data.dispatch(wp.blockEditor.store || 'core/block-editor')
        : null;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (text) { return text; };
    var config = window.MDSSponsorBoardBlocks || {};
    var boards = config.boards || [];
    var blockApiVersion = Number(config.blockApiVersion || 2);

    function blockProps(props) {
        return useBlockProps ? useBlockProps(props) : props;
    }

    function findBoard(id) {
        return boards.find(function (board) {
            return Number(board.id) === Number(id || 0);
        }) || null;
    }

    function boardOptions() {
        var options = [{ label: __('First sponsor board', 'mds-sponsorboard'), value: '0' }];
        boards.forEach(function (board) {
            options.push({
                label: '#' + board.id + ' ' + board.title,
                value: String(board.id)
            });
        });
        return options;
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
        return findBoard(attributes.id) || boards[0] || null;
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
        title: __('Million Dollar Script SponsorBoard', 'mds-sponsorboard'),
        description: __('Display a SponsorBoard from Million Dollar Script.', 'mds-sponsorboard'),
        icon: 'megaphone',
        category: 'widgets',
        attributes: {
            id: { type: 'number', default: 0 },
            showAvailable: { type: 'boolean', default: true },
            preview: { type: 'boolean', default: false }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var board = selectedBoard(attributes);
            var shortcode = '[mds_sponsorboard id="' + (attributes.id || 0) + '" show_available="' + (attributes.showAvailable ? '1' : '0') + '" preview="' + (attributes.preview ? '1' : '0') + '"]';

            return createElement(Fragment, null,
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: __('SponsorBoard settings', 'mds-sponsorboard'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Board', 'mds-sponsorboard'),
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: String(attributes.id || 0),
                            options: boardOptions(),
                            onChange: function (value) {
                                setAttributes({ id: parseInt(value, 10) || 0 });
                            }
                        }),
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
                    'aria-label': __('Select Million Dollar Script SponsorBoard block', 'mds-sponsorboard'),
                    onMouseDown: selectBlockOnPointer(props.clientId),
                    onClick: selectBlockOnPointer(props.clientId),
                    onKeyDown: selectBlockOnKey(props.clientId)
                }),
                    createElement('div', { className: 'mds-sponsorboard-editor-preview' }, previewSlots(board)),
                    createElement('div', { className: 'mds-sponsorboard-editor-meta' },
                        createElement('strong', null, board ? board.title : __('SponsorBoard', 'mds-sponsorboard')),
                        createElement('span', null, boardMeta(board, attributes)),
                        createElement('code', null, shortcode)
                    )
                )
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp));
