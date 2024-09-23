<?php
/**
 * Whatsapp
 */
return array(
    'title'       =>	__( 'Whatsapp', 'business-profile' ),
    'description' =>	_x( 'Displays just the (clickable) Whatsapp number from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-whatsapp"} -->
                        <div class="wp-block-group bpfwp-pattern-whatsapp"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
