(function(wp){
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { PanelBody, ToggleControl, SelectControl, TextControl, Spinner } = wp.components;
    const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
    const ServerSideRender = wp.serverSideRender;
    const apiFetch = wp.apiFetch;
    const { useState, useEffect, useMemo } = wp.element;

    function usePresets(query) {
        const [items, setItems] = useState([]);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState(null);

        useEffect(() => {
            let active = true;
            async function run(){
                try {
                    setLoading(true);
                    setError(null);
                    // Query gbp_block_preset posts via REST
                    const params = new URLSearchParams();
                    params.set('per_page', '50');
                    if (query && query.search) params.set('search', query.search);
                    if (query && query.categoryId) params.set('gbp_block_category', query.categoryId);
                    if (query && query.tagId) params.set('gbp_block_tag', query.tagId);

                    const posts = await apiFetch({ path: '/wp/v2/gbp_block_preset?' + params.toString() });
                    if (!active) return;
                    setItems(posts || []);
                } catch (e) {
                    if (!active) return;
                    setError(e);
                } finally {
                    if (!active) return;
                    setLoading(false);
                }
            }
            run();
            return () => { active = false; };
        }, [query && query.search, query && query.categoryId, query && query.tagId]);

        return { items, loading, error };
    }

    function useTerms(taxonomy) {
        const [terms, setTerms] = useState([]);
        const [loading, setLoading] = useState(false);
        useEffect(() => {
            let active = true;
            async function run(){
                try {
                    setLoading(true);
                    const items = await apiFetch({ path: '/wp/v2/' + taxonomy + '?per_page=100&orderby=name&order=asc' });
                    if (!active) return;
                    setTerms(items || []);
                } catch (e) {
                    if (!active) return;
                    setTerms([]);
                } finally {
                    if (!active) return;
                    setLoading(false);
                }
            }
            run();
            return () => { active = false; };
        }, [taxonomy]);
        return { terms, loading };
    }

    registerBlockType('gbp/block-preset', {
        title: __('Block Preset', 'gutenberg-blocks-presets'),
        icon: 'block-default',
        category: 'widgets',
        attributes: {
            presetId: { type: 'number' },
            presetTitle: { type: 'string', default: '' },
            showTitle: { type: 'boolean', default: false },
            className: { type: 'string', default: '' },
            align: { type: 'string' },
            categoryId: { type: 'number' },
            tagId: { type: 'number' },
            search: { type: 'string', default: '' },
        },
        supports: {
            align: [ 'wide', 'full' ],
            customClassName: true,
            anchor: true,
        },
        edit: (props) => {
            const { attributes, setAttributes } = props;
            const { presetId, presetTitle, showTitle, className, categoryId, tagId, search } = attributes;

            const query = useMemo(() => ({ categoryId, tagId, search }), [categoryId, tagId, search]);
            const { items, loading } = usePresets(query);
            const { terms: categories } = useTerms('gbp_block_category');
            const { terms: tags } = useTerms('gbp_block_tag');

            const blockProps = useBlockProps();

            const presetOptions = useMemo(() => {
                const options = [ { label: __('Select a preset…', 'gutenberg-blocks-presets'), value: 0 } ];
                for (const item of items) {
                    options.push({ label: item.title && item.title.rendered ? item.title.rendered.replace(/<[^>]*>/g, '') : __('(no title)', 'gutenberg-blocks-presets'), value: item.id });
                }
                return options;
            }, [items]);

            return (
                wp.element.createElement('div', blockProps,
                    wp.element.createElement(InspectorControls, null,
                        wp.element.createElement(PanelBody, { title: __('Preset', 'gutenberg-blocks-presets'), initialOpen: true },
                            wp.element.createElement(TextControl, {
                                label: __('Search', 'gutenberg-blocks-presets'),
                                value: search || '',
                                onChange: (v) => setAttributes({ search: v })
                            }),
                            wp.element.createElement(SelectControl, {
                                label: __('Filter by Category', 'gutenberg-blocks-presets'),
                                value: categoryId || 0,
                                options: [{ label: __('All categories', 'gutenberg-blocks-presets'), value: 0 }].concat((categories || []).map(t => ({ label: t.name, value: t.id }))),
                                onChange: (val) => setAttributes({ categoryId: parseInt(val, 10) || 0 })
                            }),
                            wp.element.createElement(SelectControl, {
                                label: __('Filter by Tag', 'gutenberg-blocks-presets'),
                                value: tagId || 0,
                                options: [{ label: __('All tags', 'gutenberg-blocks-presets'), value: 0 }].concat((tags || []).map(t => ({ label: t.name, value: t.id }))),
                                onChange: (val) => setAttributes({ tagId: parseInt(val, 10) || 0 })
                            }),
                            wp.element.createElement(SelectControl, {
                                label: __('Select Preset', 'gutenberg-blocks-presets'),
                                value: presetId || 0,
                                options: presetOptions,
                                onChange: (val) => {
                                    const id = parseInt(val, 10) || 0;
                                    const found = items.find(p => p.id === id);
                                    setAttributes({ presetId: id, presetTitle: found ? (found.title && found.title.rendered ? found.title.rendered.replace(/<[^>]*>/g, '') : '') : '' });
                                }
                            }),
                            loading ? wp.element.createElement(Spinner, null) : null
                        ),
                        wp.element.createElement(PanelBody, { title: __('Options', 'gutenberg-blocks-presets'), initialOpen: false },
                            wp.element.createElement(ToggleControl, {
                                label: __('Show title', 'gutenberg-blocks-presets'),
                                checked: !!showTitle,
                                onChange: (v) => setAttributes({ showTitle: !!v })
                            }),
                            wp.element.createElement(TextControl, {
                                label: __('Additional CSS class(es)', 'gutenberg-blocks-presets'),
                                value: className || '',
                                onChange: (v) => setAttributes({ className: v })
                            })
                        )
                    ),
                    presetId ? wp.element.createElement(ServerSideRender, {
                        block: 'gbp/block-preset',
                        attributes: attributes
                    }) : wp.element.createElement('div', { className: 'gbp-block-placeholder' }, __('Select a Block Preset from the sidebar.', 'gutenberg-blocks-presets'))
                )
            );
        },
        save: () => null, // dynamic
    });
})(window.wp);


