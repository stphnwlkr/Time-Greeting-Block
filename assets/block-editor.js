(function() {
    'use strict';
    
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { 
        PanelBody, 
        SelectControl, 
        TextControl,
        Placeholder,
        Disabled
    } = wp.components;
    const {
        InspectorControls,
        useBlockProps,
        BlockControls,
        AlignmentToolbar
    } = wp.blockEditor;
    const { createElement: el } = wp.element;
    const ServerSideRender = wp.serverSideRender;

    // Common timezone options
    const timezoneOptions = [
        { label: __('Use Site Default', 'time-greeting-block'), value: '' },
        { label: __('America/New_York (ET)', 'time-greeting-block'), value: 'America/New_York' },
        { label: __('America/Chicago (CT)', 'time-greeting-block'), value: 'America/Chicago' },
        { label: __('America/Denver (MT)', 'time-greeting-block'), value: 'America/Denver' },
        { label: __('America/Los_Angeles (PT)', 'time-greeting-block'), value: 'America/Los_Angeles' },
        { label: __('Europe/London (GMT)', 'time-greeting-block'), value: 'Europe/London' },
        { label: __('Europe/Paris (CET)', 'time-greeting-block'), value: 'Europe/Paris' },
        { label: __('Asia/Tokyo (JST)', 'time-greeting-block'), value: 'Asia/Tokyo' },
        { label: __('Australia/Sydney (AEST)', 'time-greeting-block'), value: 'Australia/Sydney' },
        { label: __('Custom', 'time-greeting-block'), value: 'custom' }
    ];

    // Common date format options
    const dateFormatOptions = [
        { label: __('January 1, 2024 (F j, Y)', 'time-greeting-block'), value: 'F j, Y' },
        { label: __('Jan 1, 2024 (M j, Y)', 'time-greeting-block'), value: 'M j, Y' },
        { label: __('1/1/2024 (n/j/Y)', 'time-greeting-block'), value: 'n/j/Y' },
        { label: __('01/01/2024 (m/d/Y)', 'time-greeting-block'), value: 'm/d/Y' },
        { label: __('2024-01-01 (Y-m-d)', 'time-greeting-block'), value: 'Y-m-d' },
        { label: __('Monday, January 1, 2024 (l, F j, Y)', 'time-greeting-block'), value: 'l, F j, Y' },
        { label: __('Custom', 'time-greeting-block'), value: 'custom' }
    ];

    // Auto-set timezone abbreviations
    const timezoneAbbreviations = {
        'America/New_York': 'ET',
        'America/Chicago': 'CT',
        'America/Denver': 'MT',
        'America/Los_Angeles': 'PT',
        'Europe/London': 'GMT',
        'Europe/Paris': 'CET',
        'Asia/Tokyo': 'JST',
        'Australia/Sydney': 'AEST'
    };

    // Edit component
    function TimeGreetingEdit({ attributes, setAttributes }) {
        const { display, dateFormat, timezone, tzAbbr, align } = attributes;
        
        const blockProps = useBlockProps({
            className: align ? `has-text-align-${align}` : undefined,
        });

        // Handle timezone change
        function handleTimezoneChange(value) {
            if (value && value !== 'custom' && value !== '') {
                setAttributes({ timezone: value });
                // Auto-set abbreviation if available
                if (timezoneAbbreviations[value]) {
                    setAttributes({ tzAbbr: timezoneAbbreviations[value] });
                }
            } else if (value === '') {
                setAttributes({ timezone: '', tzAbbr: '' });
            }
        }

        // Handle date format change
        function handleDateFormatChange(value) {
            if (value !== 'custom') {
                setAttributes({ dateFormat: value });
            }
        }

        // Check if we need custom fields
        const needsCustomTimezone = timezone && !timezoneOptions.find(tz => tz.value === timezone);
        const needsCustomDateFormat = !dateFormatOptions.find(option => option.value === dateFormat);

        return el('div', null,
            // Block Controls (Toolbar)
            el(BlockControls, null,
                el(AlignmentToolbar, {
                    value: align,
                    onChange: (newAlign) => setAttributes({ align: newAlign })
                })
            ),

            // Inspector Controls (Sidebar)
            el(InspectorControls, null,
                // Display Settings Panel
                el(PanelBody, {
                    title: __('Display Settings', 'time-greeting-block'),
                    initialOpen: true
                },
                    el(SelectControl, {
                        label: __('Display Type', 'time-greeting-block'),
                        value: display,
                        options: [
                            { label: __('Greeting Only', 'time-greeting-block'), value: 'greeting' },
                            { label: __('Date Only', 'time-greeting-block'), value: 'date' },
                            { label: __('Both Greeting and Date', 'time-greeting-block'), value: 'both' }
                        ],
                        onChange: (value) => setAttributes({ display: value }),
                        help: __('Choose what to display in your time greeting block.', 'time-greeting-block')
                    }),

                    // Date format controls (only show if date is being displayed)
                    (display === 'date' || display === 'both') && [
                        el('hr', { key: 'divider1', style: { margin: '16px 0' } }),
                        el(SelectControl, {
                            key: 'dateFormatSelect',
                            label: __('Date Format', 'time-greeting-block'),
                            value: needsCustomDateFormat ? 'custom' : dateFormat,
                            options: dateFormatOptions,
                            onChange: handleDateFormatChange,
                            help: __('Choose how the date should be formatted.', 'time-greeting-block')
                        }),

                        // Custom date format field
                        (needsCustomDateFormat || dateFormatOptions.find(option => option.value === dateFormat && option.value === 'custom')) && el(TextControl, {
                            key: 'customDateFormat',
                            label: __('Custom Date Format', 'time-greeting-block'),
                            value: dateFormat,
                            onChange: (value) => setAttributes({ dateFormat: value }),
                            help: __('Use PHP date format characters. Example: F j, Y', 'time-greeting-block')
                        })
                    ]
                ),

                // Timezone Settings Panel
                el(PanelBody, {
                    title: __('Timezone Settings', 'time-greeting-block'),
                    initialOpen: false
                },
                    el(SelectControl, {
                        label: __('Timezone', 'time-greeting-block'),
                        value: needsCustomTimezone ? 'custom' : timezone,
                        options: timezoneOptions,
                        onChange: handleTimezoneChange,
                        help: __('Leave empty to use the site default timezone.', 'time-greeting-block')
                    }),

                    // Custom timezone field
                    (needsCustomTimezone || timezone === 'custom') && timezone !== '' && el(TextControl, {
                        label: __('Custom Timezone', 'time-greeting-block'),
                        value: timezone,
                        onChange: (value) => setAttributes({ timezone: value }),
                        help: __('Enter a valid PHP timezone identifier (e.g., America/New_York)', 'time-greeting-block')
                    }),

                    el(TextControl, {
                        label: __('Timezone Abbreviation', 'time-greeting-block'),
                        value: tzAbbr,
                        onChange: (value) => setAttributes({ tzAbbr: value }),
                        help: __('Short abbreviation shown with time (e.g., ET, PT, GMT)', 'time-greeting-block')
                    })
                )
            ),

            // Block Content (Preview)
            el('div', blockProps,
                el(Disabled, null,
                    el(ServerSideRender, {
                        block: 'time-greeting-block/time-greeting',
                        attributes: attributes,
                        EmptyResponsePlaceholder: () => el(Placeholder, {
                            icon: 'clock',
                            label: __('Time Greeting', 'time-greeting-block')
                        }, __('Loading preview...', 'time-greeting-block')),
                        ErrorResponsePlaceholder: ({ response }) => el(Placeholder, {
                            icon: 'warning',
                            label: __('Time Greeting Error', 'time-greeting-block')
                        }, __('Error loading preview. Please check your settings.', 'time-greeting-block'))
                    })
                )
            )
        );
    }

    // Register the block
    registerBlockType('time-greeting-block/time-greeting', {
        edit: TimeGreetingEdit,
    });
})();