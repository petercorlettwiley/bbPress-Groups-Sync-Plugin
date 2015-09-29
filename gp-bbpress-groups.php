<?php
/*
Plugin Name: GasPedal bbPress + Groups Integration
Description: Addon to link private bbPress forums to Groups' groups.
Author: Peter Wiley
Version: 0.0.3
*/

/*
 * ------------------------------ *
 * Forum post permission handling *
 * ------------------------------ *
*/

function gpbbp_apply_capabilities_from_forum($post_id, $forum_id) {
  $forum_capabilities = Groups_Post_access::get_read_post_capabilities( $forum_id );

  if( !is_array($forum_capabilities) ) { 
    return;
  }

  foreach( $forum_capabilities as $capability ) {
    Groups_Post_Access::create( array( 'post_id' => $post_id, 'capability' => $capability ));
  }
  unset( $capability );
}

function gpbbp_new_post($post_id, $post, $update) {
  $TOPIC_POST_TYPE = bbp_get_topic_post_type();
  $REPLY_POST_TYPE = bbp_get_reply_post_type();

  $post_type = get_post_type($post);
  $forum_id = NULL;

  if($post_type == $TOPIC_POST_TYPE) {
    $forum_id = bbp_get_forum_id();
    gpbbp_apply_capabilities_from_forum($post_id, $forum_id);
  }
  if($post_type == $REPLY_POST_TYPE) {
    $forum_id = bbp_get_forum_id();
    gpbbp_apply_capabilities_from_forum($post_id, $forum_id);
  }
}
add_action('wp_insert_post', 'gpbbp_new_post', 10, 3);

/*
 * ------------------------------------ *
 * Forum redirect based on capabilities *
 * ------------------------------------ *
*/

function find_all_groups_for_user($user_id) {
  $result = array();

  // Find all possible capabilites
  $all_groups = Groups_Group::get_groups();

  // Iterate, find what capabilites the user has
  foreach($all_groups as $group) {
    $OK = Groups_User_Group::read( $user_id, $group->group_id );

    if( $OK ) {
      $result[] = $group;
    }
  }
  return $result;
}

function gpbbp_forum_redirect() {
  $COUNCIL_FORUM_ROOT_PATH = '/discussions/council/';

  if ( bbp_is_forum_archive() ) {
    $user_groups = find_all_groups_for_user( get_current_user_id() );

    // If user belongs to two groups (one is always 'Registered'), then the second must be a Council group
    if( count($user_groups) == 2 ) {
      $group = $user_groups[1];
      // Redirect user to council forum (home url + our forum constant + regex-adjusted name of group)
      $group_path = home_url() . $COUNCIL_FORUM_ROOT_PATH . strtolower( preg_replace( '/\s+Council/', '', $group->name ) );
      wp_redirect( $group_path );
      exit;
    }
  }
}
add_action('template_redirect', 'gpbbp_forum_redirect');