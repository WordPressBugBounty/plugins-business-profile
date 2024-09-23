<?php
/**
 * Cell Phone
 */
return array(
    'title'       =>	__( 'Cell Phone Number', 'business-profile' ),
    'description' =>	_x( 'Displays just the (clickable) cell phone number from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-cell-phone"} -->
                        <div class="wp-block-group bpfwp-pattern-cell-phone"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
