(function(blocks, element, editor, components, i18n) {
    var el = element.createElement;
    var __ = i18n.__;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = editor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;
    var ServerSideRender = components.ServerSideRender;

    registerBlockType('time-greeting-block/time-greeting', {
        title: __('Time Greeting', 'time-greeting-block'),
        icon: el('svg', { 
            xmlns: "http://www.w3.org/2000/svg", 
            viewBox: "0 0 640 640",
            style: { width: '20px', height: '20px' }
        },
            el('path', { 
                d: "M184 64C197.3 64 208 74.7 208 88L208 128L368 128L368 88C368 74.7 378.7 64 392 64C405.3 64 416 74.7 416 88L416 128L448 128C483.3 128 512 156.7 512 192L512 278C496.7 274.1 480.6 272 464 272C436.7 272 410.7 277.7 387.1 288L112 288L112 480C112 488.8 119.2 496 128 496L274.7 496C277.5 512.8 282.5 528.9 289.5 544L128 544C92.7 544 64 515.3 64 480L64 192C64 156.7 92.7 128 128 128L160 128L160 88C160 74.7 170.7 64 184 64zM184 176L128 176C119.2 176 112 183.2 112 192L112 240L464 240L464 192C464 183.2 456.8 176 448 176L184 176zM320 464C320 384.5 384.5 320 464 320C543.5 320 608 384.5 608 464C608 543.5 543.5 608 464 608C384.5 608 320 543.5 320 464zM464 384C455.2 384 448 391.2 448 400L448 464C448 472.8 455.2 480 464 480L512 480C520.8 480 528 472.8 528 464C528 455.2 520.8 448 512 448L480 448L480 400C480 391.2 472.8 384 464 384z"
            })
        ),
        category: 'widgets',
        description: __('Display time-based greetings and current date.', 'time-greeting-block'),
        keywords: [__('time', 'time-greeting-block'), __('greeting', 'time-greeting-block'), __('date', 'time-greeting-block')],
        
        attributes: {
            display: {
                type: 'string',
                default: 'greeting'
            },
            dateFormat: {
                type: 'string',
                default: 'F j, Y'
            },
            timezone: {
                type: 'string',
                default: ''
            },
            tzAbbr: {
                type: 'string',
                default: ''
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            function onChangeDisplay(newDisplay) {
                setAttributes({ display: newDisplay });
            }

            function onChangeDateFormat(newDateFormat) {
                setAttributes({ dateFormat: newDateFormat });
            }

            function onChangeTimezone(newTimezone) {
                setAttributes({ timezone: newTimezone });
            }

            function onChangeTzAbbr(newTzAbbr) {
                setAttributes({ tzAbbr: newTzAbbr });
            }

            return [
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: __('Display Settings', 'time-greeting-block'),
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: __('Display Type', 'time-greeting-block'),
                            value: attributes.display,
                            options: [
                                { label: __('Greeting Only', 'time-greeting-block'), value: 'greeting' },
                                { label: __('Date Only', 'time-greeting-block'), value: 'date' },
                                { label: __('Both Greeting and Date', 'time-greeting-block'), value: 'both' }
                            ],
                            onChange: onChangeDisplay
                        }),
                        
                        (attributes.display === 'date' || attributes.display === 'both') &&
                        el(TextControl, {
                            label: __('Date Format', 'time-greeting-block'),
                            value: attributes.dateFormat,
                            onChange: onChangeDateFormat,
                            help: __('PHP date format (e.g., F j, Y for "January 1, 2024")', 'time-greeting-block')
                        })
                    ),
                    
                    el(PanelBody, {
                        title: __('Timezone Settings', 'time-greeting-block'),
                        initialOpen: false
                    },
                        el(TextControl, {
                            label: __('Timezone', 'time-greeting-block'),
                            value: attributes.timezone,
                            onChange: onChangeTimezone,
                            help: __('Leave empty to use site default. Example: America/Chicago', 'time-greeting-block')
                        }),
                        
                        el(TextControl, {
                            label: __('Timezone Abbreviation', 'time-greeting-block'),
                            value: attributes.tzAbbr,
                            onChange: onChangeTzAbbr,
                            help: __('Leave empty to use site default. Example: CT, PT, MT', 'time-greeting-block')
                        })
                    )
                ),
                
                el('div', {
                    className: 'time-greeting-block-editor'
                },
                    el(ServerSideRender, {
                        block: 'time-greeting-block/time-greeting',
                        attributes: attributes
                    })
                )
            ];
        },

        save: function() {
            // Server-side rendering, so return null
            return null;
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor || window.wp.editor,
    window.wp.components,
    window.wp.i18n
);