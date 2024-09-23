<?php
/**
 * Contact card
 */
return array(
    'title'       =>	__( 'Contact Card', 'business-profile' ),
    'description' =>	_x( 'Adds a contact card', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-contact-card"} -->
                        <div class="wp-block-group bpfwp-pattern-contact-card"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
