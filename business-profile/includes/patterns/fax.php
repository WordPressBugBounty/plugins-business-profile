<?php
/**
 * Fax
 */
return array(
    'title'       =>	__( 'Fax', 'business-profile' ),
    'description' =>	_x( 'Displays just the fax number from your contact card.', 'Block pattern description', 'business-profile' ),
    'categories'  =>	array( 'bpfwp-block-patterns' ),
    'content'     =>	'<!-- wp:group {"className":"bpfwp-pattern-fax"} -->
                        <div class="wp-block-group bpfwp-pattern-fax"><!-- wp:business-profile/contact-card /--></div>
                        <!-- /wp:group -->',
);
