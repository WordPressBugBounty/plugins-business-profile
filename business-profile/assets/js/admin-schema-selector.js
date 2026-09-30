jQuery(document).ready(function() {
	jQuery('select[name="schema_target_type"]').on('change', function() {
		if ( jQuery(this).val() == 'post_type' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();

			jQuery.each(schema_option_data.post_types, function(key, post_type) { 
				jQuery('select[name="schema_target_value"]').append(new Option(post_type.label, post_type.name));
			});
		}
		else if ( jQuery(this).val() == 'post' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();

			jQuery.each(schema_option_data.posts, function(key, post) {
				jQuery('select[name="schema_target_value"]').append(new Option(post.post_title, post.ID));
			});
		}
		else if ( jQuery(this).val() == 'page' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();

			jQuery.each(schema_option_data.pages, function(key, page) {
				jQuery('select[name="schema_target_value"]').append(new Option(page.post_title, page.ID));
			});
		}
		else if ( jQuery(this).val() == 'post_category' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();

			jQuery.each(schema_option_data.post_categories, function(key, post_category) {
				jQuery('select[name="schema_target_value"]').append(new Option(post_category.name, post_category.term_id));
			});
		}
		else if ( jQuery(this).val() == 'taxonomy' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();

			jQuery.each(schema_option_data.taxonomies, function(key, taxonomy) {
				jQuery('select[name="schema_target_value"]').append(new Option(taxonomy.label, taxonomy.name));
			});
		}
		else if ( jQuery(this).val() == 'page_template' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();

			jQuery.each(schema_option_data.page_templates, function(name, file) {
				jQuery('select[name="schema_target_value"]').append(new Option(name, file));
			});
		}
		else if ( jQuery(this).val() == 'global' ) {
			jQuery('select[name="schema_target_value"]').find('option').remove();
		}
	});
});
