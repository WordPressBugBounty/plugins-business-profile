<?php
/**
 * Email
 */
return array(
    'title'       =>	__( 'Email Address', 'business-profile' ),
    'description' =>	_x( 'Displays just the email address from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-email"} -->
                        <div class="wp-block-group bpfwp-pattern-email"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
