define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    return {
        init: function() {
            // Like button handler
            $('.like-btn').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var postId = button.data('id');
                
                Ajax.call([{
                    methodname: 'local_recognition_like_post',
                    args: {
                        postid: postId
                    },
                    done: function(response) {
                        if (response.success) {
                            // Update like count
                            var icon = button.find('i');
                            if (response.data.isLiked) {
                                icon.removeClass('fa-heart-o').addClass('fa-heart');
                            } else {
                                icon.removeClass('fa-heart').addClass('fa-heart-o');
                            }
                            button.html(icon[0].outerHTML + ' ' + M.util.get_string('like', 'local_recognition') + 
                                ' (' + response.data.likes + ')');
                        } else {
                            Notification.alert('Error', response.message);
                        }
                    },
                    fail: Notification.exception
                }]);
            });
            
            // Comment form handler
            $('.comment-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var postId = form.data('id');
                var input = form.find('input');
                var content = input.val().trim();
                
                if (!content) {
                    return;
                }
                
                Ajax.call([{
                    methodname: 'local_recognition_add_comment',
                    args: {
                        postid: postId,
                        content: content
                    },
                    done: function(response) {
                        if (response.success) {
                            // Clear input
                            input.val('');
                            
                            // Update comment count
                            var commentBtn = $('.comment-btn[data-id="' + postId + '"]');
                            commentBtn.html('<i class="fa fa-comment-o"></i> ' + 
                                M.util.get_string('comment', 'local_recognition') + 
                                ' (' + response.data.comments + ')');
                            
                            // Add new comment to the list
                            var commentsSection = form.siblings('.comments-section');
                            if (commentsSection.length === 0) {
                                commentsSection = $('<div class="comments-section"></div>');
                                form.before(commentsSection);
                            }
                            commentsSection.prepend(response.data.commentHtml);
                        } else {
                            Notification.alert('Error', response.message);
                        }
                    },
                    fail: Notification.exception
                }]);
            });
        }
    };
});
