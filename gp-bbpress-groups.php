<?php
/*
Plugin Name: GasPedal bbPress + Groups Integration
Description: Addon to auto-assign Groups read capabilities to new bbPress threads
Author: Peter Wiley
Version: 0.0.1
*/

function gpbbp_new_topic() {

  $forum_capabilities = Groups_Post_access::get_read_post_capabilities( bbp_get_forum_id() );
  foreach( $forum_capabilities as $capability ) {
    Groups_Post_Access::create( array( 'post_id' => bbp_get_topic_id(), 'capability' => $capability ));
    echo $capability;
  }
  unset( $capability );
}
add_action( 'bbp_theme_before_topic_title', 'gpbbp_new_topic' );


// 
// THIS WAS USED TO DEBUG SHTUFF
//
//function gpbbp_before_topic_title_debug() {
//
//  $all_groups = Groups_Group::get_groups();
//  $a_group = $all_groups[1];
//  $group_name = $a_group->name;
//  $group_id = $a_group->id;
//
//  $forum_capabilities = Groups_Post_access::get_read_post_capabilities( bbp_get_forum_id() );
//  $topic_capabilities = Groups_Post_access::get_read_post_capabilities( bbp_get_topic_id() );
//
//  echo "<b>f:</b>&nbsp;";
//  echo bbp_get_forum_id();
//  echo "&nbsp;//&nbsp;<b>t:</b>&nbsp;";
//  echo bbp_get_topic_id();
//  echo "<br><b>fc:</b>&nbsp;";
//  foreach( $forum_capabilities as &$capability ) {
//    echo $capability;
//  }
//  unset( $capability );
//  echo "&nbsp;//&nbsp;<b>tc:</b>&nbsp;";
//  foreach( $topic_capabilities as &$capability ) {
//    echo $capability;
//  }
//  unset( $capability );
//  echo "<br>";
//
//}
//function gpbbp_topic_id() {
//  echo bbp_get_topic_id();
//  echo " ";
//  echo bbp_get_forum_id();
//}
//add_action( 'bbp_theme_before_topic_title', 'gpbbp_before_topic_title_debug' );