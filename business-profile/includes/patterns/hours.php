<?php
/**
 * Opening Hours
 */
return array(
    'title'       =>	__( 'Opening Hours', 'business-profile' ),
    'description' =>	_x( 'Displays the opening hours from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-hours"} -->
                        <div class="wp-block-group bpfwp-pattern-hours"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
