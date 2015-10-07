(function( $ ) {
  "use strict";
  $(function() {
    
    /* -------------------- *
     * Edit breadcrumb text *
     * -------------------- */
    function breadcrumb_forum_edit() {
      var $forumBreadcrumb = $('#bbpress-forums .bbp-breadcrumb-forum');
    
      if ( $forumBreadcrumb ) {
        $forumBreadcrumb.text('Discussions');
      }
    }
    
    $( document ).ready(function() {
      breadcrumb_forum_edit();
    });
    
  });
}(jQuery));