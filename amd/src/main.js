/**
 * Recognition wall JavaScript.
 *
 * @module     local_recognition/main
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery", "core/ajax", "core/notification"], function (
  $,
  Ajax,
  Notification
) {
  return {
    init: function () {
      // File upload preview
      $(".file-input").on("change", function (e) {
        var file = e.target.files[0];
        if (!file) return;

        // Check file size
        var maxSize = $(this).data("max-size");
        if (file.size > maxSize) {
          Notification.addNotification({
            message: "File size must be less than 5MB",
            type: "error",
          });
          $(this).val("");
          return;
        }

        // Show preview
        var reader = new FileReader();
        reader.onload = function (e) {
          var $preview = $(".post-attachments");
          $preview
            .html(
              `
            <div class="attachment-preview">
              <img src="${e.target.result}" alt="Preview">
              <div class="attachment-remove">&times;</div>
            </div>
          `
            )
            .show();
        };
        reader.readAsDataURL(file);
      });

      // Remove attachment preview
      $(document).on("click", ".attachment-remove", function () {
        $(".file-input").val("");
        $(".post-attachments").empty().hide();
      });

      // Like button click handler
      $(".recognition-like-btn").on("click", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "like",
              recordid: recordId,
              type: "like",
            },
            done: function (response) {
              if (response.success) {
                var likesCount = response.data.likes;
                var isLiked = response.data.isLiked;

                btn.find(".likes-count").text(likesCount);
                btn.toggleClass("liked", isLiked);
              } else {
                console.error("Like error:", response.message);
                Notification.addNotification({
                  message: response.message || "Error liking post",
                  type: "error",
                });
              }
            },
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Thanks button click handler
      $(".recognition-thanks-btn").on("click", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "thanks",
              recordid: recordId,
              type: "thanks",
            },
            done: function (response) {
              if (response.success) {
                var thanksCount = response.data.thanks;
                var isThanked = response.data.isThanked;

                btn.find(".thanks-count").text(thanksCount);
                btn.toggleClass("thanked", isThanked);
              } else {
                console.error("Thanks error:", response.message);
                Notification.addNotification({
                  message: response.message || "Error adding thanks",
                  type: "error",
                });
              }
            },
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Celebration button click handler
      $(".recognition-celebration-btn").on("click", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "celebration",
              recordid: recordId,
              type: "celebration",
            },
            done: function (response) {
              if (response.success) {
                var celebrationCount = response.data.celebration;
                var isCelebrated = response.data.isCelebrated;

                btn.find(".celebration-count").text(celebrationCount);
                btn.toggleClass("celebrated", isCelebrated);
              } else {
                console.error("Celebration error:", response.message);
                Notification.addNotification({
                  message: response.message || "Error adding celebration",
                  type: "error",
                });
              }
            },
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Comments toggle handler
      $(".recognition-comments-btn").on("click", function (e) {
        e.preventDefault();
        var btn = $(this);
        var recordId = btn.data("record-id");
        var commentsSection = btn
          .closest(".recognition-post")
          .find(".recognition-comments");

        // Toggle comments section
        commentsSection.slideToggle();

        // Load comments if section is being shown
        if (commentsSection.is(":visible")) {
          loadComments(recordId, commentsSection, btn);
        }
      });

      // Comment form submit handler
      $(".recognition-comment-form").on("submit", function (e) {
        e.preventDefault();
        var form = $(this);
        var recordId = form.data("record-id");
        var input = form.find('input[name="content"]');
        var content = input.val().trim();
        var post = form.closest(".recognition-post");
        var commentsBtn = post.find(".recognition-comments-btn");
        var commentsSection = form.closest(".recognition-comments");

        if (!content) {
          return;
        }

        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "add_comment",
              recordid: recordId,
              type: "comment",
              content: content,
            },
            done: function (response) {
              if (response.success) {
                input.val("");
                loadComments(recordId, commentsSection, commentsBtn);
              } else {
                console.error("Comment error:", response.message);
                Notification.addNotification({
                  message: response.message || "Error adding comment",
                  type: "error",
                });
              }
            },
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      });

      // Helper function to load comments
      function loadComments(recordId, commentsSection, btn) {
        Ajax.call([
          {
            methodname: "local_recognition_handle_reaction",
            args: {
              action: "get_comments",
              recordid: recordId,
              type: "comment",
            },
            done: function (response) {
              if (response.success) {
                commentsSection.find(".comments-list").html(response.data.html);
                btn.find(".comments-count").text(response.data.count);
              } else {
                console.error("Comments error:", response.message);
                Notification.addNotification({
                  message: response.message || "Error loading comments",
                  type: "error",
                });
              }
            },
            fail: function (error) {
              console.error("AJAX error:", error);
              Notification.addNotification({
                message: "Error connecting to server",
                type: "error",
              });
            },
          },
        ]);
      }
    },
  };
});
