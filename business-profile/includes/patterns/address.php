<?php
/**
 * Address
 */
return array(
    'title'       =>	__( 'Address', 'business-profile' ),
    'description' =>	_x( 'Displays the address from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-address"} -->
                        <div class="wp-block-group bpfwp-pattern-address"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
