<?php
/**
 * Map
 */
return array(
    'title'       =>	__( 'Map', 'business-profile' ),
    'description' =>	_x( 'Displays just the map from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-map"} -->
                        <div class="wp-block-group bpfwp-pattern-map"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
