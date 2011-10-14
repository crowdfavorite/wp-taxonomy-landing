<?php

/**
 * @package taxonomy-landing
 *
 * This file is part of Taxonomy Landing for WordPress
 * https://github.com/crowdfavorite/wp-taxonomy-landing
 *
 * Copyright (c) 2009-2011 Crowd Favorite, Ltd. All rights reserved.
 * http://crowdfavorite.com
 *
 * **********************************************************************
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
 * **********************************************************************
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

function cftl_intercept_get_posts(&$query_obj) {
	global $cftl_previous;
	remove_action('pre_get_posts', 'cftl_intercept_get_posts');
	if ($query_obj->is_tax || $query_obj->is_tag || $query_obj->is_category) {
		$qv = $query_obj->query_vars;
		if (!(isset($qv['paged']) && (int) $qv['paged'] < 1)) {
			// Only handle the landing page, not an explicit call to page 1
			return;
		}
		$override_query = array(
			'post_type' => 'cftl-tax-landing',
			'post_status' => 'publish',
			'numberposts' => 1,
			'tax_query' => $query_obj->tax_query->queries
			);
		$landings = get_posts($override_query);
		if (is_array($landings) && !empty($landings)) {
			$found = false;
			foreach ($query_obj->tax_query->queries as $tax_query) {
				if (is_object_in_term($landings[0]->ID, $tax_query['taxonomy'], $tax_query['terms'])) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				return;
			}
			
			$cftl_previous['query'] = $query_obj->query;
			$cftl_previous['query_vars'] = $query_obj->query_vars;
			$cftl_previous['queried_object'] = $query_obj->get_queried_object();
			$query = 'post_type=cftl-tax-landing&p=' . absint($landings[0]->ID);
			$query_obj->parse_query($query);

			add_filter('redirect_canonical', 'cftl_abort_redirect_canonical');
			
			$page_template = get_post_meta($landings[0]->ID, '_wp_page_template', true);
			if (!empty($page_template)) {
				add_filter('template_include', 'cftl_intercept_template_loader');
			}
		}
	}
}

function cftl_abort_redirect_canonical($redirect_url) {
	return false;
}

add_action('pre_get_posts', 'cftl_intercept_get_posts');

function cftl_intercept_template_loader($template) {
	global $post;
	if (isset($post) && is_object($post) && 'cftl-tax-landing' == $post->post_type) {
		$post_template = get_post_meta($post->ID, '_wp_page_template');
		if (empty($post_template)) {
			return $template;
		}
		$template = get_page_template();
		remove_filter('template_include', 'cftl_intercept_template_loader');
		$template = apply_filters( 'template_include', $template);
		add_filter('template_include', 'cftl_intercept_template_loader');
	}
	return $template;
}

add_filter('template_include', 'cftl_intercept_template_loader');
