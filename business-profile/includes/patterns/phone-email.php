<?php
/**
 * Phone and Email
 */
return array(
    'title'       =>	__( 'Phone and Email', 'business-profile' ),
    'description' =>	_x( 'Displays the (clickable) phone number and email address from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-phone-email"} -->
                        <div class="wp-block-group bpfwp-pattern-phone-email"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
