<?php
/*
Plugin Name: GasPedal bbPress + Groups Integration
Description: Addon to auto-assign Groups read capabilities to new bbPress threads
Author: Peter Wiley
Version: 0.0.2
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