( function( wp ) {
const { __ } = wp.i18n;
const { useBlockProps } = wp.blockEditor;

const Edit = () => {
const props = useBlockProps( { className: 'vrsp-listing-editor' } );
return wp.element.createElement(
'div',
props,
wp.element.createElement( 'h3', null, __( 'VR Single Property Listing', 'vr-single-property' ) ),
wp.element.createElement( 'p', null, __( 'Front-end displays gallery, availability calendar, pricing quote, and booking form.', 'vr-single-property' ) )
);
};

wp.blocks.registerBlockType( 'vrsp/listing', {
title: __( 'VR Single Property Listing', 'vr-single-property' ),
icon: 'admin-multisite',
category: 'widgets',
supports: { html: false },
edit: Edit,
save: () => null,
} );
} )( window.wp );
