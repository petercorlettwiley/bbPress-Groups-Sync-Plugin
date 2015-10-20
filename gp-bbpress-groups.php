<?php
/*
Plugin Name: GasPedal bbPress + Groups Integration
Description: Addon to link private bbPress forums to Groups' groups.
Author: Peter Wiley
Version: 0.0.6
*/

/* ------------------------------ *
 * Forum post permission handling *
 * ------------------------------ */
function gpbbp_apply_capabilities_from_forum( $post_id, $forum_id ) {
  $forum_capabilities = Groups_Post_access::get_read_post_capabilities( $forum_id );

  if( !is_array($forum_capabilities) ) { 
    return;
  }

  foreach( $forum_capabilities as $capability ) {
    Groups_Post_Access::create( array( 'post_id' => $post_id, 'capability' => $capability ));
  }
  unset( $capability );
}

function gpbbp_new_post( $post_id, $post, $update ) {
  $TOPIC_POST_TYPE = bbp_get_topic_post_type();
  $REPLY_POST_TYPE = bbp_get_reply_post_type();

  $post_type = get_post_type( $post );
  $forum_id = NULL;

  if( $post_type == $TOPIC_POST_TYPE ) {
    $forum_id = bbp_get_forum_id();
    gpbbp_apply_capabilities_from_forum($post_id, $forum_id);
  }
  if( $post_type == $REPLY_POST_TYPE ) {
    $forum_id = bbp_get_forum_id();
    gpbbp_apply_capabilities_from_forum($post_id, $forum_id);
  }

  gpbbp_new_post_notification( $post_id, $post, $post_type );
}
add_action( 'wp_insert_post', 'gpbbp_new_post', 10, 3 );

/* ------------------------------------ *
 * Forum redirect based on capabilities *
 * ------------------------------------ */
function find_all_groups_for_user( $user_id ) {
  $result = array();

  // Find all possible capabilites
  $all_groups = Groups_Group::get_groups();

  // Iterate, find what capabilites the user has
  foreach( $all_groups as $group ) {
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

/* --------------------- *
 * Forum breadcrumb edit *
 * --------------------- */
function gpbbp_breadcrumb_options() {
  $args['include_home']    = false;
  $args['include_root']    = false;
  if ( bbp_is_single_forum() ) {
    $args['include_current'] = false;
  } else {
    $args['include_current'] = true;
  }

  return $args;
}
add_filter('bbp_before_get_breadcrumb_parse_args', 'gpbbp_breadcrumb_options' );

/* ------------------------
   Forum Username + Company
   ------------------------ */
function gpbbp_get_author_brand( $author_id ) {
  $author_object = get_userdata( $author_id );
  $author_brand = $author_object->brand;
  return $author_brand;
}
function gpbbp_display_user_brand_with_username( $author_name, $reply_id ) {
  $author_id = bbp_get_reply_author_id( $reply_id );
  $author_brand = gpbbp_get_author_brand( $author_id );
  $author_name_brand_display = $author_name . '<br>' . $author_brand;
  return $author_name_brand_display;
}
add_filter( 'bbp_get_reply_author_display_name', 'gpbbp_display_user_brand_with_username', 10, 2 );

function gpbbp_display_user_brand_with_username_topic( $author_name, $topic_id ) {
  $author_id = bbp_get_topic_author_id();
  $author_brand = gpbbp_get_author_brand( $author_id );
  $author_name_brand_display = $author_name . '<br>' . $author_brand;
  return $author_name_brand_display;
}
add_filter( 'bbp_get_topic_author_display_name', 'gpbbp_display_user_brand_with_username_topic', 10, 3 );

/* -------------------
   WYSIWYG TEXT EDITOR
   ------------------- */
function gpbbp_enable_visual_editor( $args = array() ) {
  $args['tinymce'] = true;
  return $args;
}
add_filter( 'bbp_after_get_the_content_parse_args', 'gpbbp_enable_visual_editor' );

/* --------------------------------------------- *
 * Email notification of new post (via Mandrill) *
 * --------------------------------------------- */
function gpbbp_new_post_notification( $post_id, $post, $post_type ) {
  $post_is_reply = ( $post_type == bbp_get_reply_post_type() ) ? true : false;
  $post_topic = $post_is_reply ? get_post( bbp_get_topic_id() )->post_title : $post->post_title;
  $post_author = get_user_by( 'id', $post->post_author );
  $post_forum_title = bbp_get_forum_title( $forum_id );
  $post_info = array(
    'topic' => $post_topic,
    'topic_id' => bbp_get_topic_id(),
    'category' => $post_forum_title,
    'category_id' => $forum_id,
    'is_reply' => $post_is_reply,
    'author' => "$post_author->first_name $post_author->last_name",
    'author_brand' => $post_author->brand,
    'author_username'=> $post_author->display_name,
    'body' => $post->post_content,
    'permalink' => $post_is_reply ? get_permalink( bbp_get_topic_id() ) . "#post-$post_id" : get_permalink( $post_id ),
    //'mutelink' => "mute link"
    // need to add: 'user_slug' => home_url() . '/discussions/user/' . $post->post_author;
  );

  $group = Groups_Group::read_by_name( $post_forum_title );
  $group = new Groups_Group( $group->group_id );

  $mandrill_endpoint = 'https://mandrillapp.com/api/1.0/messages/send-template.json';
  $mandrill_key = 'MANDRILL KEY';
  $mandrill_template = 'new-post-notification';
  $mandrill_merge_vars = array();
  $mandrill_recipients[] = array();

  foreach( $group->users as $group_member ) {
    $mandrill_recipients[] = array(
      'email' => $group_member->user->user_email,
      'name' => $group_member->user->display_name
    );
  }

  // Set up merge vars
  foreach( $post_info as $key => $value ) {
    $mandrill_merge_vars[] = array( 'name' => $key, 'content' => $value );
  }

  // Prepare request
  $mandrill_request = array(
    'key' => $mandrill_key,
    'template_name' => $mandrill_template,
    'template_content' => array(),
    'message' => array(
      'to' => $mandrill_recipients, 
      'global_merge_vars' => $mandrill_merge_vars,
      'merge' => true,
      'merge_language' => 'handlebars'
    )
  );

  // Send request
  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_URL, $mandrill_endpoint );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($mandrill_request) );
  curl_setopt( $ch, CURLOPT_USERAGENT, 'Mandrill-Curl/1.0' );
  $result = curl_exec( $ch );
  curl_close( $ch );
}

/* ------------------- *
 * Load JQuery Scripts *
 * ------------------- */
function gpbbp_scripts_with_jquery() {
  wp_register_script( 'main', plugins_url( '/js/main.js', __FILE__ ), array( 'jquery' ) );
  wp_enqueue_script( 'main' );
}
add_action( 'wp_enqueue_scripts', 'gpbbp_scripts_with_jquery' );