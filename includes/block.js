(function (blocks, element, serverSideRender) {
    var el = element.createElement;

    blocks.registerBlockType('cgr/google-reviews', {
        apiVersion: 2,
        title: 'Google Reviews Pro',
        icon: 'star-filled',
        category: 'widgets',
        description: 'Display your customized dynamic Google Reviews.',
        example: {},
        edit: function (props) {
            return el(
                'div',
                { className: props.className },
                el(serverSideRender, {
                    block: 'cgr/google-reviews',
                })
            );
        },
        save: function () {
            return null; 
        },
    });
})(window.wp.blocks, window.wp.element, window.wp.serverSideRender);