<?php
/**
 * Phone
 */
return array(
    'title'       =>	__( 'Phone Number', 'business-profile' ),
    'description' =>	_x( 'Displays just the (clickable) phone number from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-phone"} -->
                        <div class="wp-block-group bpfwp-pattern-phone"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
