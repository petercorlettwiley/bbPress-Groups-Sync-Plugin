<?php
/*
Plugin Name: GasPedal bbPress + Groups Integration
Description: Addon to link private bbPress forums to Groups' groups.
Author: Peter Wiley
Version: 0.0.10
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



/* ----------------
   PROFILE REDIRECT
   ---------------- */

function gpbbp_profile_redirect() {
  if( bbp_is_single_user() ) {
    $directory_url = '/directory/user';
    $user_id = bbp_get_user_id();
    $user_profile_url = home_url() . $directory_url . '/' . $user_id;
    wp_redirect( $user_profile_url );
    exit;
  }
}
add_action('template_redirect', 'gpbbp_profile_redirect');



/* -----------------------------
   SHOW/HIDE PROFILE EDIT FIELDS
   ----------------------------- */

// Set url to username
function wppb_userid_to_username() {
  return true; 
}
add_filter( 'wppb_userlisting_get_user_by_id', 'wppb_userid_to_username' );

// Helper function
function gpbbp_get_directory_name_from_url($num) {
  $current_url = $_SERVER['REQUEST_URI'];
  $current_url_exploded = explode('/', $current_url);
  $current_url_page = $current_url_exploded[count($current_url_exploded)-$num];
  return $current_url_page;
}

// Hide/show fields and edit profile link
function gpbbp_profile_edit() {
  $user_url = 'user';
  $directory_url = 'directory';
  $edit_url = 'edit-profile';
  $classes[] = 'self';

  $current_url_page = gpbbp_get_directory_name_from_url(2);
  $current_page_directory = gpbbp_get_directory_name_from_url(3);

  // Edit Profile button shows up on own user's profile
  if ( $current_url_page == get_current_user_id() && $current_page_directory == $user_url ) {
    return $classes;
  }
  // Hide edit profile fields on /directory/edit-profile
  if ( $current_url_page == $edit_url && $current_page_directory == $directory_url ) {
    return $classes;
  }

}
add_action('body_class', 'gpbbp_profile_edit');

// Show 'View My Profile' link
function gpbbp_view_profile_link($content){
  $view_profile_url = '/directory/user/' . get_current_user_id();
  $view_profile_link = '<a href="' . $view_profile_url . '">View My Profile</a>';
  return $view_profile_link;
}
add_filter('wppb_after_form_fields', 'gpbbp_view_profile_link');



/* -------------------
   WYSIWYG TEXT EDITOR
   ------------------- */

function gpbbp_enable_visual_editor( $args = array() ) {
  $args['tinymce'] = true;
  return $args;
}
add_filter( 'bbp_after_get_the_content_parse_args', 'gpbbp_enable_visual_editor' );



/* --------------------------
   NEW TOPIC FORM ON HOMEPAGE
   -------------------------- */

// Utility: Get forum ID from slug
function gpbbp_get_forum_id_from_slug( $slug ) {
  $args=array(
    'name' => $slug,
    'post_type' => 'forum',
    'caller_get_posts'=> 1
  );
  $my_posts = get_posts($args);
  if( $my_posts ) {
    return $my_posts[0]->ID;
  }
}
// Reset permissions to allow form visibility
function gpbbp_access_topic_form( $retval ) {
  $retval = bbp_current_user_can_publish_topics();
  return $retval;
}
add_filter( 'bbp_current_user_can_access_create_topic_form', 'gpbbp_access_topic_form' );

// Select correct forum from dropdown
function gpbbp_select_user_forum() {
  $user_groups = find_all_groups_for_user( get_current_user_id() );
  $group = $user_groups[1];
  $group_slug = preg_replace( '/\s/', '-', $group->name );
  return gpbbp_get_forum_id_from_slug($group_slug);
}
add_filter( 'bbp_get_form_topic_forum', 'gpbbp_select_user_forum' );

// If user belongs to 2 groups (implying they're a member), add an open 'hidden' div tag before the forum dropdown
function gpbbp_topic_form_hide_forum_select_before() {
  $user_groups = find_all_groups_for_user( get_current_user_id() );
  if( count($user_groups) == 2 ) {
    echo "<div class='hidden'>";
  }
}
add_action('bbp_theme_before_topic_form_forum', 'gpbbp_topic_form_hide_forum_select_before');

// After dropdown, close 'hidden' div
function gpbbp_topic_form_hide_forum_select_after() {
  $user_groups = find_all_groups_for_user( get_current_user_id() );
  if( count($user_groups) == 2 ) {
    echo "</div>";
  }
}
add_action('bbp_theme_after_topic_form_forum', 'gpbbp_topic_form_hide_forum_select_after');



/* --------------------------
   REMOVE GRAVATAR CONNECTION
   -------------------------- */

function gpbbp_remove_gravatar ($avatar, $id_or_email, $size, $default) {
  $default = plugins_url() . '/gp-bbpress-groups/images/avatar-default.png';
  return '<img src="' . $default . '" alt="avatar" class="avatar"/>';
}
add_filter('get_avatar', 'gpbbp_remove_gravatar', 1, 5);



/* ------
   SEARCH
   ------ */

/* Include bbPress 'topic' custom post type in WordPress' search results */
function gpbbp_topic_search( $topic_search ) {
  $topic_search['exclude_from_search'] = false;
  return $topic_search;
}
add_filter( 'bbp_register_topic_post_type', 'gpbbp_topic_search' );

/* Include bbPress 'reply' custom post type in WordPress' search results */
function gpbbp_reply_search( $reply_search ) {
  $reply_search['exclude_from_search'] = false;
  return $reply_search;
}
add_filter( 'bbp_register_reply_post_type', 'gpbbp_reply_search' );



/* ---------------------
   COUNCIL LOGO REDIRECT
   --------------------- */

// Logo redirect logic
function gpbbp_council_logo_handle_request() {
  $user_groups = find_all_groups_for_user( get_current_user_id() );

    // If user belongs to two groups (one is always 'Registered'), then the second must be a Council group
    if( count($user_groups) == 2 ) {
      $group = $user_groups[1];
      $logo_name = preg_replace( '/\s/', '', $group->name );
    } else {
      if ( count($user_groups) > 2 && count($user_groups) < 6 ) {
        $logo_name = 'Moderator';
      } else {
        $logo_name = 'Admin';
      }
    }

  $base_url = get_site_url();
  wp_redirect( "$base_url/discourse/council-logo-$logo_name.svg");
  exit();
}

function gpbbp_parse_request(){
  global $wp;

  // Watch for the council logo query
  $param = '__get_council_logo';
  if(isset($wp->query_vars[$param])){
    gpbbp_council_logo_handle_request();
    exit;
  }
}
add_action('parse_request', 'gpbbp_parse_request', 0);

// Just use /index.php?__get_council_logo=1 to get logo
function gpbbp_add_query_vars($vars){
  $param = '__get_council_logo';
  $vars[] = $param;

  return $vars;
}
add_filter('query_vars', 'gpbbp_add_query_vars', 0);



/* --------------------------------------------- *
 * Email notification of new post (via Mandrill) *
 * --------------------------------------------- */

function gpbbp_new_post_notification( $post_id, $post, $post_type ) {
  $post_is_reply = ( $post_type == bbp_get_reply_post_type() ) ? true : false;
  $post_topic = $post_is_reply ? get_post( bbp_get_topic_id() )->post_title : $post->post_title;
  $post_author = get_user_by( 'id', $post->post_author );
  $post_forum_title = bbp_get_forum_title( $forum_id );
  $post_info = array(
    'topic' => htmlspecialchars_decode($post_topic, ENT_QUOTES),
    'topic_id' => bbp_get_topic_id(),
    'category' => $post_forum_title,
    'category_id' => $forum_id,
    'category_slug' => str_replace(' ', '', $post_forum_title),
    'is_reply' => $post_is_reply,
    'author' => "$post_author->first_name $post_author->last_name",
    'author_brand' => $post_author->brand,
    'author_username'=> $post_author->display_name,
    'body' => $post->post_content,
    'permalink' => $post_is_reply ? get_permalink( bbp_get_topic_id() ) . "#post-$post_id" : get_permalink( $post_id ),
    'user_slug' => home_url() . '/directory/user/' . $post->post_author
  );

  $group = Groups_Group::read_by_name( $post_forum_title );
  $group = new Groups_Group( $group->group_id );

  $mandrill_endpoint = 'https://mandrillapp.com/api/1.0/messages/send-template.json';
  $mandrill_key = 'MANDRILL KEY';
  $mandrill_template = 'new-post-notification-backup-mc-version-1';
  $mandrill_merge_vars = array();
  $mandrill_recipients[] = array();

  foreach( $group->users as $group_member ) {
    if ( $group_member->user->ID != $post->post_author ) {
      $mandrill_recipients[] = array(
        'email' => $group_member->user->user_email,
        'name' => $group_member->user->display_name
      );
    }
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
function gpbbp_stylesheet() {
  wp_register_style( 'style', plugins_url( '/css/style.css', __FILE__ ) );
  wp_enqueue_style( 'style' );
}
add_action( 'wp_enqueue_scripts', 'gpbbp_scripts_with_jquery' );
add_action( 'wp_enqueue_scripts', 'gpbbp_stylesheet' );